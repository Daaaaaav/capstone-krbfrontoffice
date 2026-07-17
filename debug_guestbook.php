<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Guestbook as GuestbookModel;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

$tz = 'Asia/Jakarta';
$now = Carbon::now($tz);

echo "=== CURRENT TIME ===" . PHP_EOL;
echo "Carbon::now(Jakarta): " . $now->toDateTimeString() . PHP_EOL;
echo PHP_EOL;

echo "=== GUESTBOOK ENTRIES STILL ACTIVE (jam_out IS NULL) ===" . PHP_EOL;
$active = GuestbookModel::whereNull('jam_out')
    ->whereNull('deleted_at')
    ->orderByDesc('created_at')
    ->limit(20)
    ->get(['guestbook_id', 'name', 'instansi', 'keperluan', 'date', 'jam_in', 'jam_out', 'qr_status', 'created_at']);

echo "Count: " . $active->count() . PHP_EOL;
foreach ($active as $g) {
    $dateStr = is_object($g->date) ? $g->date->format('Y-m-d') : $g->date;
    echo "  ID=" . $g->guestbook_id
       . " | date=" . $dateStr
       . " | jam_in=" . $g->jam_in
       . " | jam_out=" . ($g->jam_out ?: 'NULL')
       . " | qr_status=" . $g->qr_status
       . " | name=" . $g->name . PHP_EOL;
}
echo PHP_EOL;

echo "=== ENTRIES FROM YESTERDAY AND BEFORE (still active) ===" . PHP_EOL;
$today = $now->toDateString();
$staleActive = GuestbookModel::whereNull('jam_out')
    ->whereNull('deleted_at')
    ->whereDate('date', '<', $today)
    ->orderByDesc('date')
    ->get(['guestbook_id', 'name', 'date', 'jam_in', 'qr_status']);

echo "Count: " . $staleActive->count() . PHP_EOL;
foreach ($staleActive as $g) {
    $dateStr = is_object($g->date) ? $g->date->format('Y-m-d') : $g->date;
    echo "  ID=" . $g->guestbook_id
       . " | date=" . $dateStr
       . " | jam_in=" . $g->jam_in
       . " | qr_status=" . $g->qr_status
       . " | name=" . $g->name . PHP_EOL;
}
echo PHP_EOL;

echo "=== DOES AUTO-CHECKOUT LOGIC EXIST? ===" . PHP_EOL;
echo "GuestbookStatus::getActiveEntriesProperty() only filters: whereNull('jam_out')" . PHP_EOL;
echo "There is NO auto-checkout scheduler command registered." . PHP_EOL;
echo "Entries must be manually checked out or the system needs an auto-checkout feature." . PHP_EOL;
echo PHP_EOL;
echo "Done." . PHP_EOL;
