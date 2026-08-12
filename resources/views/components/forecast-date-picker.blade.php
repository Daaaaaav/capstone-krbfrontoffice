{{--
    forecast-date-picker.blade.php
    ─────────────────────────────
    Props
        start-key   : Livewire property name for the start date  (default: forecastStartDate)
        end-key     : Livewire property name for the end date    (default: forecastEndDate)
        start-value : Current start date value (PHP-rendered, Y-m-d)
        end-value   : Current end date value   (PHP-rendered, Y-m-d)

    Architecture
        The Alpine component data is defined in a forecastDatePicker(el) factory
        function in the <script> block below.  Seed values (startValue, endValue,
        startKey, endKey, uid, startCalId, endCalId) are stored as data-* attributes
        on the root element so that NO PHP-rendered value ever appears inside an
        x-data="…" string — which is the root cause of the JavaScript-leak bug.

        @json() inside an x-data="…" attribute produces a double-quoted JS string
        such as "2026-08-13".  That double-quote terminates the HTML attribute early,
        causing Alpine source code to spill into the rendered page as visible text.

        Storing values in data-* attributes and reading them in JS is always safe
        because Blade's {{ }} echo escapes HTML entities (htmlspecialchars), so a
        double-quote becomes &quot; and never breaks the attribute boundary.
--}}
@props([
    'startKey'   => 'forecastStartDate',
    'endKey'     => 'forecastEndDate',
    'startValue' => null,
    'endValue'   => null,
])

@php
    $uid        = 'fdp_' . Str::random(8);
    $startCalId = $uid . '_start';
    $endCalId   = $uid . '_end';
    $tomorrow   = now()->addDay()->format('Y-m-d');
    $maxDate    = now()->addDays(90)->format('Y-m-d');
@endphp

