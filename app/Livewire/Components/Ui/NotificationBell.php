<?php

namespace App\Livewire\Components\Ui;

use Livewire\Attributes\On;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\ManagerNotification;
use App\Models\PriorityRoomBooking;
use App\Models\PriorityVehicleBooking;
use App\Models\BookingRoom;
use App\Models\VehicleBooking;
use App\Models\Guestbook;

class NotificationBell extends Component
{
    public bool $open = false;

    public function mount(): void
    {
        $user = Auth::user();
        if (!$user) return;

        $pending = ManagerNotification::where('company_id', $user->company_id ?? 0)
            ->where('recipient_id', $user->user_id)
            ->where('action_required', true)
            ->whereNull('action_taken')
            ->where('is_read', false)
            ->orderByDesc('created_at')
            ->get();

        if ($pending->isEmpty()) return;

        $sessionKey  = 'notif_toasted_ids_' . $user->user_id;
        $alreadySeen = session($sessionKey, []);

        $newOnes = $pending->filter(fn($n) => !in_array($n->id, $alreadySeen));

        if ($newOnes->isEmpty()) return;

        $newIds = [];
        foreach ($newOnes as $n) {
            $newIds[]   = $n->id;
            $reviewPage = match (true) {
                str_contains($n->type, 'room')              => 'Room Bookings Approval',
                str_contains($n->type, 'vehicle')           => 'Vehicle Status',
                $n->type === \App\Models\ManagerNotification::TYPE_SCHEDULED_VISITOR => 'Guestbook Status',
                default                                     => 'Notifications',
            };

            $this->dispatch('toast',
                type: 'warning',
                title: '🔔 ' . $n->title,
                message: 'Action required — check ' . $reviewPage . ' to review.',
                duration: 8000
            );
        }

        session([$sessionKey => array_merge($alreadySeen, $newIds)]);
    }

    public function getUnreadCountProperty(): int
    {
        $user = Auth::user();
        if (!$user) return 0;

        return ManagerNotification::where('company_id', $user->company_id ?? 0)
            ->where('recipient_id', $user->user_id)
            ->where('is_read', false)
            ->count();
    }

    public function getNotifsProperty()
    {
        $user = Auth::user();
        if (!$user) return collect();

        return ManagerNotification::where('company_id', $user->company_id ?? 0)
            ->where('recipient_id', $user->user_id)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();
    }

    public function toggle(): void
    {
        $this->open = !$this->open;
    }

    public function close(): void
    {
        $this->open = false;
    }

