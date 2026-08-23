<?php

namespace App\Console\Commands;

use App\Models\Guestbook;
use App\Models\ManagerNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Notify receptionists when a manager-scheduled visitor's arrival time has been reached.
 *
 * Runs every minute (everyMinute) via the Laravel scheduler.
 * Duplicate prevention: the 'receptionist_notified_at' column on guestbooks is set when
 * the notification is dispatched; subsequent scheduler runs skip rows where this is non-null.
 *
 * Mirrors the Priority Room / Priority Vehicle notification pattern: uses
 * ManagerNotification::notifyReceptionists() with TYPE_SCHEDULED_VISITOR, actionRequired: false.
 */
class NotifyScheduledVisitors extends Command
{
    protected $signature   = 'visitors:notify-scheduled';
    protected $description = 'Dispatch receptionist notifications for scheduled visitors whose arrival time has been reached';

    private string $tz = 'Asia/Jakarta';

    public function handle(): int
    {
        $tz  = config('app.timezone', $this->tz);
        $now = Carbon::now($tz);

        // Find every manager-scheduled visitor that:
        //   1. Has not been checked out yet (jam_out IS NULL)
        //   2. Has not been notified yet (receptionist_notified_at IS NULL)
        //   3. Whose scheduled date + arrival time is now in the past (i.e. the visit window has opened)
        //
        // We evaluate the combined datetime as CONCAT(date, ' ', jam_in) and compare to NOW().
        $visitors = Guestbook::query()
            ->whereNull('deleted_at')
            ->where('scheduled_by_manager', true)
            ->whereNull('jam_out')
            ->whereNull('receptionist_notified_at')
            ->where(function ($q) use ($now) {
                // date < today  OR  (date = today AND jam_in <= current time)
                $q->where('date', '<', $now->toDateString())
                  ->orWhere(function ($q2) use ($now) {
                      $q2->where('date', $now->toDateString())
                         ->whereRaw("TIME(jam_in) <= ?", [$now->format('H:i:s')]);
                  });
            })
            ->get();

        if ($visitors->isEmpty()) {
            $this->line('Scheduled visitors: none to notify.');
            return self::SUCCESS;
        }

        $notified = 0;

        foreach ($visitors as $visitor) {
            try {
                DB::transaction(function () use ($visitor, $now, &$notified) {
                    // Re-check inside the transaction (optimistic lock pattern) to prevent
                    // a race condition if two scheduler processes run simultaneously.
                    $locked = Guestbook::where('guestbook_id', $visitor->guestbook_id)
                        ->whereNull('receptionist_notified_at')
                        ->lockForUpdate()
                        ->first();

                    if (! $locked) {
                        // Already handled by a concurrent run — skip.
                        return;
                    }

                    $scheduledDatetime = Carbon::parse(
                        $locked->date->format('Y-m-d') . ' ' . $locked->jam_in,
                        config('app.timezone', $this->tz)
                    );

                    $message = 'Scheduled visitor "' . $locked->name . '"'
                        . ($locked->instansi ? ' from "' . $locked->instansi . '"' : '')
                        . ' is expected at reception'
                        . ' — scheduled for ' . $scheduledDatetime->format('d M Y, H:i') . '.'
                        . ($locked->keperluan ? ' Purpose: ' . $locked->keperluan . '.' : '')
                        . ' Please assist them at the front desk.';

                    ManagerNotification::notifyReceptionists(
                        (int) $locked->company_id,
                        ManagerNotification::TYPE_SCHEDULED_VISITOR,
                        'Scheduled Visitor Arriving — ' . $locked->name,
                        $message,
                        $locked,
                        actionRequired: false
                    );

                    // Mark as notified so this row is never processed again.
                    $locked->update(['receptionist_notified_at' => $now->toDateTimeString()]);

                    $notified++;
                });
            } catch (\Throwable $e) {
                Log::error('NotifyScheduledVisitors: failed for guestbook_id=' . $visitor->guestbook_id, [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Scheduled visitor notification(s) dispatched: {$notified}");

        return self::SUCCESS;
    }
}
