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
    public string $trainingSource = 'csv_server';
    public $uploadedCsv = null;

    public ?string $uploadedCsvPath = null;
    public ?string $uploadedCsvName = null;
    public ?string $uploadError = null;
    public ?string $uploadSuccess = null;

    public ?array $csvInfo = null;

    public bool $isRetraining = false;

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

    public function setForecastDays(int $days): void
    {
        $this->forecastDays = $days;
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

            $reader = new CsvDataReader();

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
            $csvReader       = new CsvDataReader();
            $this->csvInfo   = $csvReader->serverCsvInfo();  
            $timeSeries      = $this->buildTimeSeries($csvReader);

            $result = null;

            if ($isLSTMAvailable && !empty($timeSeries)) {
                $result = $this->forecastDays === 21
                    ? $lstmClient->predict3Weeks($timeSeries, false)
                    : $lstmClient->predict($timeSeries, $this->forecastDays, false);
            }

            if (!$result || empty($result['predictions'])) {
                $fallback = $lstmClient->predictWithFallback($timeSeries, $this->forecastDays);
                $result   = array_merge($fallback, [
                    'data_source'    => 'statistical',
                    'title'          => 'Visitor Traffic Predictions',
                    'description'    => null,
                    'weekly_summary' => $this->buildWeeklySummary($fallback['predictions'] ?? []),
                ]);
            }

            $rawPredictions = $result['predictions'];
            $predictions    = [];
            $confidenceSum  = 0.0;

            foreach ($rawPredictions as $p) {
                $p['predicted']    = round($p['predicted'], 1);
                $p['lower_bound']  = round($p['lower_bound'], 1);
                $p['upper_bound']  = round($p['upper_bound'], 1);
                $p['day_name']     = \Carbon\Carbon::parse($p['date'])->isoFormat('dddd');
                $confidenceSum    += $p['confidence'];
                $predictions[]     = $p;
            }

            $predCount = count($predictions);

            $weeklyData = null;
            if (!empty($result['weekly_summary'])) {
                $weeklyData = [
                    'labels'   => array_map(fn($w) => __('app.week_label') . ' ' . $w['week'], $result['weekly_summary']),
                    'totals'   => array_map(fn($w) => round($w['total_predicted'], 0), $result['weekly_summary']),
                    'averages' => array_map(fn($w) => round($w['avg_predicted'], 1), $result['weekly_summary']),
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
        $reader = $reader ?? new CsvDataReader();

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