    public function markAllRead(): void
    {
        $user = Auth::user();
        if (!$user) return;

        ManagerNotification::where('company_id', $user->company_id ?? 0)
            ->where('recipient_id', $user->user_id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $this->dispatch('toast', type: 'info', title: 'Done', message: 'All notifications marked as read.', duration: 2000);
    }

    public function markRead(int $id): void
    {
        $user = Auth::user();
        if (!$user) return;

        ManagerNotification::where('id', $id)
            ->where('recipient_id', $user->user_id)
            ->update(['is_read' => true]);
    }

    public function approveDirectly(int $notifId): void
    {
        $user = Auth::user();
        if (!$user) return;

        $notif = ManagerNotification::where('id', $notifId)
            ->where('company_id', $user->company_id ?? 0)
            ->where('action_required', true)
            ->whereNull('action_taken')
            ->first();

        if (!$notif) {
            $this->dispatch('toast', type: 'warning', title: 'Not Found',
                message: 'Notification not found or already actioned.', duration: 3000);
            return;
        }

        try {
            DB::transaction(function () use ($notif, $user) {

                if (str_contains($notif->type, 'room')) {
                    $priority = PriorityRoomBooking::where('id', $notif->notifiable_id)
                        ->where('company_id', $user->company_id ?? 0)
                        ->firstOrFail();

                    if ($priority->cancels_booking_id) {
                        BookingRoom::where('bookingroom_id', $priority->cancels_booking_id)
                            ->whereIn('status', ['pending', 'approved', 'completed', 'done', '1', '3'])
                            ->whereNotIn('booking_type', ['online_meeting', 'onlinemeeting'])
                            ->update([
                                'status'      => 'rejected',
                                'book_reject' => 'Cancelled — superseded by manager priority booking #' . $priority->id . '.',
                                'approved_by' => $user->user_id,
                            ]);
                    }

                    $priority->update([
                        'status'     => PriorityRoomBooking::STATUS_APPROVED,
                        'handled_by' => $user->user_id,
                    ]);

                } else {
                    $priority = PriorityVehicleBooking::where('id', $notif->notifiable_id)
                        ->where('company_id', $user->company_id ?? 0)
                        ->firstOrFail();

                    if ($priority->cancels_booking_id) {
                        VehicleBooking::where('vehiclebooking_id', $priority->cancels_booking_id)
                            ->where('status', 'pending')
                            ->update([
                                'status' => 'rejected',
                                'notes'  => DB::raw(
                                    "TRIM(CONCAT(COALESCE(notes,''), IF(COALESCE(notes,'')='','','\n'), '[Cancelled — superseded by manager priority booking #" . $priority->id . "]'))"
                                ),
                            ]);
                    }

                    $priority->update([
                        'status'     => PriorityVehicleBooking::STATUS_APPROVED,
                        'handled_by' => $user->user_id,
                    ]);
                }

                $notif->update(['action_taken' => 'approved', 'is_read' => true]);

                $sessionKey = 'notif_toasted_ids_' . $user->user_id;
                $seen = session($sessionKey, []);
                $seen[] = $notif->id;
                session([$sessionKey => $seen]);
            });

            $this->dispatch('toast', type: 'success', title: 'Approved',
                message: 'Priority booking approved and conflicting booking cancelled.', duration: 4000);

        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', title: 'Error',
                message: 'Failed: ' . $e->getMessage(), duration: 5000);
        }
    }

    public bool   $showDetail       = false;
    public ?int   $detailNotifId    = null;
    public bool   $showDenyForm     = false;
    public string $denyReason       = '';

    public function openDetail(int $notifId): void
    {
        $user  = Auth::user();
        $notif = ManagerNotification::where('id', $notifId)
            ->where('company_id', $user->company_id ?? 0)
            ->where('recipient_id', $user->user_id)
            ->first();

        if (!$notif) return;

        $this->detailNotifId = $notifId;
        $this->showDenyForm  = false;
        $this->denyReason    = '';
        $this->showDetail    = true;

        if (!$notif->is_read) {
            $notif->update(['is_read' => true]);
        }
    }

    public function closeDetail(): void
    {
        $this->showDetail    = false;
        $this->detailNotifId = null;
        $this->showDenyForm  = false;
        $this->denyReason    = '';
    }

    public function openDenyForm(): void
    {
        $this->showDenyForm = true;
    }

    public function submitDeny(): void
    {
        $this->validate(['denyReason' => 'required|string|min:3|max:500']);

        if (!$this->detailNotifId) return;

        $user  = Auth::user();
        $notif = ManagerNotification::where('id', $this->detailNotifId)
            ->where('company_id', $user->company_id ?? 0)
            ->where('action_required', true)
            ->whereNull('action_taken')
            ->first();

        if (!$notif) {
            $this->dispatch('toast', type: 'warning', title: 'Not Found',
                message: 'Notification not found or already actioned.', duration: 3000);
            $this->closeDetail();
            return;
        }

        try {
            DB::transaction(function () use ($notif, $user) {
                if (str_contains($notif->type, 'room')) {
                    PriorityRoomBooking::where('id', $notif->notifiable_id)
                        ->where('company_id', $user->company_id ?? 0)
                        ->update([
                            'status'           => PriorityRoomBooking::STATUS_CONFLICT_DENIED,
                            'handled_by'       => $user->user_id,
                            'rejection_reason' => $this->denyReason,
                        ]);
                } else {
                    PriorityVehicleBooking::where('id', $notif->notifiable_id)
                        ->where('company_id', $user->company_id ?? 0)
                        ->update([
                            'status'           => PriorityVehicleBooking::STATUS_CONFLICT_DENIED,
                            'handled_by'       => $user->user_id,
                            'rejection_reason' => $this->denyReason,
                        ]);
                }

                $notif->update(['action_taken' => 'denied', 'is_read' => true]);
            });

            $this->dispatch('toast', type: 'info', title: 'Denied',
                message: 'Priority booking request denied. Original booking kept.', duration: 4000);
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', title: 'Error',
                message: 'Failed: ' . $e->getMessage(), duration: 5000);
        }

