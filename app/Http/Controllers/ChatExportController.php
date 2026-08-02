<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use App\Services\AnalyticsExportService;

class ChatExportController extends Controller
{
    public function __construct(private AnalyticsExportService $analytics) {}

    public function exportPdf(Request $request)
    {
        $user    = Auth::user();
        $data    = $this->analytics->build($user->company_id ?? null);
        $data['exported_by'] = $user->full_name ?? $user->name ?? 'Unknown';

        $pdf = Pdf::loadView('pdf.analytics-export', $data)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isRemoteEnabled' => false,
                'defaultFont'     => 'DejaVu Sans',
            ]);

        if ($request->query('key')) {
            session()->forget($request->query('key'));
        }

        return $pdf->download('analytics-report-' . now()->format('Ymd-His') . '.pdf');
    }

    public function exportCsv(Request $request)
    {
        $user = Auth::user();
        $data = $this->analytics->build($user->company_id ?? null);

        if ($request->query('key')) {
            session()->forget($request->query('key'));
        }

        $exportedBy = $user->full_name ?? $user->name ?? 'Unknown';
        $rows       = $this->buildCsvRows($data, $exportedBy);
        $filename   = 'analytics-report-' . now()->format('Ymd-His') . '.csv';

        $callback = function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function buildCsvRows(array $d, string $exportedBy): array
    {
        $rows   = [];
        $months = $d['months'];

        $rows[] = ['KRB Facility Management — Analytics Report'];
        $rows[] = ['Generated At', $d['generated_at']];
        $rows[] = ['Exported By',  $exportedBy];
        $rows[] = ['YTD Period',   $d['period_ytd']];
        $rows[] = ['Week Period',  $d['period_week']];
        $rows[] = [];

        $rows[] = ['=== KPI SUMMARY ==='];
        $rows[] = ['Metric', 'This Week', 'Year-to-Date', "Prev Year ({$d['prev_year']})", 'YoY Change (%)'];

        $yoy = fn($v) => $v === null ? 'N/A' : ($v > 0 ? "+{$v}%" : "{$v}%");

        $rows[] = ['Room Bookings',    $d['rooms']['week_total'],    $d['rooms']['ytd_total'],    $d['rooms']['prev_year'],    $yoy($d['rooms']['yoy_change'])];
        $rows[] = ['Vehicle Bookings', $d['vehicles']['week_total'], $d['vehicles']['ytd_total'], $d['vehicles']['prev_year'], $yoy($d['vehicles']['yoy_change'])];
        $rows[] = ['Deliveries',       $d['deliveries']['week_total'], $d['deliveries']['ytd_total'], 'N/A', 'N/A'];
        $rows[] = ['Guest Visits',     $d['guests']['week_total'],   $d['guests']['ytd_total'],   'N/A', 'N/A'];
        $rows[] = [];

        $rows[] = ['=== ROOM BOOKINGS ==='];
        $rows[] = ['Metric', 'Value'];
        $rows[] = ['Total YTD',          $d['rooms']['ytd_total']];
        $rows[] = ['Pending',            $d['rooms']['ytd_pending']];
        $rows[] = ['Approved / Ongoing', $d['rooms']['ytd_approved']];
        $rows[] = ['Completed',          $d['rooms']['ytd_completed']];
        $rows[] = ['Rejected',           $d['rooms']['ytd_rejected']];
        $rows[] = ['Rejection Rate',     $d['rooms']['rejection_rate'] . '%'];
        $rows[] = ['Today\'s Meetings',  $d['rooms']['today']];
        $rows[] = ['Most Booked Room (YTD)',  $d['rooms']['top_room']];
        $rows[] = ['Most Booked Room (Week)', $d['rooms']['top_room_week']];
        $rows[] = ['Top Department (YTD)',    $d['rooms']['top_dept']];
        $rows[] = ['Top Department (Week)',   $d['rooms']['top_dept_week']];
        $rows[] = ['Peak Booking Hour',       $d['rooms']['peak_hour']];
        $rows[] = [];

        $rows[] = ['Room Bookings — Monthly Breakdown'];
        $rows[] = array_merge(['Month'], $months);
        $rows[] = array_merge(['Count'], array_values($d['rooms']['monthly']));
        $rows[] = [];

        $rows[] = ['=== VEHICLE BOOKINGS ==='];
        $rows[] = ['Metric', 'Value'];
        $rows[] = ['Total YTD',          $d['vehicles']['ytd_total']];
        $rows[] = ['Pending',            $d['vehicles']['ytd_pending']];
        $rows[] = ['Approved / Ongoing', $d['vehicles']['ytd_approved']];
        $rows[] = ['Rejected',           $d['vehicles']['ytd_rejected']];
        $rows[] = ['Rejection Rate',     $d['vehicles']['rejection_rate'] . '%'];
        $rows[] = ['Today\'s Trips',     $d['vehicles']['today']];
        $rows[] = [];

        $rows[] = ['Vehicle Bookings — Monthly Breakdown'];
        $rows[] = array_merge(['Month'], $months);
        $rows[] = array_merge(['Count'], array_values($d['vehicles']['monthly']));
        $rows[] = [];

        $rows[] = ['=== DELIVERIES ==='];
        $rows[] = ['Metric', 'Value'];
        $rows[] = ['Total YTD',    $d['deliveries']['ytd_total']];
        $rows[] = ['Pending Docs', $d['deliveries']['ytd_pending']];
        $rows[] = [];

        $rows[] = ['Deliveries — Monthly Breakdown'];
        $rows[] = array_merge(['Month'], $months);
        $rows[] = array_merge(['Count'], array_values($d['deliveries']['monthly']));
        $rows[] = [];

        $rows[] = ['=== GUEST VISITS ==='];
        $rows[] = ['Metric', 'Value'];
        $rows[] = ['Total YTD',     $d['guests']['ytd_total']];
        $rows[] = ['Today\'s Visits', $d['guests']['today']];
        $rows[] = [];

        $rows[] = ['Guest Visits — Monthly Breakdown'];
        $rows[] = array_merge(['Month'], $months);
        $rows[] = array_merge(['Count'], array_values($d['guests']['monthly']));
        $rows[] = [];

        $rows[] = ['=== ACTIONABLE FLAGS ==='];
        $rows[] = ['Level', 'Category', 'Recommendation'];
        foreach ($d['flags'] as $flag) {
            $rows[] = [strtoupper($flag['level']), $flag['category'], $flag['message']];
        }

        return $rows;
    }
}
