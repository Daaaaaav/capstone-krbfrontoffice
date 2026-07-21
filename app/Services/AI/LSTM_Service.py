import time
import numpy as np
import pandas as pd
import holidays
import os
import hashlib
import json
import pickle
import logging

from datetime import datetime, timedelta
from typing import List, Optional

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, Field

from tensorflow.keras.models import Sequential, load_model
from tensorflow.keras.layers import LSTM, Dense, Dropout
from tensorflow.keras.callbacks import EarlyStopping
from tensorflow.keras.regularizers import l2

from sklearn.preprocessing import MinMaxScaler
from sklearn.metrics import (
    mean_squared_error,
    mean_absolute_error,
    mean_absolute_percentage_error,
    r2_score,
)

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI(
    title="LSTM Forecast Service",
    version="2.2.0"
)

_raw_origins = os.getenv("ALLOWED_ORIGINS", "*")
_origins = [o.strip() for o in _raw_origins.split(",") if o.strip()]

app.add_middleware(
    CORSMiddleware,
    allow_origins=_origins,
    allow_origin_regex=r"https://.*\.ngrok-free\.app",
    allow_credentials=True,
    allow_methods=["GET", "POST"],
    allow_headers=["*"],
)


# ── MODEL CACHE DIRECTORY ────────────────────────────────────────────────────

# Stored alongside this script; can be overridden via env var
MODEL_DIR = os.getenv(
    "LSTM_MODEL_DIR",
    os.path.join(os.path.dirname(os.path.abspath(__file__)), "model_cache")
)
os.makedirs(MODEL_DIR, exist_ok=True)

MODEL_PATH       = os.path.join(MODEL_DIR, "lstm_model.keras")
SCALER_PATH      = os.path.join(MODEL_DIR, "scaler.pkl")
FINGERPRINT_PATH = os.path.join(MODEL_DIR, "fingerprint.json")
METRICS_PATH     = os.path.join(MODEL_DIR, "model_metrics.json")


# ── HEALTH CHECK ─────────────────────────────────────────────────────────────

@app.get("/")
def health_check():
    return {
        "status":  "healthy",
        "service": "Improved LSTM Forecast Service",
        "version": "2.2.0"
    }


# ── CONFIG MODEL ─────────────────────────────────────────────────────────────

class LSTMConfig(BaseModel):
    lstm_units:          int   = Field(default=64,      ge=8,   le=512)
    dropout_rate:        float = Field(default=0.1,     ge=0.0, le=0.5)
    l2_regularization:   float = Field(default=1e-5,    ge=0.0, le=0.1)
    sequence_window:     int   = Field(default=7,       ge=3,   le=60)
    epochs:              int   = Field(default=150,     ge=1,   le=1000)
    batch_size:          int   = Field(default=16,      ge=4,   le=256)
    validation_split:    float = Field(default=0.15,    ge=0.05, le=0.30)
    early_stop_patience: int   = Field(default=10,      ge=1,   le=50)
    min_data_points:     int   = Field(default=45,      ge=10)
    history_days:        int   = Field(default=730,     ge=30)
    confidence_min:      float = Field(default=0.30,    ge=0.0, le=1.0)
    confidence_max:      float = Field(default=0.92,    ge=0.0, le=1.0)


# ── REQUEST MODELS ────────────────────────────────────────────────────────────

class DataPoint(BaseModel):
    date: str
    count: float


class RequestData(BaseModel):
    data: List[DataPoint]
    forecast_days: int = 7
    use_dummy_data: bool = False
    lstm_config: Optional[LSTMConfig] = None
    force_retrain: bool = False  # set True to skip cache and retrain

    def get_config(self) -> LSTMConfig:
        return self.lstm_config if self.lstm_config is not None else LSTMConfig()


# ── MODEL FINGERPRINTING ──────────────────────────────────────────────────────

