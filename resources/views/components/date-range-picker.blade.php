@props([
    'startDate' => null,
    'endDate' => null,
])

@php
    $wireModelStart = $attributes->whereStartsWith('wire:model')->first() ?? 'startDate';
    $wireModelEnd = str_replace('startDate', 'endDate', $wireModelStart);
    $startKey = preg_replace('/^wire:model(?:\.[a-z]+)*=?/', '', $wireModelStart);
    $startKey = trim($startKey, '"\'');
    $endKey = preg_replace('/^wire:model(?:\.[a-z]+)*=?/', '', $wireModelEnd);
    $endKey = trim($endKey, '"\'');
    $uid = 'drp_' . uniqid();
@endphp

<style>
.drp-wrap { position: relative; display: inline-block; min-width: 280px; }
.drp-trigger {
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
    width: 100%; height: 40px; padding: 0 12px;
    border-radius: 8px; border: 1px solid #d1d5db;
    background: #ffffff; font-size: 0.875rem; font-weight: 500;
    color: #111827 !important; -webkit-text-fill-color: #111827 !important;
    cursor: pointer; white-space: nowrap;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05); outline: none;
}
.drp-trigger:hover { background: #f9fafb; }
.drp-icon { flex-shrink:0; width:16px; height:16px; color:#6b7280; }
.drp-portal {
    position: fixed; z-index: 99999;
    border-radius: 12px; border: 1px solid #e5e7eb;
    background: #ffffff; box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    padding: 20px; min-width: 640px;
}
.drp-calendars { 
    display: grid; 
    grid-template-columns: 1fr 1fr; 
    gap: 20px; 
    margin-bottom: 20px;
}
.drp-calendar-section {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.drp-label { 
    font-size: 0.75rem; 
    font-weight: 600; 
    color: #6b7280; 
    text-transform: uppercase; 
    letter-spacing: 0.05em; 
    padding: 0 4px;
}
.drp-actions { display: flex; gap: 8px; }
.drp-btn {
    flex: 1; padding: 10px 16px; border-radius: 8px;
    font-size: 0.875rem; font-weight: 600; cursor: pointer;
    transition: all 0.15s; outline: none; border: none;
}
.drp-btn-primary {
    background: #4E653D; color: #ffffff;
}
.drp-btn-primary:hover { background: #3d4f2f; }
.drp-btn-secondary {
    background: #f3f4f6; color: #374151; border: 1px solid #d1d5db;
}
.drp-btn-secondary:hover { background: #e5e7eb; }

@media (max-width: 768px) {
    .drp-portal {
        min-width: 320px;
        max-width: calc(100vw - 32px);
        left: 16px !important;
        right: 16px;
        width: auto;
    }
    .drp-calendars {
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
         tempStart: $wire.entangle('{{ $startKey }}'),
         tempEnd: $wire.entangle('{{ $endKey }}'),
         selectingStart: true,
         startCalendarRef: null,
         endCalendarRef: null,
         get displayRange() {
             const start = this.tempStart || $wire.{{ $startKey }};
             const end = this.tempEnd || $wire.{{ $endKey }};
             if (!start || !end) return '{{ __('app.select_date_range') ?? 'Select Date Range' }}';
             const s = new Date(start);
             const e = new Date(end);
             const fmt = { month: 'short', day: 'numeric', year: 'numeric' };
             return s.toLocaleDateString('en-US', fmt) + ' - ' + e.toLocaleDateString('en-US', fmt);
         },
         toggle() {
             const el = document.getElementById('{{ $uid }}');
             const btn = el.querySelector('.drp-trigger');
             const r = btn.getBoundingClientRect();
             this.triggerRect = { 
                 top: r.bottom + window.scrollY + 4, 
                 left: r.left + window.scrollX, 
                 width: Math.max(r.width, 320) 
             };
             this.tempStart = $wire.{{ $startKey }};
             this.tempEnd = $wire.{{ $endKey }};
             this.selectingStart = true;
             this.open = !this.open;
             
             this.$nextTick(() => {
                 if (this.open) {
                     this.syncCalendars();
                 }
             });
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
     class="drp-wrap"
>
    <button type="button" class="drp-trigger" @click.stop="toggle()">
        <span x-text="displayRange"></span>
        <svg class="drp-icon" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
        </svg>
    </button>

    <template x-teleport="body">
        <div class="drp-portal"
            x-show="open"
            x-cloak
            @click.outside="close()"
            @mousedown.stop
            :style="`top:${triggerRect.top}px; left:${triggerRect.left}px;`"
            style="display:none;">
            
            <div class="drp-calendars">
                <div class="drp-calendar-section">
                    <label class="drp-label">{{ __('app.start_date') ?? 'Start Date' }}</label>
                    <x-custom-calendar 
                        id="{{ $uid }}-start-cal"
                        :max-date="now()->format('Y-m-d')"
                        @date-selected="onStartDateSelected($event)"
                        @click.stop
                        @mousedown.stop
                    />
                </div>
                
                <div class="drp-calendar-section">
                    <label class="drp-label">{{ __('app.end_date') ?? 'End Date' }}</label>
                    <x-custom-calendar 
                        id="{{ $uid }}-end-cal"
                        x-bind:min-date="tempStart"
                        :max-date="now()->format('Y-m-d')"
                        @date-selected="onEndDateSelected($event)"
                        @click.stop
                        @mousedown.stop
                    />
                </div>
            </div>

            <div class="drp-actions">
                <button type="button" class="drp-btn drp-btn-secondary" @click="close()">
                    {{ __('app.cancel') ?? 'Cancel' }}
                </button>
                <button type="button" class="drp-btn drp-btn-primary" @click="apply()">
                    {{ __('app.apply') ?? 'Apply' }}
                </button>
            </div>
        </div>
    </template>
</div>
