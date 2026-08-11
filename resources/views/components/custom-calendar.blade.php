{{--
    custom-calendar.blade.php
    ─────────────────────────
    A self-contained month-grid calendar.

    Props
        id        : DOM id AND the key used by parent to sync range state
        modelKey  : (unused externally, kept for back-compat)
        minDate   : earliest selectable date (Y-m-d string)
        maxDate   : latest  selectable date  (Y-m-d string)

    Events EMITTED
        date-selected  { date: 'Y-m-d' }   – bubbles up the DOM

    Events CONSUMED  (window, namespaced)
        fdp-sync  { calId, selectedDate, rangeStart, rangeEnd }
            – parent forecast-date-picker dispatches this to push
              range state into the calendar WITHOUT touching __x.$data.
              Only the event whose calId matches this calendar's id
              is acted upon.
--}}
@props([
    'id'       => 'calendar_' . Str::random(6),
    'modelKey' => 'selectedDate',
    'minDate'  => null,
    'maxDate'  => null,
])

<div x-data="customCalendar({
        id:      '{{ $id }}',
        minDate: '{{ $minDate }}',
        maxDate: '{{ $maxDate }}'
     })"
     id="{{ $id }}"
     x-init="init()"
     {{--
         x-effect watches the min-date HTML attribute so x-bind:min-date changes
         from a parent component are still picked up. (Retained from original.)
     --}}
     x-effect="handleMinDateAttr($el.getAttribute('min-date'))"
     {{--
         Listen for parent sync events on the window.
         We filter by calId inside the handler so no global state is needed.
     --}}
     @fdp-sync.window="handleSyncEvent($event.detail)"
     {{ $attributes->merge(['class' => 'cal-container']) }}>

    {{-- Header: prev / month+year / next ────────────────────────────── --}}
    <div class="cal-header">
        <button type="button" @click="prevMonth()" class="cal-nav-btn" :disabled="!canGoPrev()">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </button>

        <div class="cal-header-title">
            <span x-text="monthNames[currentMonth]"></span>
            <span x-text="currentYear"></span>
        </div>

        <button type="button" @click="nextMonth()" class="cal-nav-btn" :disabled="!canGoNext()">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
        </button>
    </div>

    {{-- Day-of-week header ───────────────────────────────────────────── --}}
    <div class="cal-weekdays">
        <template x-for="day in weekDays" :key="day">
            <div class="cal-weekday" x-text="day"></div>
        </template>
    </div>

    {{-- Day grid ─────────────────────────────────────────────────────── --}}
    <div class="cal-days">
        <template x-for="(day, index) in calendarDays" :key="index">
            <button
                type="button"
                @click="selectDate(day)"
                @mousedown.stop
                :disabled="!day.enabled"
                :class="{
                    'cal-day':           true,
                    'cal-day-empty':     day.empty,
                    'cal-day-disabled':  !day.enabled,
                    'cal-day-today':     day.isToday,
                    'cal-day-selected':  day.isSelected,
                    'cal-day-range-start': day.isRangeStart,
                    'cal-day-range-end':   day.isRangeEnd,
                    'cal-day-in-range':    day.inRange
                }"
                x-text="day.date">
            </button>
        </template>
    </div>
</div>

