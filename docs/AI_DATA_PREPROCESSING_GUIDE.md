# AI Data Preprocessing Guide

## Overview

This guide explains how to preprocess database data for AI predictive analysis in the KRB System. The preprocessing pipeline transforms raw database records into structured features suitable for machine learning models.

## Architecture

```
Database → DataPreprocessor → Features → PredictionService → Insights
```

### Components

1. **DataPreprocessor** (`app/Services/AI/DataPreprocessor.php`)
   - Extracts and transforms raw data
   - Handles missing values
   - Creates feature vectors
   - Normalizes data

2. **PredictionService** (`app/Services/AI/PredictionService.php`)
   - Uses preprocessed features
   - Makes predictions
   - Detects anomalies
   - Generates recommendations

3. **DecisionEngine** (`app/Services/AI/DecisionEngine.php`)
   - Evaluates booking requests
   - Calculates risk scores
   - Provides decision support

## Usage Examples

### 1. Preprocess Room Booking Data

```php
use App\Services\AI\DataPreprocessor;

$preprocessor = new DataPreprocessor();
$companyId = Auth::user()->company_id;

// Get preprocessed features for last 90 days
$features = $preprocessor->preprocessRoomBookings($companyId, 90);

// Access specific features
$peakHours = $features['peak_hours']; // [9, 14, 16]
$approvalRate = $features['approval_rate']; // 85.5
$hourlyDistribution = $features['hourly_distribution']; // [0, 0, 0, ..., 15, 20, ...]
```

### 2. Predict Future Demand

```php
use App\Services\AI\PredictionService;

$predictionService = new PredictionService();
$companyId = Auth::user()->company_id;

// Predict room booking demand for next 7 days
$forecast = $predictionService->predictBookingDemand('room_booking', $companyId, 7);

foreach ($forecast as $day) {
    echo "Date: {$day['date']}\n";
    echo "Predicted bookings: {$day['predicted_count']}\n";
    echo "Confidence: {$day['confidence']}\n";
}
```

### 3. Detect Anomalies

```php
use App\Services\AI\PredictionService;

$predictionService = new PredictionService();
$companyId = Auth::user()->company_id;

// Detect anomalies in vehicle bookings
$anomalies = $predictionService->detectAnomalies('vehicle_booking', $companyId);

foreach ($anomalies as $anomaly) {
    echo "Type: {$anomaly['type']}\n";
    echo "Severity: {$anomaly['severity']}\n";
    echo "Message: {$anomaly['message']}\n";
    echo "Recommendation: {$anomaly['recommendation']}\n";
}
```

### 4. Calculate Conflict Probability

```php
use App\Services\AI\PredictionService;

$predictionService = new PredictionService();
$companyId = Auth::user()->company_id;

$bookingData = [
    'start_time' => '2026-04-05 14:00:00',
    'duration_hours' => 2,
    'room_id' => 5,
];

$result = $predictionService->calculateConflictProbability($bookingData, $companyId);

echo "Conflict probability: {$result['probability']}%\n";
echo "Risk level: {$result['risk_level']}\n";
echo "Recommendation: {$result['recommendation']}\n";
```

### 5. Generate Optimization Recommendations

```php
use App\Services\AI\PredictionService;

$predictionService = new PredictionService();
$companyId = Auth::user()->company_id;

$recommendations = $predictionService->generateOptimizationRecommendations($companyId);

foreach ($recommendations as $rec) {
    echo "Category: {$rec['category']}\n";
    echo "Priority: {$rec['priority']}\n";
    echo "Title: {$rec['title']}\n";
    echo "Description: {$rec['description']}\n";
    echo "Impact: {$rec['impact']}\n\n";
}
```

### 6. Create Time Series Dataset

```php
use App\Services\AI\DataPreprocessor;

$preprocessor = new DataPreprocessor();
$companyId = Auth::user()->company_id;

// Get 180 days of time series data
$timeSeries = $preprocessor->createTimeSeriesDataset('guestbook', $companyId, 180);

foreach ($timeSeries as $dataPoint) {
    echo "Date: {$dataPoint['date']}\n";
    echo "Count: {$dataPoint['count']}\n";
    echo "Day of week: {$dataPoint['day_of_week']}\n";
    echo "Is weekend: " . ($dataPoint['is_weekend'] ? 'Yes' : 'No') . "\n";
}
```