<style>
/* ── Trigger button ────────────────────────────────────────────────────── */
.fdp-wrap   { position: relative; display: inline-block; min-width: 280px; }
.fdp-trigger {
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
    width: 100%; height: 40px; padding: 0 12px;
    border-radius: 8px; border: 1px solid #d1d5db;
    background: #ffffff; font-size: 0.875rem; font-weight: 500;
    color: #111827; cursor: pointer; white-space: nowrap;
    box-shadow: 0 1px 2px rgba(0,0,0,.05); outline: none;
}
.fdp-trigger:hover { background: #f9fafb; }
.fdp-icon   { flex-shrink: 0; width: 16px; height: 16px; color: #6b7280; }

/* ── Dropdown panel ────────────────────────────────────────────────────── */
.fdp-panel {
    position: absolute;
    z-index: 9999;
    top: calc(100% + 4px);
    left: 0;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    background: #ffffff;
    box-shadow: 0 4px 20px rgba(0,0,0,.15);
    padding: 20px;
    min-width: 640px;
}
.fdp-panel.fdp-align-right { left: auto; right: 0; }

.fdp-calendars {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}
.fdp-calendar-section { display: flex; flex-direction: column; gap: 12px; }
.fdp-label  {
    font-size: .75rem; font-weight: 600; color: #6b7280;
    text-transform: uppercase; letter-spacing: .05em; padding: 0 4px;
}
.fdp-actions { display: flex; gap: 8px; }
.fdp-btn    {
    flex: 1; padding: 10px 16px; border-radius: 8px;
    font-size: .875rem; font-weight: 600; cursor: pointer;
    transition: all .15s; outline: none; border: none;
}
.fdp-btn-primary   { background: #4E653D; color: #ffffff; }
.fdp-btn-primary:hover  { background: #3d4f2f; }
.fdp-btn-secondary { background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; }
.fdp-btn-secondary:hover { background: #e5e7eb; }

@media (max-width: 768px) {
    .fdp-panel      { min-width: min(640px, calc(100vw - 32px)); }
    .fdp-calendars  { grid-template-columns: 1fr; gap: 16px; }
}
</style>

{{--
    ╔══════════════════════════════════════════════════════════════════════╗
    ║  SAFE attribute design                                               ║
    ║                                                                      ║
    ║  x-data uses SINGLE outer quotes  →  x-data='forecastDatePicker()'  ║
    ║  No PHP values appear inside x-data at all.                          ║
    ║                                                                      ║
    ║  All PHP-rendered seed values live in data-* attributes whose        ║
    ║  values are HTML-entity-escaped by Blade's {{ }} syntax.             ║
    ║  A date string like 2026-08-13 contains no special HTML characters,  ║
    ║  so it passes through unchanged and is 100% safe.                    ║
    ╚══════════════════════════════════════════════════════════════════════╝

    wire:ignore keeps Livewire from morphing Alpine's internals.
    Alpine owns all DOM inside; Livewire only reads/writes the two date
    strings via $wire.set() / $wire.get() at the boundaries (open & apply).
--}}
<div {{ $attributes->only('class') }}
     id="{{ $uid }}"
     wire:ignore
     x-data='forecastDatePicker()'
     data-uid="{{ $uid }}"
     data-start-cal-id="{{ $startCalId }}"
     data-end-cal-id="{{ $endCalId }}"
     data-start-key="{{ $startKey }}"
     data-end-key="{{ $endKey }}"
     data-start-value="{{ $startValue ?? '' }}"
     data-end-value="{{ $endValue ?? '' }}"
     data-label-placeholder="{{ __('app.select_forecast_period') ?? 'Select Forecast Period' }}"
     @keydown.escape.window="if (open) cancel()"
     @resize.window.debounce.200ms="if (open) alignPanel()"
     class="fdp-wrap">

    {{-- Trigger ─────────────────────────────────────────────────────────── --}}
    <button type="button" class="fdp-trigger" @click.stop="toggle()">
        <span x-text="displayRange"></span>
        <svg class="fdp-icon" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd"
                  d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z"
                  clip-rule="evenodd"/>
        </svg>
    </button>

    {{--
        Inline panel — NO x-teleport.
        x-cloak hides it until Alpine initialises (prevents flash).
        @click.outside closes on click outside the entire fdp-wrap.
    --}}
    <div class="fdp-panel"
         x-show="open"
         x-cloak
         @click.stop
         @mousedown.stop
         @click.outside="cancel()">

        <div class="fdp-calendars">

            {{-- Start calendar ──────────────────────────────────────────── --}}
            <div class="fdp-calendar-section">
                <label class="fdp-label">{{ __('app.forecast_start') ?? 'Forecast Start' }}</label>
                <x-custom-calendar
                    id="{{ $startCalId }}"
                    :min-date="$tomorrow"
                    :max-date="$maxDate"
                    @date-selected.stop="onStartSelected($event)"
                    @click.stop
                    @mousedown.stop
                />
            </div>

            {{-- End calendar ────────────────────────────────────────────── --}}
            <div class="fdp-calendar-section">
                <label class="fdp-label">{{ __('app.forecast_end') ?? 'Forecast End' }}</label>
                <x-custom-calendar
                    id="{{ $endCalId }}"
                    :min-date="$tomorrow"
                    :max-date="$maxDate"
                    @date-selected.stop="onEndSelected($event)"
                    @click.stop
                    @mousedown.stop
                />
            </div>

        </div>

        <div class="fdp-actions">
            <button type="button" class="fdp-btn fdp-btn-secondary" @click.stop="cancel()">
                {{ __('app.cancel') ?? 'Cancel' }}
            </button>
            <button type="button" class="fdp-btn fdp-btn-primary" @click.stop="apply()">
                {{ __('app.apply') ?? 'Apply' }}
            </button>
        </div>
    </div>

</div>

{{--
    ╔══════════════════════════════════════════════════════════════════════╗
    ║  forecastDatePicker()                                                ║
    ║  ─────────────────────                                               ║
    ║  Alpine data factory for the forecast date-range picker.             ║
    ║                                                                      ║
    ║  Config is read from data-* attributes on the root element ($el)     ║
    ║  inside init(), which runs after Alpine has bound the element.       ║
    ║  This means NO PHP value ever appears inside an x-data="…" string.  ║
    ║                                                                      ║
    ║  The function is guarded against duplicate registration so that      ║
    ║  Livewire full-page re-renders (which re-execute <script> tags)      ║
    ║  do not throw "Identifier has already been declared".                ║
    ╚══════════════════════════════════════════════════════════════════════╝
--}}
<script>
(function () {
    // Guard: only define once, even if Livewire re-renders this component.
    if (typeof window.forecastDatePicker !== 'undefined') return;

    window.forecastDatePicker = function forecastDatePicker() {
        return {
            /* ── state ───────────────────────────────────────────────── */
            open:      false,
            tempStart: null,
            tempEnd:   null,

            /* ── config (populated in init from data-* attrs) ─────────── */
            _uid:            '',
            _startCalId:     '',
            _endCalId:       '',
            _startKey:       'forecastStartDate',
            _endKey:         'forecastEndDate',
            _placeholder:    'Select Forecast Period',

            /* ── lifecycle ───────────────────────────────────────────── */
            init() {
                const el = this.$el;
                this._uid         = el.dataset.uid         || '';
                this._startCalId  = el.dataset.startCalId  || '';
                this._endCalId    = el.dataset.endCalId    || '';
                this._startKey    = el.dataset.startKey    || 'forecastStartDate';
                this._endKey      = el.dataset.endKey      || 'forecastEndDate';
                this._placeholder = el.dataset.labelPlaceholder || 'Select Forecast Period';

                // Seed initial values from data attributes.
                // dataset values are always strings; treat empty string as null.
                const sv = el.dataset.startValue;
                const ev = el.dataset.endValue;
                this.tempStart = (sv && sv !== 'null' && sv !== '') ? sv : null;
                this.tempEnd   = (ev && ev !== 'null' && ev !== '') ? ev : null;
            },

            /* ── computed ────────────────────────────────────────────── */
            get displayRange() {
                const s = this.tempStart;
                const e = this.tempEnd;
                if (!s || !e) return this._placeholder;
                const fmt = { month: 'short', day: 'numeric', year: 'numeric' };
                return new Date(s + 'T00:00:00').toLocaleDateString('en-US', fmt)
                     + ' \u2013 '
                     + new Date(e + 'T00:00:00').toLocaleDateString('en-US', fmt);
            },

            /* ── open / close ────────────────────────────────────────── */
            async toggle() {
                if (this.open) { this.cancel(); return; }

                // Refresh from Livewire before opening.
                try {
                    const sv = await $wire.get(this._startKey);
                    const ev = await $wire.get(this._endKey);
                    if (sv) this.tempStart = sv;
                    if (ev) this.tempEnd   = ev;
                } catch (_) { /* $wire unavailable in isolation */ }

                this.open = true;
                this.$nextTick(() => {
                    this.alignPanel();
                    this.pushRangeToCalendars();
                });
            },

            cancel() {
                this.open = false;
            },

            /* ── apply: write back to Livewire ───────────────────────── */
            async apply() {
                if (!this.tempStart || !this.tempEnd) return;
                if (new Date(this.tempEnd) < new Date(this.tempStart)) {
                    this.tempEnd = this.tempStart;
                }
                try {
                    await $wire.set(this._startKey, this.tempStart);
                    await $wire.set(this._endKey,   this.tempEnd);
                } catch (_) { /* $wire unavailable in isolation */ }
                this.open = false;
            },

            /* ── date selection callbacks ─────────────────────────────── */
            onStartSelected(ev) {
                this.tempStart = ev.detail.date;
                if (this.tempEnd && new Date(this.tempEnd) < new Date(this.tempStart)) {
                    this.tempEnd = this.tempStart;
                }
                this.pushRangeToCalendars();
            },

            onEndSelected(ev) {
                this.tempEnd = ev.detail.date;
                if (this.tempStart && new Date(this.tempEnd) < new Date(this.tempStart)) {
                    // Swap so range is always ascending.
                    [this.tempStart, this.tempEnd] = [this.tempEnd, this.tempStart];
                }
                this.pushRangeToCalendars();
            },

            /*
             * pushRangeToCalendars
             * ─────────────────────
             * Dispatches window event 'fdp-sync' with a calId payload.
             * Each custom-calendar listens on the window and filters by calId,
             * so only the matching calendar reacts.
             */
            pushRangeToCalendars() {
                window.dispatchEvent(new CustomEvent('fdp-sync', {
                    detail: {
                        calId:        this._startCalId,
                        selectedDate: this.tempStart,
                        rangeStart:   this.tempStart,
                        rangeEnd:     this.tempEnd,
                    }
                }));
                window.dispatchEvent(new CustomEvent('fdp-sync', {
                    detail: {
                        calId:        this._endCalId,
                        selectedDate: this.tempEnd,
                        rangeStart:   this.tempStart,
                        rangeEnd:     this.tempEnd,
                    }
                }));
            },

            /*
             * alignPanel
             * ──────────
             * Adds .fdp-align-right when the panel would overflow the
             * right edge of the viewport.
             */
            alignPanel() {
                const wrap  = document.getElementById(this._uid);
                const panel = wrap ? wrap.querySelector('.fdp-panel') : null;
                if (!panel) return;
                panel.classList.remove('fdp-align-right');
                const rect    = panel.getBoundingClientRect();
                const vpWidth = window.innerWidth;
                if (rect.right > vpWidth - 16) {
                    panel.classList.add('fdp-align-right');
                }
            },
        };
    };
})();
</script>
