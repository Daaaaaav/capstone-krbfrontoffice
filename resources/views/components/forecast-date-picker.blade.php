@props([
    'startDate' => null,
    'endDate' => null,
])

@php
    $wireModelStart = $attributes->whereStartsWith('wire:model')->first() ?? 'forecastStartDate';
    $wireModelEnd = str_replace('startDate', 'endDate', str_replace('forecastStartDate', 'forecastEndDate', $wireModelStart));
    $startKey = preg_replace('/^wire:model(?:\.[a-z]+)*=?/', '', $wireModelStart);
    $startKey = trim($startKey, '"\'');
    $endKey = preg_replace('/^wire:model(?:\.[a-z]+)*=?/', '', $wireModelEnd);
    $endKey = trim($endKey, '"\'');
    $uid = 'fdp_' . uniqid();
    
    $tomorrow = now()->addDay()->format('Y-m-d');
    $maxForecast = now()->addDays(90)->format('Y-m-d');
@endphp

<style>
.fdp-wrap { position: relative; display: inline-block; min-width: 280px; }
.fdp-trigger {
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
    width: 100%; height: 40px; padding: 0 12px;
    border-radius: 8px; border: 1px solid #d1d5db;
    background: #ffffff; font-size: 0.875rem; font-weight: 500;
    color: #111827 !important; -webkit-text-fill-color: #111827 !important;
    cursor: pointer; white-space: nowrap;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05); outline: none;
}
.fdp-trigger:hover { background: #f9fafb; }
.fdp-icon { flex-shrink:0; width:16px; height:16px; color:#6b7280; }
.fdp-portal {
    position: fixed; z-index: 99999;
    border-radius: 12px; border: 1px solid #e5e7eb;
    background: #ffffff; box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    padding: 20px; min-width: 640px;
}
.fdp-calendars { 
    display: grid; 
    grid-template-columns: 1fr 1fr; 
    gap: 20px; 
    margin-bottom: 20px;
}
.fdp-calendar-section {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.fdp-label { 
    font-size: 0.75rem; 
    font-weight: 600; 
    color: #6b7280; 
    text-transform: uppercase; 
    letter-spacing: 0.05em; 
    padding: 0 4px;
}
.fdp-actions { display: flex; gap: 8px; }
.fdp-btn {
    flex: 1; padding: 10px 16px; border-radius: 8px;
    font-size: 0.875rem; font-weight: 600; cursor: pointer;
    transition: all 0.15s; outline: none; border: none;
}
.fdp-btn-primary {
    background: #4E653D; color: #ffffff;
}
.fdp-btn-primary:hover { background: #3d4f2f; }
.fdp-btn-secondary {
    background: #f3f4f6; color: #374151; border: 1px solid #d1d5db;
}
.fdp-btn-secondary:hover { background: #e5e7eb; }

@media (max-width: 768px) {
    .fdp-portal {
        min-width: 320px;
        max-width: calc(100vw - 32px);
        left: 16px !important;
        right: 16px;
        width: auto;
    }
    .fdp-calendars {
        grid-template-columns: 1fr;
        gap: 16px;
    }
}
</style>

<div {{ $attributes->only('class') }}
     id="{{ $uid }}"
     x-data="{
         open: false,
         triggerRect: {},
         portalPosition: { top: 0, left: 0 },
         tempStart: $wire.entangle('{{ $startKey }}'),
         tempEnd: $wire.entangle('{{ $endKey }}'),
         selectingStart: true,
         startCalendarRef: null,
         endCalendarRef: null,
         get displayRange() {
             const start = this.tempStart || $wire.{{ $startKey }};
             const end = this.tempEnd || $wire.{{ $endKey }};
             if (!start || !end) return '{{ __('app.select_forecast_period') ?? 'Select Forecast Period' }}';
             const s = new Date(start);
             const e = new Date(end);
             const fmt = { month: 'short', day: 'numeric', year: 'numeric' };
             return s.toLocaleDateString('en-US', fmt) + ' - ' + e.toLocaleDateString('en-US', fmt);
         },
         toggle() {
             if (this.open) {
                 this.close();
                 return;
             }
             
             this.tempStart = $wire.{{ $startKey }};
             this.tempEnd = $wire.{{ $endKey }};
             this.selectingStart = true;
             this.open = true;
             
             this.$nextTick(() => {
                 this.calculatePosition();
                 this.syncCalendars();
             });
         },
         calculatePosition() {
             const el = document.getElementById('{{ $uid }}');
             const btn = el.querySelector('.fdp-trigger');
             const portal = document.querySelector('#{{ $uid }}-portal');
             
             if (!btn || !portal) return;
             
             const btnRect = btn.getBoundingClientRect();
             const portalRect = portal.getBoundingClientRect();
             
             const viewportWidth = window.innerWidth;
             const viewportHeight = window.innerHeight;
             const scrollX = window.scrollX;
             const scrollY = window.scrollY;
             
             const gap = 4;
             const padding = 16;
             
             let top = btnRect.bottom + scrollY + gap;
             let left = btnRect.left + scrollX;
             
             if (left + portalRect.width > viewportWidth - padding) {
                 left = Math.max(padding, viewportWidth - portalRect.width - padding + scrollX);
             }
             
             if (left < padding + scrollX) {
                 left = padding + scrollX;
             }
             
             if (btnRect.bottom + portalRect.height > viewportHeight - padding) {
                 const spaceAbove = btnRect.top;
                 const spaceBelow = viewportHeight - btnRect.bottom;
                 
                 if (spaceAbove > spaceBelow && spaceAbove > portalRect.height + gap) {
                     top = btnRect.top + scrollY - portalRect.height - gap;
                 }
             }
             
             this.portalPosition = { top, left };
         },
         close() { 
             this.open = false; 
             this.tempStart = $wire.{{ $startKey }};
             this.tempEnd = $wire.{{ $endKey }};
             this.selectingStart = true;
         },
         apply() {
             if (this.tempStart && this.tempEnd) {
                 if (new Date(this.tempStart) <= new Date(this.tempEnd)) {
                     $wire.set('{{ $startKey }}', this.tempStart);
                     $wire.set('{{ $endKey }}', this.tempEnd);
                     this.close();
                 }
             }
         },
         onStartDateSelected(event) {
             this.tempStart = event.detail.date;
             this.selectingStart = false;
             this.syncCalendars();
         },
         onEndDateSelected(event) {
             this.tempEnd = event.detail.date;
             if (this.tempStart && new Date(this.tempEnd) < new Date(this.tempStart)) {
                 [this.tempStart, this.tempEnd] = [this.tempEnd, this.tempStart];
             }
             this.syncCalendars();
         },
         syncCalendars() {
             this.$nextTick(() => {
                 const startCal = document.getElementById('{{ $uid }}-start-cal');
                 const endCal = document.getElementById('{{ $uid }}-end-cal');
                 
                 if (startCal && startCal.__x) {
                     startCal.__x.$data.selectedDate = this.tempStart;
                     startCal.__x.$data.rangeStart = this.tempStart;
                     startCal.__x.$data.rangeEnd = this.tempEnd;
                     startCal.__x.$data.generateCalendar();
                 }
                 
                 if (endCal && endCal.__x) {
                     endCal.__x.$data.selectedDate = this.tempEnd;
                     endCal.__x.$data.rangeStart = this.tempStart;
                     endCal.__x.$data.rangeEnd = this.tempEnd;
                     endCal.__x.$data.generateCalendar();
                 }
             });
         }
     }"
     @keydown.escape.window="if (open) close()"
     @resize.window="if (open) $nextTick(() => calculatePosition())"
     class="fdp-wrap"
>
    <button type="button" class="fdp-trigger" @click.stop="toggle()">
        <span x-text="displayRange"></span>
        <svg class="fdp-icon" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
        </svg>
    </button>

    <template x-teleport="body">
        <div 
            id="{{ $uid }}-portal"
            class="fdp-portal"
            x-show="open"
            x-cloak
            @click.outside="close()"
            @mousedown.stop
            :style="`top: ${portalPosition.top}px; left: ${portalPosition.left}px;`">
            
            <div class="fdp-calendars">
                <div class="fdp-calendar-section">
                    <label class="fdp-label">{{ __('app.forecast_start') ?? 'Forecast Start' }}</label>
                    <x-custom-calendar 
                        id="{{ $uid }}-start-cal"
                        :min-date="$tomorrow"
                        :max-date="$maxForecast"
                        @date-selected="onStartDateSelected($event)"
                        @click.stop
                        @mousedown.stop
                    />
                </div>
                
                <div class="fdp-calendar-section">
                    <label class="fdp-label">{{ __('app.forecast_end') ?? 'Forecast End' }}</label>
                    <x-custom-calendar 
                        id="{{ $uid }}-end-cal"
                        x-bind:min-date="tempStart || '{{ $tomorrow }}'"
                        :max-date="$maxForecast"
                        @date-selected="onEndDateSelected($event)"
                        @click.stop
                        @mousedown.stop
                    />
                </div>
            </div>

            <div class="fdp-actions">
                <button type="button" class="fdp-btn fdp-btn-secondary" @click="close()">
                    {{ __('app.cancel') ?? 'Cancel' }}
                </button>
                <button type="button" class="fdp-btn fdp-btn-primary" @click="apply()">
                    {{ __('app.apply') ?? 'Apply' }}
                </button>
            </div>
        </div>
    </template>
</div>
