# LSTM Service Setup Guide

## Overview

The LSTM (Long Short-Term Memory) service provides advanced time-series predictions for upcoming visitors and room/vehicle occupancies using deep learning. It runs as a separate Python microservice that communicates with Laravel via HTTP API.

## Architecture

```
Laravel App → LSTMClient.php → HTTP API → LSTM_Service.py (FastAPI) → TensorFlow/Keras
```

## Prerequisites

- Python 3.8 or higher
- pip (Python package manager)
- Administrator access (for initial installation)

## Installation Steps

### Step 1: Install Python Packages

**Option A: Using the batch file (Recommended)**

1. Right-click `install_python_packages.bat`
2. Select "Run as administrator"
3. Wait for installation to complete

**Option B: Manual installation**

Open PowerShell as Administrator and run:

```powershell
python -m pip install --user fastapi uvicorn tensorflow pandas scikit-learn
```

### Step 2: Verify Installation

```powershell
python -c "import fastapi, uvicorn, tensorflow, pandas, sklearn; print('All packages installed successfully!')"
```

If you see "All packages installed successfully!", you're good to go!

### Step 3: Configure Environment

The `.env` file should already have these settings:

```env
LSTM_SERVICE_URL=http://127.0.0.1:8001
LSTM_SERVICE_TIMEOUT=30
```

## Starting the LSTM Service

### Option 1: Using the batch file (Easiest)

Double-click `start_lstm_service.bat`

The service will start on `http://127.0.0.1:8001`

### Option 2: Manual start

```powershell
cd C:\laragon\www\KRB-System-Caps-main\Capstone-copy
python -m uvicorn app.Services.AI.LSTM_Service:app --host 127.0.0.1 --port 8001 --reload
```

### Verify Service is Running

Open browser and go to: `http://127.0.0.1:8001`

You should see:
```json
{
  "status": "healthy",
  "service": "LSTM Prediction Service",
  "version": "1.0.0"
}
```

## Testing the Integration

### Test 1: Check Service Availability

```php
use App\Services\AI\LSTMClient;

$client = new LSTMClient();
$isAvailable = $client->isAvailable();

if ($isAvailable) {
    echo "LSTM service is running!";
} else {
    echo "LSTM service is not available. Using fallback predictions.";
}
```

### Test 2: Make a Prediction

```php
use App\Services\AI\PredictionService;

$predictionService = new PredictionService();
$companyId = Auth::user()->company_id;

$forecast = $predictionService->predictBookingDemand('room_booking', $companyId, 7);

foreach ($forecast as $day) {
    echo "Date: {$day['date']}\n";
    echo "Predicted: {$day['predicted_count']}\n";
    echo "Method: {$day['method']}\n"; // 'lstm' or 'fallback'
    echo "Confidence: {$day['confidence']}\n\n";
}
```

## How It Works

### 1. Data Flow

```
Database → DataPreprocessor → Time Series Data → LSTMClient → LSTM Service → Predictions
```

### 2. LSTM Service Features

- **Deep Learning**: Uses LSTM neural networks for accurate predictions
- **Feature Engineering**: Automatically extracts day-of-week and weekend patterns
- **Confidence Intervals**: Provides upper/lower bounds for predictions
- **RMSE Calculation**: Measures prediction accuracy

### 3. Fallback Mechanism

If the LSTM service is unavailable, the system automatically falls back to:
- Simple Moving Average (SMA)
- Trend analysis
- Day-of-week adjustments

This ensures predictions are always available, even if the Python service is down.

## API Endpoints

### Health Check

```
GET http://127.0.0.1:8001/
```

Response:
```json
{
  "status": "healthy",
  "service": "LSTM Prediction Service",
  "version": "1.0.0"
}
```

### Predict

```
POST http://127.0.0.1:8001/predict
Content-Type: application/json

{
  "data": [
    {"date": "2026-01-01", "count": 5},
    {"date": "2026-01-02", "count": 7},
    ...
  ],
  "forecast_days": 7
}
```

Response:
```json
{
  "rmse": 1.23,
  "predictions": [
    {
      "date": "2026-04-01",
      "predicted": 8.5,
      "lower_bound": 6.2,
      "upper_bound": 10.8,
      "confidence": 0.85
    },
    ...
  ]
}
```

## Troubleshooting

### Issue 1: "Access is denied" during pip install

**Solution**: Run PowerShell as Administrator or use `--user` flag:

```powershell
python -m pip install --user fastapi uvicorn tensorflow pandas scikit-learn
```

