#!/usr/bin/env bash
# ==============================================================================
# KRB System — Host Endpoint Health Checker
# ==============================================================================
# Endpoint-based application health monitoring for:
#   1. KRB Laravel Local   : http://127.0.0.1:8000/health
#   2. LSTM FastAPI Local  : http://127.0.0.1:8001/
#   3. KRB Public Endpoint : https://receptionistkebunraya.online/health
#
# Emits structured JSON events to /var/log/krb-health.log on state transitions
# and failures for ingestion by Wazuh agent.
# Prevents alert flooding by suppressing logs when state is healthy -> healthy.
# ==============================================================================

set -u

TIMEOUT_SECS=5
LOG_FILE="/var/log/krb-health.log"
STATE_FILE="/tmp/krb-health-state.json"

LARAVEL_URL="${LARAVEL_HEALTH_URL:-http://127.0.0.1:8000/health}"
LSTM_URL="${LSTM_HEALTH_URL:-http://127.0.0.1:8001/}"
PUBLIC_URL="${PUBLIC_HEALTH_URL:-https://receptionistkebunraya.online/health}"

# Ensure log directory exists if writable
LOG_DIR=$(dirname "$LOG_FILE")
if [ ! -d "$LOG_DIR" ] && [ -w "/var/log" ]; then
    mkdir -p "$LOG_DIR" 2>/dev/null || true
fi

# Fallback log file if /var/log is not writable (e.g. non-root test runs)
if [ ! -w "$LOG_DIR" ] && [ ! -f "$LOG_FILE" ]; then
    LOG_FILE="/tmp/krb-health.log"
fi

# ------------------------------------------------------------------------------
# Check individual HTTP endpoint
# Returns: HTTP_CODE:LATENCY_MS:STATUS (healthy | down)
# ------------------------------------------------------------------------------
check_endpoint() {
    local url="$1"
    local expected_code="${2:-200}"

    local result
    result=$(curl -s -k -o /dev/null \
        -m "$TIMEOUT_SECS" \
        --connect-timeout "$TIMEOUT_SECS" \
        -w "%{http_code}:%{time_total}" \
        "$url" 2>/dev/null || echo "000:0.000")

    local http_code
    local latency_sec
    http_code=$(echo "$result" | cut -d':' -f1)
    latency_sec=$(echo "$result" | cut -d':' -f2)
    
    # Calculate ms (using awk or integer math safely)
    local latency_ms
    latency_ms=$(awk "BEGIN {printf \"%.1f\", $latency_sec * 1000}" 2>/dev/null || echo "0")

    local status="down"
    if [ "$http_code" -eq "$expected_code" ] 2>/dev/null; then
        status="healthy"
    fi

    echo "${http_code}:${latency_ms}:${status}"
}

# ------------------------------------------------------------------------------
# Read previous state from state file
# ------------------------------------------------------------------------------
read_prev_status() {
    local key="$1"
    if [ -f "$STATE_FILE" ]; then
        grep -o "\"$key\":\"[^\"]*\"" "$STATE_FILE" 2>/dev/null | cut -d':' -f2 | tr -d '"' || echo "unknown"
    else
        echo "unknown"
    fi
}

# ------------------------------------------------------------------------------
# Emit structured JSON log for Wazuh ingestion
# ------------------------------------------------------------------------------
log_health_event() {
    local service="$1"
    local event_type="$2"   # down | recovered | degraded | healthy
    local http_code="$3"
    local latency_ms="$4"
    local url="$5"
    local message="$6"
    local prev_state="$7"

    local timestamp
    timestamp=$(date -u +"%Y-%m-%dT%H:%M:%SZ")

    local log_entry
    log_entry=$(cat <<EOF
{"timestamp":"$timestamp","service":"$service","event_type":"$event_type","status":"$event_type","previous_status":"$prev_state","http_code":$http_code,"latency_ms":$latency_ms,"url":"$url","message":"$message","monitor":"krb-endpoint-checker"}
EOF
)

    echo "$log_entry" >> "$LOG_FILE" 2>/dev/null || true
}

# ------------------------------------------------------------------------------
# Perform all checks
# ------------------------------------------------------------------------------
TIMESTAMP=$(date -u +"%Y-%m-%dT%H:%M:%SZ")

# 1. Check Laravel Local
RES_LARAVEL=$(check_endpoint "$LARAVEL_URL" 200)
CODE_LARAVEL=$(echo "$RES_LARAVEL" | cut -d':' -f1)
MS_LARAVEL=$(echo "$RES_LARAVEL" | cut -d':' -f2)
STATUS_LARAVEL=$(echo "$RES_LARAVEL" | cut -d':' -f3)
PREV_LARAVEL=$(read_prev_status "krb_laravel_local")

