{{--
    forecast-date-picker.blade.php
    ─────────────────────────────
    Props
        start-key   : Livewire property name for the start date  (default: forecastStartDate)
        end-key     : Livewire property name for the end date    (default: forecastEndDate)
        start-value : Current start date value (PHP-rendered, Y-m-d)
        end-value   : Current end date value   (PHP-rendered, Y-m-d)

    Changes vs. original
        • No x-teleport – panel is position:absolute inside position:relative wrapper,
          so Livewire morphing never loses DOM ownership.
        • No $wire.entangle() – tempStart/tempEnd are plain Alpine props seeded from
          PHP @json() values on open. Livewire is written only on Apply via $wire.set().
        • No __x.$data – parent dispatches window event 'fdp-sync-{calId}' containing
          the new selectedDate / rangeStart / rangeEnd. Each custom-calendar listens for
          its own event and updates internally.
        • $startKey / $endKey come from explicit Blade props, not fragile regex parsing.
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
    /* overflow hidden keeps the shadow clean */
}
/* Flip left-edge if panel would overflow viewport to the right */
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
    The outer div carries wire:ignore so Livewire will not morph its internals.
    Alpine owns all DOM inside; Livewire only reads/writes the two date strings
    via $wire.set() / $wire.get() at the boundaries (open & apply).
--}}
<div {{ $attributes->only('class') }}
     id="{{ $uid }}"
     wire:ignore
     x-data="{
         open:     false,
         tempStart: @json($startValue),
         tempEnd:   @json($endValue),

         /* ── helpers ─────────────────────────────────────── */
         get displayRange() {
             const s = this.tempStart;
             const e = this.tempEnd;
             if (!s || !e) return '{{ __('app.select_forecast_period') ?? 'Select Forecast Period' }}';
             const fmt = { month: 'short', day: 'numeric', year: 'numeric' };
             return new Date(s + 'T00:00:00').toLocaleDateString('en-US', fmt)
                  + ' – '
                  + new Date(e + 'T00:00:00').toLocaleDateString('en-US', fmt);
         },

         /* ── open / close ────────────────────────────────── */
         async toggle() {
             if (this.open) { this.cancel(); return; }

             /* Refresh from Livewire before opening */
             this.tempStart = await $wire.get('{{ $startKey }}') || this.tempStart;
             this.tempEnd   = await $wire.get('{{ $endKey }}')   || this.tempEnd;

             this.open = true;
             this.$nextTick(() => {
                 this.alignPanel();
                 this.pushRangeToCalendars();
             });
         },

         cancel() {
             this.open = false;
         },

         /* ── apply: write back to Livewire ───────────────── */
         async apply() {
             if (!this.tempStart || !this.tempEnd) return;
             if (new Date(this.tempEnd) < new Date(this.tempStart)) {
                 this.tempEnd = this.tempStart;
             }
             await $wire.set('{{ $startKey }}', this.tempStart);
             await $wire.set('{{ $endKey }}',   this.tempEnd);
             this.open = false;
         },

         /* ── date selection callbacks ─────────────────────── */
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
                 /* Swap so range is always ascending */
                 [this.tempStart, this.tempEnd] = [this.tempEnd, this.tempStart];
             }
             this.pushRangeToCalendars();
         },

         /*
          * pushRangeToCalendars
          * ─────────────────────
          * Dispatches a namespaced window event that each custom-calendar
          * listens for by its own id. This completely replaces the __x.$data hack.
          */
         pushRangeToCalendars() {
             const startPayload = {
                 calId:        '{{ $startCalId }}',
                 selectedDate: this.tempStart,
                 rangeStart:   this.tempStart,
                 rangeEnd:     this.tempEnd,
             };
             const endPayload = {
                 calId:        '{{ $endCalId }}',
                 selectedDate: this.tempEnd,
                 rangeStart:   this.tempStart,
                 rangeEnd:     this.tempEnd,
             };
             window.dispatchEvent(new CustomEvent('fdp-sync', { detail: startPayload }));
             window.dispatchEvent(new CustomEvent('fdp-sync', { detail: endPayload   }));
         },

         /*
          * alignPanel
          * ──────────
          * Keeps the inline panel inside the viewport by adding .fdp-align-right
          * when the panel would overflow on the right.
          */
         alignPanel() {
             const wrap  = document.getElementById('{{ $uid }}');
             const panel = wrap?.querySelector('.fdp-panel');
             if (!wrap || !panel) return;

             panel.classList.remove('fdp-align-right');
             const rect    = panel.getBoundingClientRect();
             const vpWidth = window.innerWidth;
             if (rect.right > vpWidth - 16) {
                 panel.classList.add('fdp-align-right');
             }
         }
     }"
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
        Inline panel – NO x-teleport.
        x-cloak hides it until Alpine initialises (prevents flash).
        @click.outside closes on click outside the entire fdp-wrap,
        but we use a simple click-outside implemented via @click.away on
        the panel itself combined with @click.stop on the trigger.
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