def _data_signature(df: pd.DataFrame) -> str:
    """
    A lightweight signature of the training dataset.
    Uses the sorted date range + total count sum so we retrain when
    new data arrives but not on every tiny fluctuation.
    """
    min_date  = str(df['date'].min())
    max_date  = str(df['date'].max())
    n_rows    = len(df)
    total     = round(float(df['count'].sum()), 2)
    return f"{min_date}|{max_date}|{n_rows}|{total}"


def compute_fingerprint(cfg: LSTMConfig, df: pd.DataFrame) -> str:
    """SHA-256 of (config JSON + data signature)."""
    payload = json.dumps(cfg.dict(), sort_keys=True) + "|" + _data_signature(df)
    return hashlib.sha256(payload.encode()).hexdigest()


def load_fingerprint() -> Optional[str]:
    if not os.path.exists(FINGERPRINT_PATH):
        return None
    try:
        with open(FINGERPRINT_PATH, "r") as f:
            return json.load(f).get("fingerprint")
    except Exception:
        return None


def save_fingerprint(fp: str, trained_at: str, training_samples: int) -> None:
    with open(FINGERPRINT_PATH, "w") as f:
        json.dump({
            "fingerprint":      fp,
            "trained_at":       trained_at,
            "training_samples": training_samples
        }, f, indent=2)


def load_fingerprint_meta() -> dict:
    if not os.path.exists(FINGERPRINT_PATH):
        return {}
    try:
        with open(FINGERPRINT_PATH, "r") as f:
            return json.load(f)
    except Exception:
        return {}


# ── MODEL METRICS PERSISTENCE ─────────────────────────────────────────────────

def save_model_metrics(metrics: dict) -> None:
    """Persist evaluation metrics to METRICS_PATH after every training run."""
    try:
        with open(METRICS_PATH, "w") as f:
            json.dump(metrics, f, indent=2)
        logger.info("Model metrics saved to %s", METRICS_PATH)
    except Exception as e:
        logger.warning("Could not save model metrics: %s", e)


def load_model_metrics() -> Optional[dict]:
    """Load previously stored evaluation metrics. Returns None if absent."""
    if not os.path.exists(METRICS_PATH):
        return None
    try:
        with open(METRICS_PATH, "r") as f:
            return json.load(f)
    except Exception:
        return None


# ── SAVE / LOAD MODEL + SCALER ────────────────────────────────────────────────

def save_model_and_scaler(model, scaler: MinMaxScaler) -> None:
    model.save(MODEL_PATH)
    with open(SCALER_PATH, "wb") as f:
        pickle.dump(scaler, f)
    logger.info("Model and scaler saved to %s", MODEL_DIR)


def load_model_and_scaler():
    """Returns (model, scaler) or (None, None) if cache is missing."""
    if not os.path.exists(MODEL_PATH) or not os.path.exists(SCALER_PATH):
        return None, None
    try:
        model = load_model(MODEL_PATH)
        with open(SCALER_PATH, "rb") as f:
            scaler = pickle.load(f)
        logger.info("Loaded cached model from %s", MODEL_PATH)
        return model, scaler
    except Exception as e:
        logger.warning("Failed to load cached model: %s", e)
        return None, None


# ── FEATURE ENGINEERING ───────────────────────────────────────────────────────

def create_features(df):
    df['date'] = pd.to_datetime(df['date'])
    df = df.sort_values('date')

    df['day_of_week'] = df['date'].dt.dayofweek
    df['month']       = df['date'].dt.month

    df['is_weekend'] = (
        df['day_of_week']
        .isin([5, 6])
        .astype(int)
    )

    id_holidays = holidays.ID()
    df['is_holiday'] = df['date'].apply(
        lambda x: 1 if x in id_holidays else 0
    )

    df['lag_1']      = df['count'].shift(1)
    df['lag_7']      = df['count'].shift(7)
    df['rolling_7']  = df['count'].rolling(window=7).mean()
    df['rolling_14'] = df['count'].rolling(window=14).mean()

    df = df.dropna().reset_index(drop=True)
    return df


# ── PREPROCESSING ─────────────────────────────────────────────────────────────