# 2. Check LSTM Local
RES_LSTM=$(check_endpoint "$LSTM_URL" 200)
CODE_LSTM=$(echo "$RES_LSTM" | cut -d':' -f1)
MS_LSTM=$(echo "$RES_LSTM" | cut -d':' -f2)
STATUS_LSTM=$(echo "$RES_LSTM" | cut -d':' -f3)
PREV_LSTM=$(read_prev_status "lstm_local")

# 3. Check KRB Public
RES_PUBLIC=$(check_endpoint "$PUBLIC_URL" 200)
CODE_PUBLIC=$(echo "$RES_PUBLIC" | cut -d':' -f1)
MS_PUBLIC=$(echo "$RES_PUBLIC" | cut -d':' -f2)
STATUS_PUBLIC=$(echo "$RES_PUBLIC" | cut -d':' -f3)
PREV_PUBLIC=$(read_prev_status "krb_public")

# ------------------------------------------------------------------------------
# Evaluate State Transitions & Log Wazuh Events
# ------------------------------------------------------------------------------

# Laravel Evaluation
if [ "$STATUS_LARAVEL" = "down" ]; then
    log_health_event "krb_laravel_local" "down" "$CODE_LARAVEL" "$MS_LARAVEL" "$LARAVEL_URL" "KRB Laravel application endpoint is unavailable." "$PREV_LARAVEL"
elif [ "$STATUS_LARAVEL" = "healthy" ] && [ "$PREV_LARAVEL" = "down" ]; then
    log_health_event "krb_laravel_local" "recovered" "$CODE_LARAVEL" "$MS_LARAVEL" "$LARAVEL_URL" "KRB Laravel application endpoint recovered." "$PREV_LARAVEL"
fi

# LSTM Evaluation
if [ "$STATUS_LSTM" = "down" ]; then
    log_health_event "lstm_local" "down" "$CODE_LSTM" "$MS_LSTM" "$LSTM_URL" "LSTM Forecast Service endpoint is unavailable." "$PREV_LSTM"
elif [ "$STATUS_LSTM" = "healthy" ] && [ "$PREV_LSTM" = "down" ]; then
    log_health_event "lstm_local" "recovered" "$CODE_LSTM" "$MS_LSTM" "$LSTM_URL" "LSTM Forecast Service endpoint recovered." "$PREV_LSTM"
fi

# Public Endpoint Evaluation
if [ "$STATUS_PUBLIC" = "down" ]; then
    log_health_event "krb_public" "down" "$CODE_PUBLIC" "$MS_PUBLIC" "$PUBLIC_URL" "KRB public endpoint is unavailable." "$PREV_PUBLIC"
elif [ "$STATUS_PUBLIC" = "healthy" ] && [ "$PREV_PUBLIC" = "down" ]; then
    log_health_event "krb_public" "recovered" "$CODE_PUBLIC" "$MS_PUBLIC" "$PUBLIC_URL" "KRB public endpoint recovered." "$PREV_PUBLIC"
fi

# ------------------------------------------------------------------------------
# Save current state to state file
# ------------------------------------------------------------------------------
cat <<EOF > "$STATE_FILE"
{
  "updated_at": "$TIMESTAMP",
  "krb_laravel_local": "$STATUS_LARAVEL",
  "lstm_local": "$STATUS_LSTM",
  "krb_public": "$STATUS_PUBLIC"
}
EOF

# ------------------------------------------------------------------------------
# Output Machine-Readable Summary to stdout
# ------------------------------------------------------------------------------
OVERALL="healthy"
EXIT_CODE=0

if [ "$STATUS_LARAVEL" = "down" ] || [ "$STATUS_LSTM" = "down" ] || [ "$STATUS_PUBLIC" = "down" ]; then
    if [ "$STATUS_LARAVEL" = "down" ] && [ "$STATUS_LSTM" = "down" ] && [ "$STATUS_PUBLIC" = "down" ]; then
        OVERALL="down"
    else
        OVERALL="degraded"
    fi
    EXIT_CODE=1
fi

echo "KRB_LOCAL=${STATUS_LARAVEL} (${CODE_LARAVEL}, ${MS_LARAVEL}ms) LSTM_LOCAL=${STATUS_LSTM} (${CODE_LSTM}, ${MS_LSTM}ms) KRB_PUBLIC=${STATUS_PUBLIC} (${CODE_PUBLIC}, ${MS_PUBLIC}ms) OVERALL=${OVERALL}"

exit $EXIT_CODE