{{-- ── Styles (scoped by class names, unchanged from original) ──────── --}}
<style>
.cal-container {
    background: white;
    border-radius: 8px;
    padding: 16px;
    min-width: 280px;
}
.cal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
    padding: 0 4px;
}
.cal-header-title {
    font-size: .9375rem;
    font-weight: 600;
    color: #4E653D;
    display: flex;
    gap: 6px;
}
.cal-nav-btn {
    display: flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border-radius: 6px;
    background: transparent; color: #4E653D; border: none;
    cursor: pointer; transition: all .15s;
}
.cal-nav-btn:hover:not(:disabled) { background: #f0f4ed; }
.cal-nav-btn:disabled              { color: #d1d5db; cursor: not-allowed; }
.cal-weekdays {
    display: grid; grid-template-columns: repeat(7, 1fr);
    gap: 4px; margin-bottom: 8px;
}
.cal-weekday {
    text-align: center; font-size: .75rem; font-weight: 600;
    color: #6b7280; padding: 8px 0;
    text-transform: uppercase; letter-spacing: .025em;
}
.cal-days {
    display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px;
}
.cal-day {
    aspect-ratio: 1;
    display: flex; align-items: center; justify-content: center;
    font-size: .875rem; font-weight: 500;
    color: #374151; background: white;
    border: 1px solid transparent; border-radius: 6px;
    cursor: pointer; transition: all .15s; position: relative;
}
.cal-day:hover:not(.cal-day-disabled):not(.cal-day-empty) {
    background: #f0f4ed; border-color: #CDDEA7;
}
.cal-day-empty    { cursor: default; color: transparent; pointer-events: none; }
.cal-day-disabled { color: #d1d5db; cursor: not-allowed; opacity: .5; }
.cal-day-today    { font-weight: 700; color: #4E653D; border-color: #CDDEA7; }
.cal-day-selected { background: #4E653D !important; color: white !important; font-weight: 600; }
.cal-day-range-start {
    background: #4E653D !important; color: white !important; font-weight: 600;
    border-top-right-radius: 0; border-bottom-right-radius: 0;
}
.cal-day-range-end {
    background: #4E653D !important; color: white !important; font-weight: 600;
    border-top-left-radius: 0; border-bottom-left-radius: 0;
}
.cal-day-in-range { background: #f0f4ed; color: #4E653D; border-radius: 0; border-color: transparent; }
.cal-day-in-range:hover { background: #e8efe0; }
</style>

<script>
/*
 * customCalendar(config)
 * ─────────────────────
 * Alpine data factory used by <x-custom-calendar>.
 *
 * Public surface (safe to call from outside):
 *   setRange(selectedDate, rangeStart, rangeEnd)
 *     – sets the highlighted range and regenerates the grid.
 *       Called by handleSyncEvent() when a matching fdp-sync event arrives.
 *
 * The __x.$data pattern has been completely removed.
 * External callers must use the fdp-sync window event instead.
 */
function customCalendar(config) {
    return {
        /* ── identity ───────────────────────────────────────────────── */
        id: config.id,

        /* ── date bounds ─────────────────────────────────────────────── */
        minDate: config.minDate && config.minDate !== 'null' && config.minDate !== ''
            ? new Date(config.minDate)
            : null,
        maxDate: config.maxDate && config.maxDate !== 'null' && config.maxDate !== ''
            ? new Date(config.maxDate)
            : null,

        /* ── navigation state ────────────────────────────────────────── */
        currentMonth: new Date().getMonth(),
        currentYear:  new Date().getFullYear(),

        /* ── selection state ─────────────────────────────────────────── */
        selectedDate: null,
        rangeStart:   null,
        rangeEnd:     null,

        /* ── static data ─────────────────────────────────────────────── */
        monthNames: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
        weekDays:   ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'],

        /* ── computed grid ───────────────────────────────────────────── */
        calendarDays: [],

        /* ── lifecycle ───────────────────────────────────────────────── */
        init() {
            const today = new Date();
            this.currentMonth = today.getMonth();
            this.currentYear  = today.getFullYear();
            this.generateCalendar();
        },

        /*
         * handleMinDateAttr
         * ─────────────────
         * Called by x-effect whenever the min-date HTML attribute changes,
         * e.g. when the parent uses x-bind:min-date.
         */
        handleMinDateAttr(value) {
            if (value && value !== 'null' && value !== '' && value !== 'undefined') {
                try {
                    const d = new Date(value);
                    if (!isNaN(d.getTime())) {
                        const prev = this.minDate ? this.minDate.getTime() : null;
                        if (prev !== d.getTime()) {
                            this.minDate = d;
                            this.generateCalendar();
                        }
                    }
                } catch (_) { /* ignore */ }
            } else if (this.minDate !== null) {
                this.minDate = null;
                this.generateCalendar();
            }
        },

        /*
         * handleSyncEvent
         * ───────────────
         * Receives the fdp-sync window event dispatched by forecast-date-picker.
         * Only acts when event.detail.calId matches this calendar's id.
         * This is the safe replacement for parent code that used __x.$data.
         */
        handleSyncEvent(detail) {
            if (!detail || detail.calId !== this.id) return;
            this.setRange(
                detail.selectedDate ?? null,
                detail.rangeStart   ?? null,
                detail.rangeEnd     ?? null
            );
        },

        /*
         * setRange  (public)
         * ──────────────────
         * Updates selection + range highlighting and navigates to the
         * month of selectedDate if provided.
         */
        setRange(selectedDate, rangeStart, rangeEnd) {
            this.selectedDate = selectedDate;
            this.rangeStart   = rangeStart;
            this.rangeEnd     = rangeEnd;

            /* Navigate the calendar to show the selected date */
            if (selectedDate) {
                const d = new Date(selectedDate + 'T00:00:00');
                if (!isNaN(d.getTime())) {
                    this.currentMonth = d.getMonth();
                    this.currentYear  = d.getFullYear();
                }
            }

            this.generateCalendar();
        },

        /* ── calendar grid ───────────────────────────────────────────── */
        generateCalendar() {
            const firstDay     = new Date(this.currentYear, this.currentMonth, 1);
            const lastDay      = new Date(this.currentYear, this.currentMonth + 1, 0);
            const daysInMonth  = lastDay.getDate();
            const startDOW     = firstDay.getDay();

            const days = [];

            /* Leading empty cells */
            for (let i = 0; i < startDOW; i++) {
                days.push({ date: '', empty: true, enabled: false });
            }

            for (let d = 1; d <= daysInMonth; d++) {
                const cur     = new Date(this.currentYear, this.currentMonth, d);
                const dateStr = this.formatDate(cur);
                const enabled = this.isDateEnabled(cur);

                days.push({
                    date:         d,
                    dateObj:      cur,
                    dateStr:      dateStr,
                    empty:        false,
                    enabled:      enabled,
                    isToday:      this.isToday(cur),
                    isSelected:   !!(this.selectedDate && dateStr === this.selectedDate),
                    isRangeStart: !!(this.rangeStart   && dateStr === this.rangeStart),
                    isRangeEnd:   !!(this.rangeEnd     && dateStr === this.rangeEnd),
                    inRange:      this.isInRange(cur),
                });
            }

            this.calendarDays = days;
        },

        /* ── user interaction ────────────────────────────────────────── */
        selectDate(day) {
            if (!day.enabled || day.empty) return;
            if (!this.isDateEnabled(day.dateObj)) return;

            this.selectedDate = day.dateStr;
            this.$dispatch('date-selected', { date: day.dateStr });
            this.generateCalendar();
        },

        prevMonth() {
            if (this.currentMonth === 0) {
                this.currentMonth = 11;
                this.currentYear--;
            } else {
                this.currentMonth--;
            }
            this.generateCalendar();
        },

        nextMonth() {
            if (this.currentMonth === 11) {
                this.currentMonth = 0;
                this.currentYear++;
            } else {
                this.currentMonth++;
            }
            this.generateCalendar();
        },

        /* ── guard helpers ───────────────────────────────────────────── */
        canGoPrev() {
            if (!this.minDate) return true;
            return new Date(this.currentYear, this.currentMonth, 1) > this.minDate;
        },

        canGoNext() {
            if (!this.maxDate) return true;
            return new Date(this.currentYear, this.currentMonth + 1, 0) < this.maxDate;
        },

        isDateEnabled(date) {
            const d = new Date(date.getFullYear(), date.getMonth(), date.getDate());
            if (this.minDate) {
                const mn = new Date(this.minDate.getFullYear(), this.minDate.getMonth(), this.minDate.getDate());
                if (d < mn) return false;
            }
            if (this.maxDate) {
                const mx = new Date(this.maxDate.getFullYear(), this.maxDate.getMonth(), this.maxDate.getDate());
                if (d > mx) return false;
            }
            return true;
        },

        isToday(date) {
            const t = new Date();
            return date.getDate()     === t.getDate()  &&
                   date.getMonth()    === t.getMonth()  &&
                   date.getFullYear() === t.getFullYear();
        },

        isInRange(date) {
            if (!this.rangeStart || !this.rangeEnd) return false;
            const s = new Date(this.rangeStart + 'T00:00:00');
            const e = new Date(this.rangeEnd   + 'T00:00:00');
            return date > s && date < e;
        },

        formatDate(date) {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        },

        /* Kept for any legacy callers that might still exist in the project */
        setRangeMode(start, end) {
            this.setRange(this.selectedDate, start, end);
        },
    };
}
</script>