FEATURE_COLUMNS = [
    'count',
    'day_of_week',
    'month',
    'is_weekend',
    'is_holiday',
    'lag_1',
    'lag_7',
    'rolling_7',
    'rolling_14'
]


def preprocess(df, scaler: Optional[MinMaxScaler] = None):
    """
    If a fitted scaler is provided (inference mode) use it directly.
    Otherwise fit a new one (training mode).
    Returns (scaled_array, scaler).
    """
    features = df[FEATURE_COLUMNS]
    if scaler is None:
        scaler = MinMaxScaler()
        scaled = scaler.fit_transform(features)
    else:
        scaled = scaler.transform(features)
    return scaled, scaler


# ── CREATE SEQUENCES ──────────────────────────────────────────────────────────

def create_sequences(data, window: int = 7):
    X, y = [], []
    for i in range(len(data) - window):
        X.append(data[i:i + window])
        y.append(data[i + window][0])
    return np.array(X), np.array(y)


# ── BUILD MODEL ───────────────────────────────────────────────────────────────

def build_model(input_shape, cfg: LSTMConfig):
    model = Sequential()
    model.add(
        LSTM(
            cfg.lstm_units,
            input_shape=input_shape,
            kernel_regularizer=l2(cfg.l2_regularization),
            recurrent_regularizer=l2(cfg.l2_regularization),
        )
    )
    model.add(Dropout(cfg.dropout_rate))
    model.add(Dense(1))
    model.compile(optimizer='adam', loss='mse')
    return model


# ── CONFIDENCE SCORE ──────────────────────────────────────────────────────────

def compute_confidence(rmse: float, y_test: np.ndarray, cfg: LSTMConfig) -> float:
    data_range = float(np.max(y_test) - np.min(y_test))
    if data_range < 1e-6:
        return 0.5
    nrmse      = rmse / data_range
    confidence = max(cfg.confidence_min, min(cfg.confidence_max, 1.0 - nrmse))
    return round(confidence, 4)


# ── FORECAST ──────────────────────────────────────────────────────────────────

def forecast(model, data, scaler, df, days: int = 7, window: int = 7):
    results     = []
    last_seq    = data[-window:].copy()
    last_date   = df['date'].max()
    id_holidays = holidays.ID()

    for i in range(days):
        pred_scaled = model.predict(
            last_seq.reshape(1, window, -1),
            verbose=0
        )[0][0]

        next_date  = last_date + timedelta(days=i + 1)
        dow        = next_date.dayofweek
        month      = next_date.month
        is_weekend = int(dow in [5, 6])
        is_holiday = int(next_date in id_holidays)

        lag_1      = pred_scaled
        lag_7      = last_seq[-7][0] if len(last_seq) > 7 else pred_scaled
        rolling_7  = np.mean([x[0] for x in last_seq[-7:]])
        rolling_14 = (
            np.mean([x[0] for x in last_seq[-14:]])
            if len(last_seq) > 14
            else rolling_7
        )

        new_row  = [pred_scaled, dow, month, is_weekend, is_holiday, lag_1, lag_7, rolling_7, rolling_14]
        last_seq = np.vstack([last_seq[1:], new_row])

        dummy       = np.zeros((1, len(FEATURE_COLUMNS)))
        dummy[0][0] = pred_scaled
        inv         = scaler.inverse_transform(dummy)[0][0]

        results.append({
            "date":      next_date.strftime("%Y-%m-%d"),
            "predicted": float(max(0, inv))
        })

    return results


# ── DUMMY DATA GENERATOR ──────────────────────────────────────────────────────

def generate_dummy_booking_data(days: int = 180):
    import random
    data        = []
    start_date  = datetime.now() - timedelta(days=days)
    id_holidays = holidays.ID()

    for i in range(days):
        date  = start_date + timedelta(days=i)
        dow   = date.weekday()
        count = 20
        if dow < 5:
            count += random.randint(5, 10)
        else:
            count += random.randint(-2, 5)
        if date in id_holidays:
            count += random.randint(15, 30)
        count += i * 0.03
        count += random.randint(-4, 4)
        count  = max(1, int(count))
        data.append({"date": date.strftime("%Y-%m-%d"), "count": float(count)})

    return data


