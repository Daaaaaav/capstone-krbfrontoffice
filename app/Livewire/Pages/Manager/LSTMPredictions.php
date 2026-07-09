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

    // ── Forecast controls ─────────────────────────────────────────────────────
    public int $forecastDays = 21;

    /**
     * Active training-data source:
     *   'csv_server'  – bundled historical CSV (default)
     *   'csv_upload'  – user-uploaded CSV
     *   'live_db'     – live guestbook records from the database
     */
    public string $trainingSource = 'csv_server';

    // ── Upload state ──────────────────────────────────────────────────────────
    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $uploadedCsv = null;

    /** Stored path of the validated upload (relative to the private disk). */
    public ?string $uploadedCsvPath = null;

    /** Human-readable name of the last accepted upload. */
    public ?string $uploadedCsvName = null;

    /** Validation / upload error message shown to the user. */
    public ?string $uploadError = null;

    /** Success message shown after a successful upload. */
    public ?string $uploadSuccess = null;

    // ── Status flags ──────────────────────────────────────────────────────────
    public bool $isRetraining = false;

    // ── Validation ────────────────────────────────────────────────────────────
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

    // ── Actions ───────────────────────────────────────────────────────────────

    public function setForecastDays(int $days): void
    {
        $this->forecastDays = $days;
    }

    public function setTrainingSource(string $source): void
    {
        if (!in_array($source, ['csv_server', 'csv_upload', 'live_db'], true)) {
            return;
        }

        // Switching away from upload doesn't delete the stored file — the user
        // can switch back to it without re-uploading.
        $this->trainingSource = $source;
        $this->uploadError    = null;
        $this->uploadSuccess  = null;
    }

    /**
     * Handle CSV upload, validate columns, store the file, and switch the
     * training source to 'csv_upload' automatically on success.
     */
    public function uploadCsv(): void
    {
        $this->uploadError   = null;
        $this->uploadSuccess = null;

        $this->validate();

        try {
            $reader = new CsvDataReader();

            // Store to a temp path first so we can inspect the headers
            $tmpPath = $this->uploadedCsv->store(
                CsvDataReader::UPLOAD_PATH,
                CsvDataReader::DISK
            );

            $missing = $reader->validateColumns($tmpPath);

            if (!empty($missing)) {
                // Remove invalid file and report the problem
                Storage::disk(CsvDataReader::DISK)->delete($tmpPath);
                $this->uploadError = __('app.csv_missing_columns', [
                    'columns' => implode(', ', $missing),
                ]);
                $this->uploadedCsv = null;
                return;
            }

            // Delete any previously uploaded file
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
            $this->uploadError = __('app.csv_upload_failed');
            $this->uploadedCsv = null;
        }
    }

    /**
     * Force a full model retrain using the current training source.
     * The LSTM service's fingerprint cache is bypassed.
     */
    public function retrain(): void
    {
        $this->isRetraining = true;

        try {
            $timeSeries = $this->buildTimeSeries();
            $client     = new LSTMClient();

            if ($client->isAvailable() && !empty($timeSeries)) {
                // POST to /retrain which sets force_retrain=true server-side
                $client->forceRetrain($timeSeries, $this->forecastDays);
            }
        } catch (\Throwable $e) {
            Log::error('LSTMPredictions: retrain failed', ['error' => $e->getMessage()]);
        }

        $this->isRetraining = false;
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public function render()
    {
        try {
            $lstmClient      = new LSTMClient();
            $isLSTMAvailable = $lstmClient->isAvailable();

            $timeSeries = $this->buildTimeSeries();

            // ── Get predictions ───────────────────────────────────────────────
            $result = null;

            if ($isLSTMAvailable && !empty($timeSeries)) {
                $result = $this->forecastDays === 21
                    ? $lstmClient->predict3Weeks($timeSeries, false)
                    : $lstmClient->predict($timeSeries, $this->forecastDays, false);
            }

            // ── Fallback to statistical model ─────────────────────────────────
            if (!$result || empty($result['predictions'])) {
                $fallback = $lstmClient->predictWithFallback($timeSeries, $this->forecastDays);
                $result   = array_merge($fallback, [
                    'data_source'    => 'statistical',
                    'title'          => 'Visitor Traffic Predictions',
                    'description'    => null,
                    'weekly_summary' => $this->buildWeeklySummary($fallback['predictions'] ?? []),
                ]);
            }

            // ── Build chart arrays ────────────────────────────────────────────
            $predictions     = $result['predictions'];
            $dailyLabels     = array_map(fn($p) => date('d/m', strtotime($p['date'])), $predictions);
            $dailyPredicted  = array_map(fn($p) => round($p['predicted'], 1), $predictions);
            $dailyLowerBound = array_map(fn($p) => round($p['lower_bound'], 1), $predictions);
            $dailyUpperBound = array_map(fn($p) => round($p['upper_bound'], 1), $predictions);

            // ── Weekly summary ────────────────────────────────────────────────
            $weeklyData = null;
            if (!empty($result['weekly_summary'])) {
                $weeklyData = [
                    'labels'   => array_map(fn($w) => __('app.week_label') . ' ' . $w['week'], $result['weekly_summary']),
                    'totals'   => array_map(fn($w) => round($w['total_predicted'], 0), $result['weekly_summary']),
                    'averages' => array_map(fn($w) => round($w['avg_predicted'], 1), $result['weekly_summary']),
                ];
            }

            // ── Stats cards ───────────────────────────────────────────────────
            $totalPredicted = array_sum($dailyPredicted);
            $avgDaily       = $totalPredicted / max(1, count($dailyPredicted));
            $avgConfidence  = array_sum(array_column($predictions, 'confidence')) / max(1, count($predictions));
            $maxDay         = !empty($dailyPredicted) ? max($dailyPredicted) : 0;

            $stats = [
                ['label' => __('app.total_predicted'), 'value' => number_format($totalPredicted, 0), 'color' => 'blue',   'icon' => 'chart-bar'],
                ['label' => __('app.avg_per_day'),      'value' => number_format($avgDaily, 1),        'color' => 'green',  'icon' => 'calculator'],
                ['label' => __('app.peak_day'),         'value' => number_format($maxDay, 0),           'color' => 'yellow', 'icon' => 'arrow-trending-up'],
                ['label' => __('app.confidence'),       'value' => number_format($avgConfidence * 100, 1) . '%', 'color' => 'purple', 'icon' => 'check-badge'],
            ];

            // ── CSV server metadata (shown in the UI) ─────────────────────────
            $csvReader  = new CsvDataReader();
            $csvInfo    = $csvReader->serverCsvInfo();

            return view('livewire.pages.manager.lstm-predictions', [
                'isLSTMAvailable' => $isLSTMAvailable,
                'predictions'     => $predictions,
                'weeklyData'      => $weeklyData,
                'dailyLabels'     => $dailyLabels,
                'dailyPredicted'  => $dailyPredicted,
                'dailyLowerBound' => $dailyLowerBound,
                'dailyUpperBound' => $dailyUpperBound,
                'stats'           => $stats,
                'rmse'            => $result['rmse'] ?? ($result['metrics']['rmse'] ?? 0),
                'activeSource'    => $result['data_source'] ?? 'statistical',
                'title'           => $result['title'] ?? 'Visitor Traffic Predictions',
                'description'     => $result['description'] ?? null,
                'csvInfo'         => $csvInfo,
            ]);

        } catch (\Exception $e) {
            Log::error('LSTMPredictions render failed', ['error' => $e->getMessage()]);

            return view('livewire.pages.manager.lstm-predictions', [
                'isLSTMAvailable' => false,
                'predictions'     => [],
                'weeklyData'      => null,
                'dailyLabels'     => [],
                'dailyPredicted'  => [],
                'dailyLowerBound' => [],
                'dailyUpperBound' => [],
                'stats'           => $this->getEmptyStats(),
                'rmse'            => 0,
                'activeSource'    => 'error',
                'title'           => 'Visitor Traffic Predictions',
                'description'     => null,
                'csvInfo'         => ['rows' => 0, 'start' => null, 'end' => null],
            ]);
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Build the time-series array from whichever source is currently active.
     * Falls back to the server CSV when the upload path is missing.
     */
    private function buildTimeSeries(): array
    {
        $reader = new CsvDataReader();

        switch ($this->trainingSource) {

            case 'csv_upload':
                if ($this->uploadedCsvPath) {
                    try {
                        return $reader->readUploadedCsv($this->uploadedCsvPath, 'visitors');
                    } catch (\Throwable $e) {
                        Log::warning('LSTMPredictions: uploaded CSV unreadable, falling back to server CSV', [
                            'error' => $e->getMessage(),
                        ]);
                        // Fall through to server CSV
                    }
                }
                // No valid upload → fall through to server CSV
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
