<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Analytics Report — {{ $year }}</title>
    <style>
        @page { margin: 2cm 2.2cm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9pt;
            color: #111;
            line-height: 1.5;
        }

        /* ── Header ── */
        .report-header {
            border-bottom: 2.5px solid #111;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .header-row { display: table; width: 100%; }
        .header-left { display: table-cell; vertical-align: bottom; }
        .header-right { display: table-cell; vertical-align: bottom; text-align: right; }
        .report-title { font-size: 16pt; font-weight: bold; color: #111; }
        .report-sub { font-size: 9pt; color: #555; margin-top: 3px; }
        .badge-pill {
            display: inline-block;
            background: #111;
            color: #fff;
            font-size: 8pt;
            font-weight: bold;
            padding: 3px 10px;
            border-radius: 20px;
            letter-spacing: 0.04em;
        }

        /* ── Section headings ── */
        .section { margin-top: 22px; margin-bottom: 6px; }
        .section-title {
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            border-bottom: 1.5px solid #ddd;
            padding-bottom: 4px;
            color: #111;
        }

        /* ── KPI cards (2-per-row table) ── */
        .kpi-grid { display: table; width: 100%; margin-top: 10px; border-collapse: separate; border-spacing: 6px; }
        .kpi-cell { display: table-cell; width: 25%; }
        .kpi-card {
            border: 1px solid #dde1e9;
            border-radius: 6px;
            padding: 8px 10px;
            background: #f8f9fb;
        }
        .kpi-label { font-size: 7.5pt; color: #666; text-transform: uppercase; letter-spacing: 0.06em; }
        .kpi-value { font-size: 16pt; font-weight: bold; color: #111; margin: 3px 0 1px; }
        .kpi-sub   { font-size: 7.5pt; color: #888; }
        .kpi-pos   { color: #16a34a; }
        .kpi-neg   { color: #dc2626; }
        .kpi-neu   { color: #6b7280; }

        /* ── Tables ── */
        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            font-size: 8.5pt;
        }
        table.data th {
            background: #111;
            color: #fff;
            padding: 5px 7px;
            text-align: left;
            font-weight: bold;
        }
        table.data td {
            border: 1px solid #dde1e9;
            padding: 4px 7px;
        }
        table.data tbody tr:nth-child(even) td {
            background: #f5f7fb;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .bold { font-weight: bold; }

        /* ── Monthly breakdown mini-table ── */
        table.monthly th, table.monthly td {
            font-size: 7.5pt;
            padding: 3px 4px;
            text-align: center;
        }

        /* ── Actionable flags ── */
        .flag { margin-top: 7px; padding: 7px 10px; border-radius: 5px; font-size: 8.5pt; }
        .flag-warning { background: #fef3c7; border-left: 3px solid #f59e0b; }
        .flag-info    { background: #eff6ff; border-left: 3px solid #3b82f6; }
        .flag-ok      { background: #f0fdf4; border-left: 3px solid #22c55e; }
        .flag-cat     { font-weight: bold; margin-bottom: 2px; }

        /* ── Footer ── */
        .doc-footer {
            margin-top: 30px;
            border-top: 1px solid #ccc;
            padding-top: 7px;
            font-size: 7.5pt;
            color: #999;
            text-align: center;
        }

        .page-break { page-break-before: always; }
    </style>
</head>
<body>

{{-- ═══════════════════════════════════════════════ --}}
{{-- HEADER                                          --}}
{{-- ═══════════════════════════════════════════════ --}}
<div class="report-header">
    <div class="header-row">
        <div class="header-left">
            <div class="report-title">Analytics Report</div>
            <div class="report-sub">
                Kebun Raya Bogor Facility Management<br>
                YTD Period: {{ $period_ytd }} &nbsp;|&nbsp; Week: {{ $period_week }}<br>
                Generated: {{ $generated_at }} &nbsp;|&nbsp; By: {{ $exported_by }}
            </div>
        </div>
        <div class="header-right">
            <span class="badge-pill">{{ $year }} Report</span>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════ --}}
{{-- KPI OVERVIEW                                    --}}
{{-- ═══════════════════════════════════════════════ --}}
<div class="section">
    <div class="section-title">KPI Overview — Year to Date</div>
</div>

@php
    $yoyClass = fn($v) => $v === null ? 'kpi-neu' : ($v >= 0 ? 'kpi-pos' : 'kpi-neg');
    $yoyStr   = fn($v) => $v === null ? 'vs prior N/A' : ($v >= 0 ? "+{$v}% vs {$prev_year}" : "{$v}% vs {$prev_year}");
@endphp

<table class="kpi-grid">
    <tr>
        <td class="kpi-cell">
            <div class="kpi-card">
                <div class="kpi-label">Room Bookings</div>
                <div class="kpi-value">{{ number_format($rooms['ytd_total']) }}</div>
                <div class="kpi-sub {{ $yoyClass($rooms['yoy_change']) }}">{{ $yoyStr($rooms['yoy_change']) }}</div>
            </div>
        </td>
        <td class="kpi-cell">
            <div class="kpi-card">
                <div class="kpi-label">Vehicle Bookings</div>
                <div class="kpi-value">{{ number_format($vehicles['ytd_total']) }}</div>
                <div class="kpi-sub {{ $yoyClass($vehicles['yoy_change']) }}">{{ $yoyStr($vehicles['yoy_change']) }}</div>
            </div>
        </td>
        <td class="kpi-cell">
            <div class="kpi-card">
                <div class="kpi-label">Deliveries</div>
                <div class="kpi-value">{{ number_format($deliveries['ytd_total']) }}</div>
                <div class="kpi-sub kpi-neu">{{ $deliveries['ytd_pending'] }} pending</div>
            </div>
        </td>
        <td class="kpi-cell">
            <div class="kpi-card">
                <div class="kpi-label">Guest Visits</div>
                <div class="kpi-value">{{ number_format($guests['ytd_total']) }}</div>
                <div class="kpi-sub kpi-neu">{{ $guests['today'] }} today</div>
            </div>
        </td>
    </tr>
</table>

{{-- ═══════════════════════════════════════════════ --}}
{{-- THIS WEEK SNAPSHOT                              --}}
{{-- ═══════════════════════════════════════════════ --}}
<div class="section">
    <div class="section-title">This Week — {{ $period_week }}</div>
</div>

<table class="data">
    <thead>
        <tr>
            <th>Category</th>
            <th class="text-right">Total</th>
            <th class="text-right">Pending</th>
            <th class="text-right">Approved</th>
            <th class="text-right">Completed</th>
            <th class="text-right">Rejected</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="bold">Room Bookings</td>
            <td class="text-right bold">{{ $rooms['week_total'] }}</td>
            <td class="text-right">{{ $rooms['week_pending'] }}</td>
            <td class="text-right">{{ $rooms['week_approved'] }}</td>
            <td class="text-right">{{ $rooms['week_completed'] }}</td>
            <td class="text-right">{{ $rooms['week_rejected'] }}</td>
        </tr>
        <tr>
            <td class="bold">Vehicle Bookings</td>
            <td class="text-right bold">{{ $vehicles['week_total'] }}</td>
            <td class="text-right">—</td>
            <td class="text-right">—</td>
            <td class="text-right">—</td>
            <td class="text-right">—</td>
        </tr>
        <tr>
            <td class="bold">Deliveries</td>
            <td class="text-right bold">{{ $deliveries['week_total'] }}</td>
            <td class="text-right">—</td>
            <td class="text-right">—</td>
            <td class="text-right">—</td>
            <td class="text-right">—</td>
        </tr>
        <tr>
            <td class="bold">Guest Visits</td>
            <td class="text-right bold">{{ $guests['week_total'] }}</td>
            <td class="text-right">—</td>
            <td class="text-right">—</td>
            <td class="text-right">—</td>
            <td class="text-right">—</td>
        </tr>
    </tbody>
</table>

{{-- ═══════════════════════════════════════════════ --}}
{{-- ROOM BOOKINGS DETAIL                           --}}
{{-- ═══════════════════════════════════════════════ --}}
<div class="section">
    <div class="section-title">Room Bookings — Detail</div>
</div>

<table class="data">
    <thead>
        <tr>
            <th>Metric</th>
            <th class="text-right">Value</th>
            <th>Notes</th>
        </tr>
    </thead>
    <tbody>
        <tr><td>Total YTD</td><td class="text-right bold">{{ $rooms['ytd_total'] }}</td><td>vs {{ $rooms['prev_year'] }} in {{ $prev_year }}</td></tr>
        <tr><td>Pending</td><td class="text-right">{{ $rooms['ytd_pending'] }}</td><td></td></tr>
        <tr><td>Approved / Ongoing</td><td class="text-right">{{ $rooms['ytd_approved'] }}</td><td></td></tr>
        <tr><td>Completed</td><td class="text-right">{{ $rooms['ytd_completed'] }}</td><td></td></tr>
        <tr><td>Rejected</td><td class="text-right">{{ $rooms['ytd_rejected'] }}</td><td>Rejection rate: <strong>{{ $rooms['rejection_rate'] }}%</strong></td></tr>
        <tr><td>Today's Meetings</td><td class="text-right">{{ $rooms['today'] }}</td><td></td></tr>
        <tr><td>Peak Booking Hour</td><td class="text-right">{{ $rooms['peak_hour'] }}</td><td>Most popular time slot YTD</td></tr>
        <tr><td>Most Booked Room (YTD)</td><td class="text-right">{{ $rooms['top_room'] }}</td><td></td></tr>
        <tr><td>Most Booked Room (Week)</td><td class="text-right">{{ $rooms['top_room_week'] }}</td><td></td></tr>
        <tr><td>Top Department (YTD)</td><td class="text-right">{{ $rooms['top_dept'] }}</td><td></td></tr>
        <tr><td>Top Department (Week)</td><td class="text-right">{{ $rooms['top_dept_week'] }}</td><td></td></tr>
    </tbody>
</table>

<br>
<table class="data monthly">
    <thead>
        <tr>
            <th style="text-align:left;">Month</th>
            @foreach ($months as $m)<th>{{ $m }}</th>@endforeach
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="bold" style="text-align:left;">Bookings</td>
            @foreach ($rooms['monthly'] as $cnt)<td>{{ $cnt }}</td>@endforeach
        </tr>
    </tbody>
</table>

{{-- ═══════════════════════════════════════════════ --}}
{{-- VEHICLE BOOKINGS DETAIL                        --}}
{{-- ═══════════════════════════════════════════════ --}}
<div class="section page-break">
    <div class="section-title">Vehicle Bookings — Detail</div>
</div>

<table class="data">
    <thead>
        <tr>
            <th>Metric</th>
            <th class="text-right">Value</th>
            <th>Notes</th>
        </tr>
    </thead>
    <tbody>
        <tr><td>Total YTD</td><td class="text-right bold">{{ $vehicles['ytd_total'] }}</td><td>vs {{ $vehicles['prev_year'] }} in {{ $prev_year }}</td></tr>
        <tr><td>Pending</td><td class="text-right">{{ $vehicles['ytd_pending'] }}</td><td></td></tr>
        <tr><td>Approved / Ongoing</td><td class="text-right">{{ $vehicles['ytd_approved'] }}</td><td></td></tr>
        <tr><td>Rejected</td><td class="text-right">{{ $vehicles['ytd_rejected'] }}</td><td>Rejection rate: <strong>{{ $vehicles['rejection_rate'] }}%</strong></td></tr>
        <tr><td>Today's Trips</td><td class="text-right">{{ $vehicles['today'] }}</td><td></td></tr>
    </tbody>
</table>

<br>
<table class="data monthly">
    <thead>
        <tr>
            <th style="text-align:left;">Month</th>
            @foreach ($months as $m)<th>{{ $m }}</th>@endforeach
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="bold" style="text-align:left;">Bookings</td>
            @foreach ($vehicles['monthly'] as $cnt)<td>{{ $cnt }}</td>@endforeach
        </tr>
    </tbody>
</table>

{{-- ═══════════════════════════════════════════════ --}}
{{-- DELIVERIES + GUESTS                            --}}
{{-- ═══════════════════════════════════════════════ --}}
<div class="section">
    <div class="section-title">Deliveries &amp; Guest Visits</div>
</div>

<table class="data">
    <thead>
        <tr>
            <th>Category</th>
            <th class="text-right">Total YTD</th>
            <th class="text-right">This Week</th>
            <th class="text-right">Today</th>
            <th class="text-right">Pending</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="bold">Deliveries</td>
            <td class="text-right">{{ $deliveries['ytd_total'] }}</td>
            <td class="text-right">{{ $deliveries['week_total'] }}</td>
            <td class="text-right">—</td>
            <td class="text-right">{{ $deliveries['ytd_pending'] }}</td>
        </tr>
        <tr>
            <td class="bold">Guest Visits</td>
            <td class="text-right">{{ $guests['ytd_total'] }}</td>
            <td class="text-right">{{ $guests['week_total'] }}</td>
            <td class="text-right">{{ $guests['today'] }}</td>
            <td class="text-right">—</td>
        </tr>
    </tbody>
</table>

{{-- ═══════════════════════════════════════════════ --}}
{{-- ACTIONABLE FLAGS                               --}}
{{-- ═══════════════════════════════════════════════ --}}
<div class="section">
    <div class="section-title">Actionable Recommendations</div>
</div>

@foreach ($flags as $flag)
    <div class="flag flag-{{ $flag['level'] }}">
        <div class="flag-cat">
            @if ($flag['level'] === 'warning') ⚠ @elseif ($flag['level'] === 'info') ℹ @else ✓ @endif
            {{ $flag['category'] }}
        </div>
        <div>{{ $flag['message'] }}</div>
    </div>
@endforeach

{{-- ═══════════════════════════════════════════════ --}}
{{-- FOOTER                                         --}}
{{-- ═══════════════════════════════════════════════ --}}
<div class="doc-footer">
    KRB Facility Management Analytics Report — {{ $year }} &nbsp;|&nbsp; Confidential — Internal Use Only
</div>

<script type="text/php">
    if (isset($pdf)) {
        $pdf->page_text(510, 812, "Page {PAGE_NUM} of {PAGE_COUNT}", null, 7.5, [0.6, 0.6, 0.6]);
    }
</script>

</body>
</html>
