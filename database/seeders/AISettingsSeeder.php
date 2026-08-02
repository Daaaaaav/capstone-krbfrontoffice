<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AISettings;

class AISettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key'         => 'lstm_units',
                'value'       => '64',
                'type'        => 'int',
                'group'       => 'lstm',
                'label'       => 'LSTM Units',
                'description' => 'Number of units in the LSTM layer. Higher = more capacity but slower training.',
            ],
            [
                'key'         => 'dropout_rate',
                'value'       => '0.1',
                'type'        => 'float',
                'group'       => 'lstm',
                'label'       => 'Dropout Rate',
                'description' => 'Fraction of units randomly dropped during training (0.0–0.5). Prevents overfitting.',
            ],
            [
                'key'         => 'l2_regularization',
                'value'       => '0.00001',
                'type'        => 'float',
                'group'       => 'lstm',
                'label'       => 'L2 Regularization',
                'description' => 'L2 kernel/recurrent regularizer strength. Penalises large weights.',
            ],
            [
                'key'         => 'sequence_window',
                'value'       => '7',
                'type'        => 'int',
                'group'       => 'lstm',
                'label'       => 'Sequence Window (days)',
                'description' => 'Number of past days used as input to predict the next day.',
            ],
            [
                'key'         => 'epochs',
                'value'       => '150',
                'type'        => 'int',
                'group'       => 'lstm',
                'label'       => 'Max Training Epochs',
                'description' => 'Maximum number of training iterations. Early stopping may stop before this.',
            ],
            [
                'key'         => 'batch_size',
                'value'       => '16',
                'type'        => 'int',
                'group'       => 'lstm',
                'label'       => 'Batch Size',
                'description' => 'Number of samples processed per gradient update. Smaller = slower but often better.',
            ],
            [
                'key'         => 'validation_split',
                'value'       => '0.15',
                'type'        => 'float',
                'group'       => 'lstm',
                'label'       => 'Validation Split',
                'description' => 'Fraction of training data held out to monitor overfitting (0.05–0.30).',
            ],
            [
                'key'         => 'early_stop_patience',
                'value'       => '10',
                'type'        => 'int',
                'group'       => 'lstm',
                'label'       => 'Early Stop Patience',
                'description' => 'Epochs with no improvement before training stops early.',
            ],
            [
                'key'         => 'min_data_points',
                'value'       => '45',
                'type'        => 'int',
                'group'       => 'lstm',
                'label'       => 'Minimum Data Points',
                'description' => 'Minimum historical records required to run LSTM training.',
            ],
            [
                'key'         => 'history_days',
                'value'       => '730',
                'type'        => 'int',
                'group'       => 'lstm',
                'label'       => 'History Lookback (days)',
                'description' => 'How many days of historical data to include when building the training dataset.',
            ],
            [
                'key'         => 'confidence_min',
                'value'       => '0.30',
                'type'        => 'float',
                'group'       => 'lstm',
                'label'       => 'Confidence Score Floor',
                'description' => 'Minimum allowed confidence score returned to the UI (0.0–1.0).',
            ],
            [
                'key'         => 'confidence_max',
                'value'       => '0.92',
                'type'        => 'float',
                'group'       => 'lstm',
                'label'       => 'Confidence Score Ceiling',
                'description' => 'Maximum allowed confidence score returned to the UI (0.0–1.0).',
            ],

            [
                'key'         => 'ma_window',
                'value'       => '7',
                'type'        => 'int',
                'group'       => 'fallback',
                'label'       => 'MA Window Size',
                'description' => 'Moving average window (days) used when the LSTM service is unavailable.',
            ],
            [
                'key'         => 'ma_weekend_factor',
                'value'       => '0.9',
                'type'        => 'float',
                'group'       => 'fallback',
                'label'       => 'Weekend Demand Factor',
                'description' => 'Multiplier applied to weekend day predictions in the fallback model.',
            ],
            [
                'key'         => 'ma_lower_bound',
                'value'       => '0.8',
                'type'        => 'float',
                'group'       => 'fallback',
                'label'       => 'Lower Bound Multiplier',
                'description' => 'Prediction × this value = lower confidence bound in fallback forecasts.',
            ],
            [
                'key'         => 'ma_upper_bound',
                'value'       => '1.2',
                'type'        => 'float',
                'group'       => 'fallback',
                'label'       => 'Upper Bound Multiplier',
                'description' => 'Prediction × this value = upper confidence bound in fallback forecasts.',
            ],
            [
                'key'         => 'ma_confidence',
                'value'       => '0.60',
                'type'        => 'float',
                'group'       => 'fallback',
                'label'       => 'Fallback Confidence Score',
                'description' => 'Fixed confidence score assigned to all fallback (non-LSTM) predictions.',
            ],

            // ── Decision Engine ───────────────────────────────────────────────
            [
                'key'         => 'urgency_hours',
                'value'       => '24',
                'type'        => 'int',
                'group'       => 'decision',
                'label'       => 'Urgency Threshold (hours)',
                'description' => 'Bookings created within this many hours of their start time are flagged as urgent.',
            ],
            [
                'key'         => 'long_duration_hours',
                'value'       => '4',
                'type'        => 'int',
                'group'       => 'decision',
                'label'       => 'Long Duration Threshold (hours)',
                'description' => 'Bookings longer than this duration receive an additional risk score.',
            ],
            [
                'key'         => 'risk_high_threshold',
                'value'       => '70',
                'type'        => 'int',
                'group'       => 'decision',
                'label'       => 'High Risk Score Threshold',
                'description' => 'Risk scores at or above this value are labelled High Risk.',
            ],
            [
                'key'         => 'risk_med_threshold',
                'value'       => '40',
                'type'        => 'int',
                'group'       => 'decision',
                'label'       => 'Medium Risk Score Threshold',
                'description' => 'Risk scores at or above this value (and below High) are labelled Medium Risk.',
            ],

            // ── Conflict Probability Scoring ──────────────────────────────────
            [
                'key'         => 'conflict_score_peak_hour',
                'value'       => '30',
                'type'        => 'int',
                'group'       => 'decision',
                'label'       => 'Score: Peak Hour',
                'description' => 'Points added when the booking falls in a peak usage hour.',
            ],
            [
                'key'         => 'conflict_score_high_day_vol',
                'value'       => '20',
                'type'        => 'int',
                'group'       => 'decision',
                'label'       => 'Score: High Day Volume',
                'description' => 'Points added when the requested day has above-average booking volume.',
            ],
            [
                'key'         => 'conflict_score_short_notice',
                'value'       => '25',
                'type'        => 'int',
                'group'       => 'decision',
                'label'       => 'Score: Short Notice',
                'description' => 'Points added for bookings made within the urgency window.',
            ],
            [
                'key'         => 'conflict_score_long_duration',
                'value'       => '15',
                'type'        => 'int',
                'group'       => 'decision',
                'label'       => 'Score: Long Duration',
                'description' => 'Points added for bookings exceeding the long-duration threshold.',
            ],
            [
                'key'         => 'conflict_score_popular_room',
                'value'       => '10',
                'type'        => 'int',
                'group'       => 'decision',
                'label'       => 'Score: Popular Room',
                'description' => 'Points added when a frequently-booked room is requested.',
            ],
            [
                'key'         => 'conflict_day_volume_ratio',
                'value'       => '1.2',
                'type'        => 'float',
                'group'       => 'decision',
                'label'       => 'Day Volume Ratio',
                'description' => 'A day is considered high-volume when its count exceeds average × this ratio.',
            ],

            // ── Anomaly Detection ─────────────────────────────────────────────
            [
                'key'         => 'demand_spike_multiplier',
                'value'       => '1.5',
                'type'        => 'float',
                'group'       => 'decision',
                'label'       => 'Demand Spike Multiplier',
                'description' => 'Recent frequency must exceed average × this value to trigger a spike alert.',
            ],
            [
                'key'         => 'low_approval_threshold',
                'value'       => '60',
                'type'        => 'float',
                'group'       => 'decision',
                'label'       => 'Low Approval Rate Threshold (%)',
                'description' => 'Approval rates below this percentage trigger a low-approval-rate anomaly.',
            ],
            [
                'key'         => 'approval_improvement_threshold',
                'value'       => '70',
                'type'        => 'float',
                'group'       => 'decision',
                'label'       => 'Approval Improvement Threshold (%)',
                'description' => 'Approval rates below this generate a process-improvement recommendation.',
            ],
            [
                'key'         => 'ma_noise_factor',
                'value'       => '0.1',
                'type'        => 'float',
                'group'       => 'fallback',
                'label'       => 'MA Noise Factor',
                'description' => 'Random noise injected into occupancy fallback predictions (fraction of average).',
            ],
            [
                'key'         => 'ma_floor_avg',
                'value'       => '3.0',
                'type'        => 'float',
                'group'       => 'fallback',
                'label'       => 'MA Floor Average',
                'description' => 'Minimum average used in occupancy fallback when no history exists.',
            ],

            // ── Booking Behaviour ─────────────────────────────────────────────
            [
                'key'         => 'approval_time_validation',
                'value'       => '1',
                'type'        => 'bool',
                'group'       => 'booking',
                'label'       => 'Enable Approval Time Validation',
                'description' => 'When ON (default), offline meeting bookings must be created at least 1 hour before the meeting start time. Turn OFF to allow same-hour bookings from the Receptionist Dashboard.',
            ],

            // ── Security / Spam Detection ─────────────────────────────────────
            [
                'key'         => 'spam_threshold',
                'value'       => '10',
                'type'        => 'int',
                'group'       => 'security',
                'label'       => 'Form Spam Threshold',
                'description' => 'Number of form submissions from the same IP within the window before flagging spam.',
            ],
            [
                'key'         => 'spam_window_seconds',
                'value'       => '60',
                'type'        => 'int',
                'group'       => 'security',
                'label'       => 'Spam Detection Window (seconds)',
                'description' => 'Time window in seconds for the form spam rate-limiter.',
            ],

            // ── Role Priority Multipliers ─────────────────────────────────────
            [
                'key'         => 'priority_admin',
                'value'       => '1.0',
                'type'        => 'float',
                'group'       => 'decision',
                'label'       => 'Priority Multiplier: Admin',
                'description' => 'Risk score multiplier applied to bookings made by Admin role users.',
            ],
            [
                'key'         => 'priority_manager',
                'value'       => '0.8',
                'type'        => 'float',
                'group'       => 'decision',
                'label'       => 'Priority Multiplier: Manager',
                'description' => 'Risk score multiplier applied to bookings made by Manager role users.',
            ],
            [
                'key'         => 'priority_default',
                'value'       => '0.5',
                'type'        => 'float',
                'group'       => 'decision',
                'label'       => 'Priority Multiplier: Default',
                'description' => 'Risk score multiplier applied to all other role users.',
            ],
        ];

        foreach ($settings as $data) {
            AISettings::updateOrCreate(
                ['key' => $data['key']],
                $data
            );
        }

        $this->command->info('AI settings seeded (' . count($settings) . ' entries).');
    }
}
