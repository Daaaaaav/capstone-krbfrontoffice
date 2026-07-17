<?php
/**
 * Debug script: run via  php debug_bookings.php
 * Checks guestbook entries stuck without jam_out.
 */
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\BookingRoom;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

$tz = 'Asia/Jakarta';
$now = Carbon::now($tz);

echo "=== TIMEZONE DIAGNOSTICS ===" . PHP_EOL;
echo "app.timezone config  : " . config('app.timezone') . PHP_EOL;
echo "Carbon::now(Jakarta) : " . $now->toDateTimeString() . PHP_EOL;
echo "PHP date()           : " . date('Y-m-d H:i:s') . PHP_EOL;
$dbTime = DB::selectOne('SELECT NOW() as db_now, @@global.time_zone as tz, @@session.time_zone as sess_tz');
echo "MySQL NOW()          : " . $dbTime->db_now . PHP_EOL;
echo "MySQL global TZ      : " . $dbTime->tz . PHP_EOL;
echo "MySQL session TZ     : " . $dbTime->sess_tz . PHP_EOL;
echo PHP_EOL;

$endExpr = "COALESCE(
    CASE WHEN end_time REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2} ' THEN end_time END,
    CASE WHEN `date` REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2} ' THEN `date` END,
    CONCAT(`date`, ' ', end_time)
)";

echo "=== STUCK BOOKINGS (end_time passed, still pending/approved) ===" . PHP_EOL;
$stuck = BookingRoom::query()
    ->whereIn('status', ['pending', 'approved'])
    ->whereNotNull('date')
    ->whereNotNull('end_time')
    ->whereRaw("$endExpr <= ?", [$now->format('Y-m-d H:i:s')])
    ->select('bookingroom_id', 'meeting_title', 'status', 'date', 'start_time', 'end_time', 'book_reject')
    ->orderByDesc('date')
    ->get();

echo "Count: " . $stuck->count() . PHP_EOL;
foreach ($stuck as $b) {
    $dateStr = is_object($b->date) ? $b->date->format('Y-m-d') : $b->date;
    echo "  ID=" . $b->bookingroom_id
       . " | status=" . $b->status
       . " | date=" . $dateStr
       . " | start=" . $b->start_time
       . " | end=" . $b->end_time
       . " | book_reject=" . ($b->book_reject ?: 'NULL')
       . " | title=" . $b->meeting_title . PHP_EOL;
}
echo PHP_EOL;

echo "=== TESTING AUTO-COMPLETE MANUALLY ===" . PHP_EOL;
$threshold = $now->copy()->subMinute()->format('Y-m-d H:i:s');
echo "Threshold (now - 1min): $threshold" . PHP_EOL;

$wouldComplete = BookingRoom::query()
    ->whereNotNull('date')
    ->whereNotNull('end_time')
    ->whereRaw("$endExpr IS NOT NULL")
    ->whereRaw("$endExpr <= ?", [$threshold])
    ->whereRaw("LOWER(TRIM(`status`)) = 'approved'")
    ->select('bookingroom_id', 'meeting_title', 'status', 'date', 'start_time', 'end_time')
    ->get();

echo "Bookings AutoCompleteBookings command WOULD update: " . $wouldComplete->count() . PHP_EOL;
foreach ($wouldComplete as $b) {
    $dateStr = is_object($b->date) ? $b->date->format('Y-m-d') : $b->date;
    echo "  ID=" . $b->bookingroom_id . " | date=" . $dateStr . " | end=" . $b->end_time . PHP_EOL;
}
echo PHP_EOL;

echo "=== RECENTLY COMPLETED BOOKINGS (what BookingHistory shows) ===" . PHP_EOL;
$completed = BookingRoom::query()
    ->where(function ($q) {
        $q->whereIn(DB::raw("LOWER(TRIM(`status`))"), ['done', 'completed', '3']);
    })
    ->where(function ($q) {
        $q->whereNull('book_reject')->orWhere('book_reject', '');
    })
    ->whereNull('deleted_at')
    ->orderByDesc('updated_at')
    ->limit(10)
    ->get(['bookingroom_id', 'meeting_title', 'status', 'date', 'book_reject', 'updated_at']);

echo "Count: " . $completed->count() . PHP_EOL;
foreach ($completed as $b) {
    $dateStr = is_object($b->date) ? $b->date->format('Y-m-d') : $b->date;
    echo "  ID=" . $b->bookingroom_id
       . " | status=" . $b->status
       . " | date=" . $dateStr
       . " | book_reject=" . ($b->book_reject ?: 'NULL')
       . " | updated_at=" . $b->updated_at
       . " | title=" . $b->meeting_title . PHP_EOL;
}
echo PHP_EOL;

echo "=== COMPLETED BUT WITH BOOK_REJECT SET (hidden from history!) ===" . PHP_EOL;
$hidden = BookingRoom::query()
    ->where(function ($q) {
        $q->whereIn(DB::raw("LOWER(TRIM(`status`))"), ['done', 'completed', '3']);
    })
    ->whereNotNull('book_reject')
    ->where('book_reject', '!=', '')
    ->whereNull('deleted_at')
    ->orderByDesc('updated_at')
    ->limit(20)
    ->get(['bookingroom_id', 'meeting_title', 'status', 'date', 'book_reject', 'updated_at']);

echo "Count: " . $hidden->count() . PHP_EOL;
foreach ($hidden as $b) {
    $dateStr = is_object($b->date) ? $b->date->format('Y-m-d') : $b->date;
    echo "  ID=" . $b->bookingroom_id
       . " | status=" . $b->status
       . " | date=" . $dateStr
       . " | book_reject=" . $b->book_reject
       . " | title=" . $b->meeting_title . PHP_EOL;
}
echo PHP_EOL;

echo "=== SCHEDULER STATUS ===" . PHP_EOL;
echo "Run:  php artisan schedule:list" . PHP_EOL;
echo "Dev:  php artisan schedule:work  (keep running in terminal)" . PHP_EOL;
echo PHP_EOL;
echo "Done." . PHP_EOL;
