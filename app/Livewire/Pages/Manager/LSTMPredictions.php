<?php

namespace App\Livewire\Pages\Manager;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Services\AI\LSTMClient;
use App\Services\AI\DataPreprocessor;
use App\Services\AI\CsvDataReader;

#[Layout('layouts.manager')]
#[Title('LSTM Predictions')]
class LSTMPredictions extends Component
{
    use WithFileUploads;

    public int $forecastDays = 21;
    public ?string $forecastStartDate = null;
    public ?string $forecastEndDate = null;
    public string $trainingSource = 'csv_server';
    public $uploadedCsv = null;

    public ?string $uploadedCsvPath = null;
    public ?string $uploadedCsvName = null;
    public ?string $uploadError = null;
    public ?string $uploadSuccess = null;

    public ?array $csvInfo = null;

    public bool $isRetraining = false;

    /** Reused across render() → buildTimeSeries() within the same request. Not serialized by Livewire. */
    private CsvDataReader $csvReader;

    protected function rules(): array
    {
        return [
            'uploadedCsv' => [
                'required',
                'file',
                'mimes:csv,txt',
                'max:10240', // 10 MB
            ],
        ];
    }

    public function mount(): void
    {
        $this->initializeForecastDates();
    }

    public function updated($propertyName): void
    {
        if ($propertyName === 'forecastStartDate' || $propertyName === 'forecastEndDate') {
            $this->syncForecastDaysFromDates();
        }
    }

    private function initializeForecastDates(): void
    {
        if (!$this->forecastStartDate || !$this->forecastEndDate) {
            $this->forecastStartDate = now()->addDay()->format('Y-m-d');
            $this->forecastEndDate = now()->addDays($this->forecastDays)->format('Y-m-d');
        }
    }

    private function syncForecastDaysFromDates(): void
    {
        if ($this->forecastStartDate && $this->forecastEndDate) {
            $start = \Carbon\Carbon::parse($this->forecastStartDate);
            $end = \Carbon\Carbon::parse($this->forecastEndDate);
            $this->forecastDays = max(1, $start->diffInDays($end));
        }
    }

    public function setForecastDays(int $days): void
    {
        $this->forecastDays = $days;
        $this->forecastStartDate = now()->addDay()->format('Y-m-d');
        $this->forecastEndDate = now()->addDays($days)->format('Y-m-d');
    }

    public function setTrainingSource(string $source): void
    {
        if (!in_array($source, ['csv_server', 'csv_upload', 'live_db'], true)) {
            return;
        }

        $this->trainingSource = $source;
        $this->uploadError    = null;
        $this->uploadSuccess  = null;
    }

    public function uploadCsv(): void
    {
        $this->uploadError   = null;
        $this->uploadSuccess = null;

        $this->validate($this->rules(), [
            'uploadedCsv.required' => __('app.csv_error_no_file'),
            'uploadedCsv.file'     => __('app.csv_error_not_file'),
            'uploadedCsv.mimes'    => __('app.csv_error_wrong_type'),
            'uploadedCsv.max'      => __('app.csv_error_too_large'),
        ]);

        try {
            $uploadDir = Storage::disk(CsvDataReader::DISK)->path(CsvDataReader::UPLOAD_PATH);
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $reader = $this->csvReader ?? new CsvDataReader();

            $tmpPath = $this->uploadedCsv->store(
                CsvDataReader::UPLOAD_PATH,
                CsvDataReader::DISK
            );

            if (!$tmpPath) {
                throw new \RuntimeException('File could not be stored. Check storage permissions.');
            }

            $missing = $reader->validateColumns($tmpPath);

            if (!empty($missing)) {
                Storage::disk(CsvDataReader::DISK)->delete($tmpPath);
                $this->uploadError = __('app.csv_missing_columns', [
                    'columns' => implode(', ', $missing),
                ]);
                $this->uploadedCsv = null;
                return;
            }

            if ($this->uploadedCsvPath) {
                Storage::disk(CsvDataReader::DISK)->delete($this->uploadedCsvPath);
            }

            $this->uploadedCsvPath = $tmpPath;
            $this->uploadedCsvName = $this->uploadedCsv->getClientOriginalName();
            $this->uploadedCsv     = null;
            $this->trainingSource  = 'csv_upload';
            $this->uploadSuccess   = __('app.csv_upload_success', [
                'name' => $this->uploadedCsvName,
            ]);

            Log::info('LSTMPredictions: CSV uploaded', [
                'path' => $tmpPath,
                'name' => $this->uploadedCsvName,
            ]);

        } catch (\Throwable $e) {
            Log::error('LSTMPredictions: CSV upload failed', ['error' => $e->getMessage()]);
            $this->uploadError = __('app.csv_upload_failed_detail', [
                'detail' => $e->getMessage(),
            ]);
            $this->uploadedCsv = null;
        }
    }