## Feature Descriptions

### Room Booking Features

| Feature | Type | Description |
|---------|------|-------------|
| `hourly_distribution` | array[24] | Number of bookings per hour (0-23) |
| `daily_distribution` | array[7] | Number of bookings per day of week (0=Sunday) |
| `weekly_trend` | array | Bookings per week number |
| `avg_duration` | float | Average booking duration in hours |
| `peak_hours` | array | Top 3 busiest hours |
| `booking_frequency` | float | Average bookings per day |
| `status_distribution` | array | Count by status (pending, approved, rejected) |
| `approval_rate` | float | Percentage of approved bookings |
| `cancellation_rate` | float | Percentage of cancelled/rejected bookings |
| `room_popularity` | array | Booking count per room (top 10) |
| `department_usage` | array | Booking count per department |
| `repeat_users` | array | Count and percentage of repeat users |
| `booking_lead_time` | float | Average hours between booking and start time |
| `unusual_patterns` | array | Detected anomalies with severity |

### Vehicle Booking Features

| Feature | Type | Description |
|---------|------|-------------|
| `hourly_distribution` | array[24] | Number of bookings per hour |
| `daily_distribution` | array[7] | Number of bookings per day of week |
| `weekly_trend` | array | Bookings per week number |
| `avg_duration` | float | Average trip duration in hours |
| `destination_frequency` | array | Top 10 destinations |
| `booking_frequency` | float | Average bookings per day |
| `status_distribution` | array | Count by status |
| `completion_rate` | float | Percentage of completed trips |
| `vehicle_popularity` | array | Booking count per vehicle (top 10) |
| `department_usage` | array | Booking count per department |
| `repeat_users` | array | Count and percentage of repeat users |
| `booking_lead_time` | float | Average hours between booking and departure |

### Guestbook Features

| Feature | Type | Description |
|---------|------|-------------|
| `hourly_distribution` | array[24] | Number of visitors per hour |
| `daily_distribution` | array[7] | Number of visitors per day of week |
| `weekly_trend` | array | Visitors per week number |
| `avg_visit_duration` | float | Average visit duration in minutes |
| `visitor_frequency` | float | Average visitors per day |
| `peak_hours` | array | Top 3 busiest hours |
| `institution_distribution` | array | Top 10 visiting institutions |
| `repeat_institutions` | array | Institutions with multiple visits |
| `purpose_categories` | array | Visits categorized by purpose |

### Delivery Features

| Feature | Type | Description |
|---------|------|-------------|
| `hourly_distribution` | array[24] | Number of deliveries per hour |
| `daily_distribution` | array[7] | Number of deliveries per day of week |
| `weekly_trend` | array | Deliveries per week number |
| `delivery_frequency` | float | Average deliveries per day |
| `type_distribution` | array | Count by type (package, document) |
| `direction_distribution` | array | Count by direction (taken, deliver) |
| `status_distribution` | array | Count by status |
| `completion_rate` | float | Percentage of completed deliveries |
| `avg_processing_time` | float | Average hours to complete |
| `storage_utilization` | array | Usage count per storage location |

## Data Quality Considerations

### Handling Missing Data

The preprocessor automatically handles missing data:

1. **Missing timestamps**: Excluded from temporal analysis
2. **Missing durations**: Excluded from duration calculations
3. **Empty collections**: Returns zero-filled feature arrays
4. **Invalid values**: Filtered out with validation

### Data Validation

```php
// Example: Validate visit duration
if ($visitor->jam_in && $visitor->jam_out) {
    $duration = Carbon::parse($visitor->jam_in)
        ->diffInMinutes(Carbon::parse($visitor->jam_out));
    
    // Only include reasonable durations (< 24 hours)
    if ($duration > 0 && $duration < 1440) {
        $durations[] = $duration;
    }
}
```

## Integration with Livewire Components

### Example: AI Security Reports