# ── CORE TRAIN-OR-LOAD LOGIC ──────────────────────────────────────────────────

def _get_or_train_model(df: pd.DataFrame, cfg: LSTMConfig, force_retrain: bool = False):
    """
    Returns (model, scaler, scaled, X_train, y_train, X_test, y_test,
             from_cache, history, training_time_seconds).

    - If a saved model exists AND its fingerprint matches the current
      config + data, it is loaded directly (no training).
    - Otherwise the model is trained from scratch and the result is saved.
    - force_retrain=True bypasses the fingerprint check entirely.
    """
    current_fp = compute_fingerprint(cfg, df)
    saved_fp   = load_fingerprint()

    use_cache = (
        not force_retrain
        and saved_fp == current_fp
        and os.path.exists(MODEL_PATH)
        and os.path.exists(SCALER_PATH)
    )

    # Always build scaled data so we can run forecast() afterwards
    df_feat        = create_features(df.copy())
    scaled, scaler_fit = preprocess(df_feat)  # fresh fit for sequences
    X, y           = create_sequences(scaled, window=cfg.sequence_window)
    split          = int(len(X) * 0.8)
    X_train, X_test = X[:split], X[split:]
    y_train, y_test = y[:split], y[split:]

    if use_cache:
        model, scaler = load_model_and_scaler()
        if model is not None:
            logger.info("Cache hit — skipping training.")
            return model, scaler, scaled, X_train, y_train, X_test, y_test, True, None, None

    # ── Train ────────────────────────────────────────────────────────────────
    logger.info("Training new LSTM model (force=%s)…", force_retrain)
    model = build_model((X.shape[1], X.shape[2]), cfg)

    early_stop = EarlyStopping(
        monitor='val_loss',
        patience=cfg.early_stop_patience,
        restore_best_weights=True,
        min_delta=1e-4,
    )

    t_start = time.time()
    history = model.fit(
        X_train, y_train,
        epochs=cfg.epochs,
        batch_size=cfg.batch_size,
        validation_split=cfg.validation_split,
        callbacks=[early_stop],
        verbose=0
    )
    training_time = round(time.time() - t_start, 2)

    save_model_and_scaler(model, scaler_fit)
    trained_at = datetime.now().isoformat()
    save_fingerprint(current_fp, trained_at, int(len(X_train)))

    return model, scaler_fit, scaled, X_train, y_train, X_test, y_test, False, history, training_time


# ── MAIN PREDICTION ENDPOINT ──────────────────────────────────────────────────