    public function retrain(): void
    {
        $this->isRetraining = true;

        try {
            $client     = app(LSTMClient::class);
            $timeSeries = $this->buildTimeSeries();

            if ($client->isAvailable() && !empty($timeSeries)) {
                $client->forceRetrain($timeSeries, $this->forecastDays);
            }
        } catch (\Throwable $e) {
            Log::error('LSTMPredictions: retrain failed', ['error' => $e->getMessage()]);
        }

        $this->isRetraining = false;
    }

    public function render()
    {
        try {
            $lstmClient      = app(LSTMClient::class);
            $isLSTMAvailable = $lstmClient->isAvailable();
            $csvReader       = $this->csvReader ?? new CsvDataReader();
            $timeSeries      = $this->buildTimeSeries($csvReader);

            // Derive csvInfo from the already-loaded timeSeries when using the server CSV,
            // so we don't call serverCsvInfo() (which re-reads the CSV) separately.
            if ($this->trainingSource === 'csv_server' || $this->trainingSource === 'csv_upload') {
                if (!empty($timeSeries)) {
                    $this->csvInfo = [
                        'rows'  => count($timeSeries),
                        'start' => $timeSeries[0]['date'],
                        'end'   => $timeSeries[count($timeSeries) - 1]['date'],
                    ];
                } else {
                    $this->csvInfo = ['rows' => 0, 'start' => null, 'end' => null];
                }
            } else {
                // live_db path: timeSeries came from DB, still need CSV info from the server file
                $this->csvInfo = $csvReader->serverCsvInfo();
            }

            $result = null;

            if ($isLSTMAvailable && !empty($timeSeries)) {
                $result = $this->forecastDays === 21
                    ? $lstmClient->predict3Weeks($timeSeries, false)
                    : $lstmClient->predict($timeSeries, $this->forecastDays, false);
            }

            $weeklySummarySource = 'fastapi';

            if (!$result || empty($result['predictions'])) {
                $weeklySummarySource = 'local_fallback';
                $fallback = $lstmClient->predictWithFallback($timeSeries, $this->forecastDays);
                $result   = array_merge($fallback, [
                    'data_source'    => 'statistical',
                    'title'          => 'Visitor Traffic Predictions',
                    'description'    => null,
                    'weekly_summary' => $this->buildWeeklySummary($fallback['predictions'] ?? []),
                ]);
            } elseif (empty($result['weekly_summary'])) {
                $weeklySummarySource = 'local_rebuild';
                $result['weekly_summary'] = $this->buildWeeklySummary($result['predictions']);
            }

            $rawPredictions  = $result['predictions'];
            $firstPredDate   = $rawPredictions[0]['date'] ?? 'unknown';
            $lastPredDate    = $rawPredictions[count($rawPredictions) - 1]['date'] ?? 'unknown';

            Log::info('LSTMPredictions: prediction dataset received', [
                'source'              => $result['data_source'] ?? 'unknown',
                'weekly_summary_from' => $weeklySummarySource,
                'total_predictions'   => count($rawPredictions),
                'first_prediction'    => $firstPredDate,
                'last_prediction'     => $lastPredDate,
                'forecast_days'       => $this->forecastDays,
                'training_source'     => $this->trainingSource,
            ]);

            $predictions   = [];
            $confidenceSum = 0.0;

            foreach ($rawPredictions as $p) {
                $p['predicted']    = round($p['predicted'], 1);
                $p['lower_bound']  = round($p['lower_bound'], 1);
                $p['upper_bound']  = round($p['upper_bound'], 1);
                $p['day_name']     = \Carbon\Carbon::parse($p['date'])->isoFormat('dddd');
                $confidenceSum    += $p['confidence'];
                $predictions[]     = $p;
            }

            $predCount = count($predictions);

            $firstTableDate = $predictions[0]['date'] ?? 'unknown';
            $lastTableDate  = $predictions[$predCount - 1]['date'] ?? 'unknown';

            $weeklyData = null;
            if (!empty($result['weekly_summary'])) {
                $weeklySummary = $result['weekly_summary'];

                $firstWeekStart = $weeklySummary[0]['start_date'] ?? 'unknown';
                $lastWeekEnd    = $weeklySummary[count($weeklySummary) - 1]['end_date'] ?? 'unknown';

                Log::info('LSTMPredictions: weekly summary alignment check', [
                    'weekly_summary_source' => $weeklySummarySource,
                    'week_count'            => count($weeklySummary),
                    'first_week_start'      => $firstWeekStart,
                    'last_week_end'         => $lastWeekEnd,
                    'first_table_date'      => $firstTableDate,
                    'last_table_date'       => $lastTableDate,
                    'dates_aligned'         => ($firstWeekStart === $firstTableDate),
                ]);

                $weeklyData = [
                    'labels'   => array_map(fn($w) => __('app.week_label') . ' ' . $w['week'], $weeklySummary),
                    'totals'   => array_map(fn($w) => round($w['total_predicted'], 0), $weeklySummary),
                    'averages' => array_map(fn($w) => round($w['avg_predicted'], 1), $weeklySummary),
                ];
            }

            $dailyPredictedValues = array_column($predictions, 'predicted');
            $totalPredicted = array_sum($dailyPredictedValues);
            $avgDaily       = $totalPredicted / max(1, $predCount);
            $avgConfidence  = $predCount > 0 ? $confidenceSum / $predCount : 0;
            $maxDay         = !empty($dailyPredictedValues) ? max($dailyPredictedValues) : 0;

            $stats = [
                ['label' => __('app.total_predicted'), 'value' => number_format($totalPredicted, 0), 'color' => 'blue',   'icon' => 'chart-bar'],
                ['label' => __('app.avg_per_day'),      'value' => number_format($avgDaily, 1),        'color' => 'green',  'icon' => 'calculator'],
                ['label' => __('app.peak_day'),         'value' => number_format($maxDay, 0),           'color' => 'yellow', 'icon' => 'arrow-trending-up'],
                ['label' => __('app.confidence'),       'value' => number_format($avgConfidence * 100, 1) . '%', 'color' => 'purple', 'icon' => 'check-badge'],
            ];

            // ── Single getModelMetrics() call per render ──────────────────
            $modelMetrics = $isLSTMAvailable ? $lstmClient->getModelMetrics() : null;

            Log::info('LSTMPredictions: metrics pipeline trace', [
                'lstm_available'       => $isLSTMAvailable,
                'model_metrics_null'   => is_null($modelMetrics),
                'metrics_available'    => $modelMetrics['available'] ?? false,
                'metrics_trained_at'   => $modelMetrics['trained_at'] ?? 'null',
                'metrics_mae'          => $modelMetrics['mae'] ?? 'null',
                'metrics_rmse'         => $modelMetrics['rmse'] ?? 'null',
                'metrics_epochs'       => $modelMetrics['epochs_run'] ?? 'null',
                'first_table_date'     => $firstTableDate,
                'last_table_date'      => $lastTableDate,
                'daily_chart_labels_from' => 'prediction.date via JS split',
            ]);

            return view('livewire.pages.manager.lstm-predictions', [
                'isLSTMAvailable' => $isLSTMAvailable,
                'predictions'     => $predictions,
                'weeklyData'      => $weeklyData,
                'stats'           => $stats,
                'rmse'            => $result['rmse'] ?? ($result['metrics']['rmse'] ?? 0),
                'activeSource'    => $result['data_source'] ?? 'statistical',
                'title'           => $result['title'] ?? 'Visitor Traffic Predictions',
                'description'     => $result['description'] ?? null,
                'csvInfo'         => $this->csvInfo,
                'modelMetrics'    => $modelMetrics,
            ]);

        } catch (\Exception $e) {
            Log::error('LSTMPredictions render failed', ['error' => $e->getMessage()]);

            return view('livewire.pages.manager.lstm-predictions', [
                'isLSTMAvailable' => false,
                'predictions'     => [],
                'weeklyData'      => null,
                'stats'           => $this->getEmptyStats(),
                'rmse'            => 0,
                'activeSource'    => 'error',
                'title'           => 'Visitor Traffic Predictions',
                'description'     => null,
                'csvInfo'         => $this->csvInfo,
                'modelMetrics'    => null,
            ]);
        }
    }