### Issue 2: "Module not found" error

**Solution**: Verify Python path and reinstall packages:

```powershell
python -m pip list
python -m pip install --upgrade --user fastapi uvicorn tensorflow pandas scikit-learn
```

### Issue 3: LSTM service won't start

**Solution**: Check if port 8001 is already in use:

```powershell
netstat -ano | findstr :8001
```

If port is in use, change the port in `.env` and `start_lstm_service.bat`:

```env
LSTM_SERVICE_URL=http://127.0.0.1:8002
```

### Issue 4: Predictions are always using fallback

**Cause**: LSTM service is not running or not reachable

**Solution**:
1. Start the LSTM service using `start_lstm_service.bat`
2. Verify it's running: `http://127.0.0.1:8001`
3. Check Laravel logs: `storage/logs/laravel.log`

### Issue 5: TensorFlow warnings about CPU

**Solution**: These warnings are normal. TensorFlow is optimized for GPU but works fine on CPU. To suppress warnings:

```python
import os
os.environ['TF_CPP_MIN_LOG_LEVEL'] = '2'
```

Add this at the top of `LSTM_Service.py`.

## Performance Optimization

### 1. Model Caching

Cache trained models to avoid retraining:

```python
import pickle

# Save model
with open('lstm_model.pkl', 'wb') as f:
    pickle.dump(model, f)

# Load model
with open('lstm_model.pkl', 'rb') as f:
    model = pickle.load(f)
```

### 2. Batch Predictions

Process multiple prediction requests in batches:

```php
$forecasts = [];
foreach (['room_booking', 'vehicle_booking', 'guestbook'] as $type) {
    $forecasts[$type] = $predictionService->predictBookingDemand($type, $companyId);
}
```

### 3. Background Processing

Use Laravel queues for long-running predictions:

```php
use Illuminate\Support\Facades\Queue;

Queue::push(function() use ($companyId) {
    $predictionService = new PredictionService();
    $forecast = $predictionService->predictBookingDemand('room_booking', $companyId, 30);
    
    // Store results in cache
    Cache::put("forecast_{$companyId}", $forecast, 3600);
});
```

## Production Deployment

### 1. Run as Windows Service

Use NSSM (Non-Sucking Service Manager):

```powershell
nssm install LSTMService "C:\Python312\python.exe" "-m uvicorn app.Services.AI.LSTM_Service:app --host 127.0.0.1 --port 8001"
nssm start LSTMService
```

### 2. Use Process Manager

Use PM2 or similar:

```powershell
npm install -g pm2
pm2 start "python -m uvicorn app.Services.AI.LSTM_Service:app --host 127.0.0.1 --port 8001" --name lstm-service
pm2 save
pm2 startup
```

## Monitoring

### Check Service Status

```php
use App\Services\AI\LSTMClient;

$client = new LSTMClient();
if (!$client->isAvailable()) {
    Log::warning('LSTM service is down');
}
```

### Log Predictions

```php
Log::info('LSTM Prediction', [
    'type' => 'room_booking',
    'company_id' => $companyId,
    'method' => $forecast[0]['method'],
    'predictions' => count($forecast),
]);
```

## Advanced Configuration

### Adjust LSTM Parameters

Edit `LSTM_Service.py`:

```python
model.add(LSTM(128, input_shape=input_shape))  # Default: 64

model.fit(X_train, y_train, epochs=50, verbose=0)  # Default: 20

X, y = create_sequences(scaled, window=14)  # Default: 7
```

### Custom Features

Add more features to improve predictions:

```python
def create_features(df):
    df['date'] = pd.to_datetime(df['date'])
    df['day_of_week'] = df['date'].dt.dayofweek
    df['is_weekend'] = df['day_of_week'].isin([5, 6]).astype(int)
    df['month'] = df['date'].dt.month
    df['is_holiday'] = df['date'].isin(holidays).astype(int)  
    return df
```

## Next Steps

1. **Monitor Performance**: Track RMSE and prediction accuracy
2. **Tune Hyperparameters**: Adjust LSTM layers, epochs, window size
3. **Add More Features**: Include holidays, weather, events
4. **Implement Caching**: Cache predictions to reduce API calls
5. **Set Up Monitoring**: Use tools like Prometheus or Grafana

## Support

For issues or questions:
1. Check Laravel logs: `storage/logs/laravel.log`
2. Check LSTM service console output
3. Review this documentation
4. Contact system administrator