@app.post("/predict")
def predict(request: RequestData):

    cfg = request.get_config()

    if request.use_dummy_data:
        dummy_data = generate_dummy_booking_data(180)
        df = pd.DataFrame(dummy_data)
    else:
        df = pd.DataFrame([d.dict() for d in request.data])

    if len(df) < cfg.min_data_points:
        return {
            "error":       "Insufficient data",
            "message":     (
                f"Need at least {cfg.min_data_points} data points, "
                f"got {len(df)}"
            ),
            "predictions": []
        }

    model, scaler, scaled, X_train, y_train, X_test, y_test, from_cache, history, training_time = \
        _get_or_train_model(df, cfg, force_retrain=request.force_retrain)

    # We need the feature-engineered df for forecast()
    df_feat = create_features(df.copy())

    preds = model.predict(X_test, verbose=0)

    rmse = np.sqrt(mean_squared_error(y_test, preds))
    mae  = mean_absolute_error(y_test, preds)
    mape = mean_absolute_percentage_error(y_test, preds)
    r2   = r2_score(y_test, preds)

    future = forecast(
        model, scaled, scaler, df_feat,
        request.forecast_days,
        window=cfg.sequence_window
    )

    recent    = df_feat['count'].tail(90)
    nonzero   = recent[recent > 0]
    hist_floor = float(nonzero.mean()) if len(nonzero) > 0 else 0.0

    confidence_score = compute_confidence(rmse, y_test, cfg)

    count_min   = scaler.data_min_[0]
    count_max   = scaler.data_max_[0]
    count_range = count_max - count_min if count_max > count_min else 1.0
    rmse_real   = float(rmse) * count_range

    final = []
    for item in future:
        raw_pred = item["predicted"]
        if raw_pred < hist_floor * 0.1 and hist_floor > 0:
            raw_pred = hist_floor * 0.5
        lower = max(0.0, raw_pred - 1.96 * rmse_real)
        upper = raw_pred + 1.96 * rmse_real
        final.append({
            "date":        item["date"],
            "predicted":   round(raw_pred, 2),
            "lower_bound": round(lower, 2),
            "upper_bound": round(upper, 2),
            "confidence":  confidence_score,
        })

    # ── Persist full evaluation metrics after every prediction ──────────────
    # When training ran (from_cache=False), capture the loss history.
    # When loaded from cache, carry over whichever history was stored previously.
    loss_history      = None
    val_loss_history  = None
    final_train_loss  = None
    final_val_loss    = None
    actual_epochs     = None
    val_samples       = None

    if history is not None:
        loss_history     = [round(v, 6) for v in history.history.get("loss", [])]
        val_loss_history = [round(v, 6) for v in history.history.get("val_loss", [])]
        actual_epochs    = len(loss_history)
        final_train_loss = loss_history[-1]     if loss_history     else None
        final_val_loss   = val_loss_history[-1] if val_loss_history else None
        # approximate validation sample count from the training split fraction
        val_samples      = round(len(X_train) * cfg.validation_split)
    else:
        # Cache hit — load previously stored history values if present
        stored = load_model_metrics()
        if stored:
            loss_history     = stored.get("loss_history")
            val_loss_history = stored.get("val_loss_history")
            actual_epochs    = stored.get("epochs_run")
            final_train_loss = stored.get("training_loss")
            final_val_loss   = stored.get("validation_loss")
            val_samples      = stored.get("validation_samples")

    meta       = load_fingerprint_meta()
    trained_at = meta.get("trained_at") if from_cache else datetime.now().isoformat()

    metrics_payload = {
        "trained_at":         trained_at,
        "from_cache":         from_cache,
        "epochs_run":         actual_epochs,
        "training_loss":      final_train_loss,
        "validation_loss":    final_val_loss,
        "mae":                round(float(mae),  4),
        "rmse":               round(float(rmse), 4),
        "mape":               round(float(mape), 4),
        "r2":                 round(float(r2),   4),
        "training_samples":   len(X_train),
        "validation_samples": val_samples,
        "test_samples":       len(X_test),
        "training_time":      training_time,
        "loss_history":       loss_history,
        "val_loss_history":   val_loss_history,
    }

    # Only overwrite the file when real training happened, or no file exists yet
    if not from_cache or not os.path.exists(METRICS_PATH):
        save_model_metrics(metrics_payload)

    return {
        "model":            "Improved LSTM Forecast Model",
        "features_used":    FEATURE_COLUMNS,
        "config_used":      cfg.dict(),
        "from_cache":       from_cache,
        "metrics": {
            "rmse": round(float(rmse), 4),
            "mae":  round(float(mae),  4),
            "mape": round(float(mape), 4),
            "r2":   round(float(r2),   4),
        },
        "rmse":             round(float(rmse), 4),
        "predictions":      final,
        "data_source":      "dummy" if request.use_dummy_data else "real",
        "training_samples": len(X_train),
        "test_samples":     len(X_test),
    }


# ── 3-WEEK FORECAST ENDPOINT ──────────────────────────────────────────────────

