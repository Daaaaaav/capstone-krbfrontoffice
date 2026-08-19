{{-- Status Badge Component --}}
@props([
    'status',
])

@php
    $normalized = strtolower((string) $status);

    $class = match($normalized) {
        'active', 'approved', 'success', 'available', '1' => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/20',
        'pending', 'pending_receipt', 'warning', '0' => 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/20',
        'pending_cancellation' => 'bg-orange-500/10 text-orange-700 dark:text-orange-400 border-orange-500/20',
        'rejected', 'deleted', 'destructive', '2' => 'bg-red-500/10 text-red-700 dark:text-red-400 border-red-500/20',
        'cancelled', 'canceled', 'cancelled_conflict_denied' => 'bg-rose-500/10 text-rose-700 dark:text-rose-400 border-rose-500/20',
        'completed', 'done', 'returned', '3' => 'bg-blue-500/10 text-blue-700 dark:text-blue-400 border-blue-500/20',
        'on_progress', 'in_progress', 'on the road' => 'bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 border-indigo-500/20',
        'late_return' => 'bg-orange-500/10 text-orange-700 dark:text-orange-400 border-orange-500/20',
        'unavailable', 'inactive' => 'bg-slate-500/10 text-slate-700 dark:text-slate-400 border-slate-500/20',
        default => 'bg-muted text-muted-foreground border-border',
    };

    $label = match($normalized) {
        'pending_receipt' => 'Pending',
        'pending_cancellation' => 'Awaiting Cancel',
        'on_progress', 'in_progress' => 'On the Road',
        'late_return' => 'Late Return',
        'cancelled_conflict_denied' => 'Cancelled',
        '0' => 'Pending',
        '1' => 'Approved',
        '2' => 'Rejected',
        '3' => 'Completed',
        default => ucfirst(str_replace('_', ' ', $status)),
    };
@endphp

<span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded-md border {{ $class }}">
    {{ $label }}
</span>