        $this->closeDetail();
    }

    public function approveFromDetail(): void
    {
        if (!$this->detailNotifId) return;
        $this->approveDirectly($this->detailNotifId);
        $this->closeDetail();
    }

    public function getDetailNotifProperty(): ?ManagerNotification
    {
        if (!$this->detailNotifId) return null;
        return ManagerNotification::find($this->detailNotifId);
    }

    public function getDetailBookingProperty(): mixed
    {
        $notif = $this->detailNotif;
        if (!$notif || !$notif->notifiable_id) return null;

        if ($notif->type === \App\Models\ManagerNotification::TYPE_SCHEDULED_VISITOR) {
            return \App\Models\Guestbook::with(['department', 'user'])
                ->find($notif->notifiable_id);
        }

        if (str_contains($notif->type, 'room')) {
            return PriorityRoomBooking::with(['room', 'cancelledBooking.room', 'manager'])
                ->find($notif->notifiable_id);
        }

        return PriorityVehicleBooking::with(['vehicle', 'cancelledBooking', 'manager', 'department'])
            ->find($notif->notifiable_id);
    }

    public function denyDirectly(int $notifId): void
    {
        $user = Auth::user();
        if (!$user) return;

        $notif = ManagerNotification::where('id', $notifId)
            ->where('company_id', $user->company_id ?? 0)
            ->where('action_required', true)
            ->whereNull('action_taken')
            ->first();

        if (!$notif) {
            $this->dispatch('toast', type: 'warning', title: 'Not Found',
                message: 'Notification not found or already actioned.', duration: 3000);
            return;
        }

        try {
            DB::transaction(function () use ($notif, $user) {
                if (str_contains($notif->type, 'room')) {
                    PriorityRoomBooking::where('id', $notif->notifiable_id)
                        ->where('company_id', $user->company_id ?? 0)
                        ->update([
                            'status'           => PriorityRoomBooking::STATUS_CONFLICT_DENIED,
                            'handled_by'       => $user->user_id,
                            'rejection_reason' => 'Denied via quick action by receptionist.',
                        ]);
                } else {
                    PriorityVehicleBooking::where('id', $notif->notifiable_id)
                        ->where('company_id', $user->company_id ?? 0)
                        ->update([
                            'status'           => PriorityVehicleBooking::STATUS_CONFLICT_DENIED,
                            'handled_by'       => $user->user_id,
                            'rejection_reason' => 'Denied via quick action by receptionist.',
                        ]);
                }

                $notif->update(['action_taken' => 'denied', 'is_read' => true]);
            });

            $this->dispatch('toast', type: 'info', title: 'Denied',
                message: 'Priority booking request denied. Original booking kept.', duration: 4000);

        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', title: 'Error',
                message: 'Failed: ' . $e->getMessage(), duration: 5000);
        }
    }

    public function render()
    {
        return view('livewire.components.ui.notification-bell', [
            'unreadCount'   => $this->unreadCount,
            'notifs'        => $this->notifs,
            'detailNotif'   => $this->detailNotif,
            'detailBooking' => $this->detailBooking,
        ]);
    }
}