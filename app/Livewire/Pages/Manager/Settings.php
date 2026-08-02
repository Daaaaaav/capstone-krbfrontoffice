<?php

namespace App\Livewire\Pages\Manager;

use App\Models\AISettings;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

#[Layout('layouts.manager')]
#[Title('Settings')]
class Settings extends Component
{
    // ── Profile ──────────────────────────────────────────────────────────────
    public string $name            = '';
    public string $email           = '';
    public string $phone           = '';
    public string $currentPassword = '';
    public string $newPassword     = '';
    public string $confirmPassword = '';

    public bool    $showPasswordSection = false;
    public ?string $successMessage      = null;
    public ?string $errorMessage        = null;

    // ── AI Settings ──────────────────────────────────────────────────────────
    public string $activeTab      = 'profile';   // 'profile' | 'ai'
    public array  $aiSettings     = [];           // key => value (string, for inputs)
    public array  $aiMeta         = [];           // key => [label, description, type, group]
    public ?string $aiSuccess     = null;
    public ?string $aiError       = null;

    // ── Mount ─────────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $user        = Auth::user();
        $this->name  = $user->full_name ?? $user->name ?? '';
        $this->email = $user->email ?? '';
        $this->phone = $user->phone_number ?? '';

        $this->loadAISettings();
    }

    // ── Profile actions ───────────────────────────────────────────────────────

    public function updateProfile(): void
    {
        $this->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . Auth::id() . ',user_id',
            'phone' => 'nullable|string|max:20',
        ]);

        $user               = Auth::user();
        $user->full_name    = $this->name;
        $user->email        = $this->email;
        $user->phone_number = $this->phone;
        $user->save();

        $this->successMessage = 'Profile updated successfully.';
        $this->errorMessage   = null;
    }

    public function updatePassword(): void
    {
        $this->validate([
            'currentPassword' => 'required',
            'newPassword'     => 'required|min:8',
            'confirmPassword' => 'required|same:newPassword',
        ], [
            'confirmPassword.same' => 'New password and confirmation do not match.',
        ]);

        $user = Auth::user();

        if (! Hash::check($this->currentPassword, $user->password)) {
            $this->errorMessage   = 'Current password is incorrect.';
            $this->successMessage = null;
            return;
        }

        $user->password = Hash::make($this->newPassword);
        $user->save();

        $this->currentPassword = '';
        $this->newPassword     = '';
        $this->confirmPassword = '';
        $this->showPasswordSection = false;

        $this->successMessage = 'Password changed successfully.';
        $this->errorMessage   = null;
    }

    // ── AI Settings actions ───────────────────────────────────────────────────

    /**
     * Load AI settings from the database into component state.
     */
    public function loadAISettings(): void
    {
        $rows = AISettings::orderBy('group')->orderBy('key')->get();

        foreach ($rows as $row) {
            $this->aiSettings[$row->key] = $row->value;
            $this->aiMeta[$row->key] = [
                'label'       => $row->label,
                'description' => $row->description,
                'type'        => $row->type,
                'group'       => $row->group,
            ];
        }
    }

    /**
     * Persist all AI settings to the database and bust the cache.
     */
    public function saveAISettings(): void
    {
        // Basic validation: all values must be present and numeric (int/float/bool)
        $rules = [];
        foreach ($this->aiSettings as $key => $value) {
            $type = $this->aiMeta[$key]['type'] ?? 'float';
            $rules["aiSettings.{$key}"] = match ($type) {
                'int'  => 'required|numeric|integer',
                'bool' => 'required|in:0,1',
                default => 'required|numeric',
            };
        }

        $this->validate($rules);

        foreach ($this->aiSettings as $key => $value) {
            AISettings::where('key', $key)->update(['value' => (string) $value]);
        }

        AISettings::bustCache();

        $this->aiSuccess = 'AI model settings saved successfully.';
        $this->aiError   = null;
    }

    public function resetAISettings(): void
    {
        $defaults = [
            'lstm_units'                     => '64',
            'dropout_rate'                   => '0.1',
            'l2_regularization'              => '0.00001',
            'sequence_window'                => '7',
            'epochs'                         => '150',
            'batch_size'                     => '16',
            'validation_split'               => '0.15',
            'early_stop_patience'            => '10',
            'min_data_points'                => '45',
            'history_days'                   => '730',
            'confidence_min'                 => '0.30',
            'confidence_max'                 => '0.92',
        
            'ma_window'                      => '7',
            'ma_weekend_factor'              => '0.9',
            'ma_lower_bound'                 => '0.8',
            'ma_upper_bound'                 => '1.2',
            'ma_confidence'                  => '0.60',
            'ma_noise_factor'                => '0.1',
            'ma_floor_avg'                   => '3.0',
        
            'urgency_hours'                  => '24',
            'long_duration_hours'            => '4',
            'risk_high_threshold'            => '70',
            'risk_med_threshold'             => '40',
            'conflict_score_peak_hour'       => '30',
            'conflict_score_high_day_vol'    => '20',
            'conflict_score_short_notice'    => '25',
            'conflict_score_long_duration'   => '15',
            'conflict_score_popular_room'    => '10',
            'conflict_day_volume_ratio'      => '1.2',
            'demand_spike_multiplier'        => '1.5',
            'low_approval_threshold'         => '60',
            'approval_improvement_threshold' => '70',
        
            'spam_threshold'                 => '10',
            'spam_window_seconds'            => '60',
        
            'priority_admin'                 => '1.0',
            'priority_manager'               => '0.8',
            'priority_default'               => '0.5',
        
            'approval_time_validation'       => '1',
        ];

        foreach ($defaults as $key => $value) {
            if (isset($this->aiSettings[$key])) {
                $this->aiSettings[$key] = $value;
                AISettings::where('key', $key)->update(['value' => $value]);
            }
        }

        AISettings::bustCache();

        $this->aiSuccess = 'AI settings have been reset to their defaults.';
        $this->aiError   = null;
    }

    public function render()
    {
        $grouped = [];
        foreach ($this->aiMeta as $key => $meta) {
            $grouped[$meta['group']][$key] = $meta;
        }

        return view('livewire.pages.manager.settings', [
            'aiGrouped' => $grouped,
        ]);
    }
}