@app.post("/predict-3weeks")
def predict_three_weeks(request: RequestData):

    cfg = request.get_config()
    request.forecast_days = 21

    result = predict(request)

    if "predictions" in result and result["predictions"]:
        weekly_summary = []
        predictions    = result["predictions"]

        for week_num in range(3):
            start_idx = week_num * 7
            end_idx   = min(start_idx + 7, len(predictions))
            week_data = predictions[start_idx:end_idx]

            if week_data:
                avg_predicted = sum(p["predicted"] for p in week_data) / len(week_data)
                weekly_summary.append({
                    "week":            week_num + 1,
                    "start_date":      week_data[0]["date"],
                    "end_date":        week_data[-1]["date"],
                    "avg_predicted":   round(avg_predicted, 2),
                    "total_predicted": round(sum(p["predicted"] for p in week_data), 2)
                })

        result["weekly_summary"]  = weekly_summary
        result["forecast_period"] = "3 weeks (21 days)"
        result["title"]           = "Booking Predictions for the Following 3 Weeks"

    return result


# ── DEMO ENDPOINT ─────────────────────────────────────────────────────────────

@app.get("/demo")
def demo_prediction():

    dummy_data = generate_dummy_booking_data(180)
    request = RequestData(
        data=[DataPoint(**d) for d in dummy_data],
        forecast_days=21,
        use_dummy_data=True
    )
    result = predict_three_weeks(request)

    return {
        "title":                "LSTM Model Predictions (Based on Dummy Booking Counts) for the Following 3 Weeks",
        "description":          "Uses holiday-aware forecasting, lag features, and rolling averages",
        "training_data_points": 180,
        "forecast_days":        21,
        **result
    }


# ── MODEL INFO ENDPOINT ───────────────────────────────────────────────────────

@app.get("/model-info")
def model_info():
    """Returns the current cache status without running any prediction."""
    meta        = load_fingerprint_meta()
    model_exists  = os.path.exists(MODEL_PATH)
    scaler_exists = os.path.exists(SCALER_PATH)

    model_size_kb = (
        round(os.path.getsize(MODEL_PATH) / 1024, 1)
        if model_exists else None
    )

    return {
        "cache_directory":    MODEL_DIR,
        "model_file":         MODEL_PATH,
        "model_exists":       model_exists,
        "model_size_kb":      model_size_kb,
        "scaler_exists":      scaler_exists,
        "last_trained_at":    meta.get("trained_at"),
        "training_samples":   meta.get("training_samples"),
        "fingerprint":        meta.get("fingerprint"),
        "note": (
            "Fingerprint changes when config or data changes, "
            "triggering automatic retraining."
        )
    }


# ── FORCE RETRAIN ENDPOINT ────────────────────────────────────────────────────

@app.post("/retrain")
def force_retrain(request: RequestData):
    """
    Same as /predict but always retrains regardless of cached fingerprint.
    Useful after manually updating hyperparameters or after a large data refresh.
    """
    request.force_retrain = True
    return predict(request)


# ── MODEL METRICS ENDPOINT ────────────────────────────────────────────────────

@app.get("/model-metrics")
def model_metrics():
    """
    Returns the persisted evaluation metrics from the last training run.
    Does NOT trigger any training or prediction — display-only.
    """
    stored = load_model_metrics()
    if stored is None:
        return {
            "available": False,
            "message":   "No evaluation metrics found. Train the model at least once.",
        }
    return {
        "available":          True,
        "trained_at":         stored.get("trained_at"),
        "from_cache":         stored.get("from_cache"),
        "epochs_run":         stored.get("epochs_run"),
        "training_loss":      stored.get("training_loss"),
        "validation_loss":    stored.get("validation_loss"),
        "mae":                stored.get("mae"),
        "rmse":               stored.get("rmse"),
        "mape":               stored.get("mape"),
        "r2":                 stored.get("r2"),
        "training_samples":   stored.get("training_samples"),
        "validation_samples": stored.get("validation_samples"),
        "test_samples":       stored.get("test_samples"),
        "training_time":      stored.get("training_time"),
        "loss_history":       stored.get("loss_history"),
        "val_loss_history":   stored.get("val_loss_history"),
    }
