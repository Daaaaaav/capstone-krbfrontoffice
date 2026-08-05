# Occupancy Forecast Summary Cards Fix

## Problem
The dashboard summary cards for occupancy forecasting were displaying "—" instead of forecast values:
- Avg Room Occupancy: —
- Avg Vehicle Bookings: —  
- Peak Day: —
- Total Forecast: —

Historical averages displayed correctly, but forecast averages were null.

## Root Cause
When the LSTM service was available but historical data was insufficient (< 45 data points), the `LSTMClient::predict()` method returned `null`. The component didn't fall back to the moving average forecast in this scenario, leaving forecast arrays as `null` and causing all summary cards to display "—".

Additionally, if the prediction data structure was malformed (missing 'predicted' keys), the extraction logic would include `null` values in the averages calculation.

## Solution Implemented

### 1. Added Fallback to Moving Average (lines 190-221)
When LSTM is available but returns `null` or empty predictions, the system now falls back to the moving average forecast method. This ensures forecasts are always available when historical data exists, even if LSTM can't generate predictions.

```php
// Fallback to moving average if LSTM returns no predictions due to insufficient data
if (($roomForecast === null || empty($roomForecast)) && in_array($this->forecastType, ['room', 'combined'])) {
    $maSettings = AISettings::getMultiple([...]);
    $roomForecast = $this->movingAverageForecast($roomHistory, $this->forecastDays, $maSettings);
    
    if (config('app.debug')) {
        Log::info('OccupancyForecasting: falling back to MA for room forecast', [
            'reason' => 'LSTM returned null or empty',
            'ma_forecast_count' => count($roomForecast),
        ]);
    }
}
```

The same fallback logic applies for vehicle forecasts.

### 2. Added Null Value Filtering (line 544-545)
Added filtering to remove `null` values from the predictions array in case of data structure issues where predictions exist but individual items lack the 'predicted' key.

```php
// Filter out null values in case predictions array has items but missing 'predicted' keys
$roomPredictions    = array_filter($roomPredictions, fn($v) => $v !== null);
$vehiclePredictions = array_filter($vehiclePredictions, fn($v) => $v !== null);
```

### 3. Added Comprehensive Debug Logging
Added debug logging throughout the forecast extraction and statistics calculation pipeline to aid future troubleshooting:
- LSTM prediction extraction (lines 161-171, 176-186)
- Moving average fallback triggers (lines 197-201, 214-218)
- Prediction array extraction in buildStats() (lines 547-556)

## Files Modified

### app/Livewire/Pages/Manager/OccupancyForecasting.php
- **Lines 154-221**: Modified render() method to add LSTM fallback logic
- **Lines 533-590**: Modified buildStats() method to filter null predictions and add debug logging

### app/Console/Commands/TestForecastStats.php (NEW)
- Created diagnostic command to test forecast calculation pipeline
- Usage: `php artisan test:forecast-stats`

## Behavior Changes

### Before Fix
- LSTM available + insufficient data → Cards show "—"
- LSTM unavailable → Cards show moving average values (correct)

### After Fix  
- LSTM available + insufficient data → Falls back to moving average, cards show values
- LSTM available + sufficient data → Cards show LSTM predictions (unchanged)
- LSTM unavailable → Cards show moving average values (unchanged)
- Malformed prediction data → Null values filtered out, remaining valid predictions averaged

## Backwards Compatibility
✅ All existing functionality preserved:
- UI layout unchanged
- Blade templates unchanged
- Business logic unchanged
- API contracts unchanged
- Prediction algorithms unchanged
- Existing behavior for sufficient data unchanged

## Testing Recommendations

1. **Test with insufficient historical data**:
   - Upload CSV with < 45 rows
   - Verify cards show moving average forecasts instead of "—"

2. **Test with sufficient data**:
   - Use default CSV (> 45 rows)
   - Verify cards show LSTM predictions

3. **Test LSTM unavailable**:
   - Stop LSTM service
   - Verify cards show moving average forecasts

4. **Test forecast type switching**:
   - Switch between "Rooms", "Vehicles", and "Both"
   - Verify appropriate cards update

5. **Test forecast period changes**:
   - Switch between 7, 14, and 21 days
   - Verify totals and averages update correctly

6. **Test CSV upload**:
   - Upload new CSV
   - Verify forecasts regenerate and cards update

## Debug Mode
Enable debug logging in `.env`:
```
APP_DEBUG=true
```

Then monitor `storage/logs/laravel.log` for detailed prediction pipeline traces.

## Key Insight
The fix ensures **graceful degradation**: when the advanced LSTM model can't generate predictions (due to insufficient data), the system automatically falls back to the simpler but reliable moving average model, ensuring users always see meaningful forecast data.
