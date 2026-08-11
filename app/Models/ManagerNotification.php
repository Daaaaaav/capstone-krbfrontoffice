<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ManagerNotification extends Model
{
    protected $table = 'manager_notifications';
    protected $primaryKey = 'id';

    protected $fillable = [
        'company_id',
        'recipient_id',
        'type',
        'title',
        'message',
        'notifiable_type',
        'notifiable_id',
        'action_required',
        'action_taken',
        'is_read',
    ];

    protected $casts = [
        'action_required' => 'boolean',
        'is_read'         => 'boolean',
    ];

    const TYPE_ROOM_CANCEL_REQUEST     = 'priority_room_cancel_request';
    const TYPE_VEHICLE_CANCEL_REQUEST  = 'priority_vehicle_cancel_request';
    const TYPE_PRIORITY_ROOM_DIRECT    = 'priority_room_direct';
    const TYPE_PRIORITY_VEHICLE_DIRECT = 'priority_vehicle_direct';
    const TYPE_SCHEDULED_VISITOR       = 'scheduled_visitor';

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id', 'user_id');
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'notifiable_type', 'notifiable_id');
    }

    public function scopeForCompany($query, ?int $companyId)
    {
        return $companyId ? $query->where('company_id', $companyId) : $query;
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeActionRequired($query)
    {
        return $query->where('action_required', true)->whereNull('action_taken');
    }

    public function scopeForRecipient($query, int $userId)
    {
        return $query->where('recipient_id', $userId);
    }

    public function markRead(): void
    {
        if (!$this->is_read) {
            $this->update(['is_read' => true]);
        }
    }

    public function isPendingAction(): bool
    {
        return $this->action_required && $this->action_taken === null;
    }

    public function iconClass(): string
    {
        return match ($this->type) {
            self::TYPE_ROOM_CANCEL_REQUEST, self::TYPE_PRIORITY_ROOM_DIRECT       => 'text-amber-500',
            self::TYPE_VEHICLE_CANCEL_REQUEST, self::TYPE_PRIORITY_VEHICLE_DIRECT => 'text-blue-500',
            self::TYPE_SCHEDULED_VISITOR                                           => 'text-emerald-600',
            default                                                                => 'text-muted-foreground',
        };
    }

    public static function notifyReceptionists(
        int $companyId,
        string $type,
        string $title,
        string $message,
        Model $notifiable,
        bool $actionRequired = false
    ): void {
        $receptionistRoleId = \App\Models\Role::where('name', 'Receptionist')->value('role_id');

        $receptionists = User::where('company_id', $companyId)
            ->where('role_id', $receptionistRoleId)
            ->whereNull('deleted_at')
            ->pluck('user_id');

        foreach ($receptionists as $recipientId) {
            static::create([
                'company_id'       => $companyId,
                'recipient_id'     => $recipientId,
                'type'             => $type,
                'title'            => $title,
                'message'          => $message,
                'notifiable_type'  => get_class($notifiable),
                'notifiable_id'    => $notifiable->getKey(),
                'action_required'  => $actionRequired,
                'action_taken'     => null,
                'is_read'          => false,
            ]);
        }
    }

    public static function pendingCountFor(int $userId, int $companyId): int
    {
        return static::where('company_id', $companyId)
            ->where('recipient_id', $userId)
            ->where('is_read', false)
            ->count();
    }
}