    private function buildTimeSeries(?CsvDataReader $reader = null): array
    {
        $reader = $reader ?? ($this->csvReader ?? new CsvDataReader());

        switch ($this->trainingSource) {

            case 'csv_upload':
                if ($this->uploadedCsvPath) {
                    try {
                        return $reader->readUploadedCsv($this->uploadedCsvPath, 'visitors');
                    } catch (\Throwable $e) {
                        Log::warning('LSTMPredictions: uploaded CSV unreadable, falling back to server CSV', [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $this->trainingSource = 'csv_server';
                return $reader->readServerCsv('visitors');

            case 'live_db':
                $companyId    = Auth::user()->company_id;
                $preprocessor = new DataPreprocessor();
                return $preprocessor->createTimeSeriesDataset('guestbook', $companyId);

            case 'csv_server':
            default:
                return $reader->readServerCsv('visitors');
        }
    }

    private function buildWeeklySummary(array $predictions): array
    {
        if (count($predictions) < 7) return [];

        $summary = [];
        foreach (array_chunk($predictions, 7) as $i => $week) {
            $total = array_sum(array_column($week, 'predicted'));
            $summary[] = [
                'week'            => $i + 1,
                'start_date'      => $week[0]['date'],
                'end_date'        => end($week)['date'],
                'total_predicted' => round($total, 2),
                'avg_predicted'   => round($total / count($week), 2),
            ];
        }
        return $summary;
    }

    private function getEmptyStats(): array
    {
        return [
            ['label' => __('app.total_predicted'), 'value' => '0',  'color' => 'blue',   'icon' => 'chart-bar'],
            ['label' => __('app.avg_per_day'),      'value' => '0',  'color' => 'green',  'icon' => 'calculator'],
            ['label' => __('app.peak_day'),         'value' => '0',  'color' => 'yellow', 'icon' => 'arrow-trending-up'],
            ['label' => __('app.confidence'),       'value' => '0%', 'color' => 'purple', 'icon' => 'check-badge'],
        ];
    }
}