```php
// app/Livewire/Pages/Manager/AISecurityReports.php

use App\Services\AI\PredictionService;

public function render()
{
    $companyId = Auth::user()->company_id;
    $predictionService = new PredictionService();

    // Get real-time anomaly detection
    $roomAnomalies = $predictionService->detectAnomalies('room_booking', $companyId);
    $vehicleAnomalies = $predictionService->detectAnomalies('vehicle_booking', $companyId);
    
    // Format for display
    $alerts = [];
    foreach ($roomAnomalies as $anomaly) {
        $alerts[] = [
            'severity' => $anomaly['severity'],
            'message' => '[Room Bookings] ' . $anomaly['message'],
            'recommendation' => $anomaly['recommendation'],
        ];
    }
    
    return view('livewire.pages.manager.a-i-security-reports', [
        'alerts' => $alerts,
    ]);
}
```

## Performance Optimization

### Caching Preprocessed Features

```php
use Illuminate\Support\Facades\Cache;

$cacheKey = "room_features_{$companyId}";

$features = Cache::remember($cacheKey, 3600, function() use ($preprocessor, $companyId) {
    return $preprocessor->preprocessRoomBookings($companyId);
});
```

### Limiting Data Range

```php
// Process only recent data for faster results
$features = $preprocessor->preprocessRoomBookings($companyId, 30); // Last 30 days
```

### Batch Processing

```php
// Process multiple types in parallel
$features = [
    'rooms' => $preprocessor->preprocessRoomBookings($companyId),
    'vehicles' => $preprocessor->preprocessVehicleBookings($companyId),
    'visitors' => $preprocessor->preprocessGuestbookData($companyId),
    'deliveries' => $preprocessor->preprocessDeliveryData($companyId),
];
```

## Advanced Use Cases

### 1. Custom Feature Engineering

```php
// Extend DataPreprocessor for custom features
class CustomPreprocessor extends DataPreprocessor
{
    public function extractCustomFeature($bookings)
    {
        // Your custom logic here
        return $customFeature;
    }
}
```

### 2. Export for External ML Tools

```php
// Export to CSV for Python/R analysis
$timeSeries = $preprocessor->createTimeSeriesDataset('room_booking', $companyId, 365);

$csv = fopen('room_bookings_timeseries.csv', 'w');
fputcsv($csv, ['date', 'count', 'day_of_week', 'is_weekend', 'month']);

foreach ($timeSeries as $row) {
    fputcsv($csv, $row);
}

fclose($csv);
```

### 3. Real-time Monitoring

```php
// Set up scheduled task for continuous monitoring
// app/Console/Kernel.php

protected function schedule(Schedule $schedule)
{
    $schedule->call(function () {
        $companies = Company::all();
        
        foreach ($companies as $company) {
            $predictionService = new PredictionService();
            $anomalies = $predictionService->detectAnomalies('room_booking', $company->company_id);
            
            if (!empty($anomalies)) {
                // Send notification to admins
                Notification::send($company->admins, new AnomalyDetectedNotification($anomalies));
            }
        }
    })->hourly();
}
```

## Troubleshooting

### Issue: Empty Features Returned

**Cause**: No data in database for specified time range

**Solution**: 
```php
// Check if data exists
$count = BookingRoom::where('company_id', $companyId)
    ->where('created_at', '>=', now()->subDays(90))
    ->count();

if ($count === 0) {
    // Use dummy data or extend time range
    $features = $preprocessor->preprocessRoomBookings($companyId, 180);
}
```

### Issue: Slow Performance

**Cause**: Processing large datasets

**Solution**:
```php
// Use pagination or chunking
BookingRoom::where('company_id', $companyId)
    ->where('created_at', '>=', now()->subDays(90))
    ->chunk(1000, function($bookings) {
        // Process in batches
    });
```

### Issue: Inaccurate Predictions

**Cause**: Insufficient historical data

**Solution**:
- Collect at least 30 days of data for basic predictions
- 90+ days recommended for accurate forecasting
- Consider seasonal patterns (need 1+ year for annual trends)

## Next Steps

1. **Implement Machine Learning Models**: Use preprocessed features to train ML models (e.g., ARIMA, Prophet, LSTM)
2. **Add More Features**: Extend preprocessor with domain-specific features
3. **Integrate External APIs**: Combine with weather, holidays, or event data
4. **Build Dashboards**: Visualize predictions and insights in real-time
5. **Automate Actions**: Trigger automated responses based on predictions

## References

- Laravel Documentation: https://laravel.com/docs
- Carbon (Date/Time): https://carbon.nesbot.com/docs/
- Time Series Analysis: https://otexts.com/fpp3/
- Anomaly Detection: https://en.wikipedia.org/wiki/Anomaly_detection
