@props([
    'id' => 'calendar_' . uniqid(),
    'modelKey' => 'selectedDate',
    'minDate' => null,
    'maxDate' => null,
])

<div x-data="customCalendar({
        id: '{{ $id }}',
        modelKey: '{{ $modelKey }}',
        minDate: '{{ $minDate }}',
        maxDate: '{{ $maxDate }}'
    })"
    x-init="init()"
    x-effect="updateMinDate()"
    {{ $attributes->merge(['class' => 'cal-container']) }}>
    
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
    
    <div class="cal-weekdays">
        <template x-for="day in weekDays" :key="day">
            <div class="cal-weekday" x-text="day"></div>
        </template>
    </div>
    
    <div class="cal-days">
        <template x-for="(day, index) in calendarDays" :key="index">
            <button 
                type="button"
                @click="selectDate(day)"
                @mousedown.stop
                :disabled="!day.enabled"
                :class="{
                    'cal-day': true,
                    'cal-day-empty': day.empty,
                    'cal-day-disabled': !day.enabled,
                    'cal-day-today': day.isToday,
                    'cal-day-selected': day.isSelected,
                    'cal-day-range-start': day.isRangeStart,
                    'cal-day-range-end': day.isRangeEnd,
                    'cal-day-in-range': day.inRange
                }"
                x-text="day.date">
            </button>
        </template>
    </div>
</div>

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
    font-size: 0.9375rem;
    font-weight: 600;
    color: #4E653D;
    display: flex;
    gap: 6px;
}

.cal-nav-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 6px;
    background: transparent;
    color: #4E653D;
    border: none;
    cursor: pointer;
    transition: all 0.15s;
}

.cal-nav-btn:hover:not(:disabled) {
    background: #f0f4ed;
}

.cal-nav-btn:disabled {
    color: #d1d5db;
    cursor: not-allowed;
}

.cal-weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 4px;
    margin-bottom: 8px;
}

.cal-weekday {
    text-align: center;
    font-size: 0.75rem;
    font-weight: 600;
    color: #6b7280;
    padding: 8px 0;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.cal-days {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 2px;
}

.cal-day {
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
    font-weight: 500;
    color: #374151;
    background: white;
    border: 1px solid transparent;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.15s;
    position: relative;
}

.cal-day:hover:not(.cal-day-disabled):not(.cal-day-empty) {
    background: #f0f4ed;
    border-color: #CDDEA7;
}

.cal-day-empty {
    cursor: default;
    color: transparent;
    pointer-events: none;
}

.cal-day-disabled {
    color: #d1d5db;
    cursor: not-allowed;
    opacity: 0.5;
}

.cal-day-today {
    font-weight: 700;
    color: #4E653D;
    border-color: #CDDEA7;
}

.cal-day-selected {
    background: #4E653D !important;
    color: white !important;
    font-weight: 600;
}

.cal-day-range-start {
    background: #4E653D !important;
    color: white !important;
    font-weight: 600;
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
}

.cal-day-range-end {
    background: #4E653D !important;
    color: white !important;
    font-weight: 600;
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
}

.cal-day-in-range {
    background: #f0f4ed;
    color: #4E653D;
    border-radius: 0;
    border-color: transparent;
}

.cal-day-in-range:hover {
    background: #e8efe0;
}
</style>

<script>
function customCalendar(config) {
    return {
        id: config.id,
        modelKey: config.modelKey,
        minDate: config.minDate ? new Date(config.minDate) : null,
        maxDate: config.maxDate ? new Date(config.maxDate) : new Date(),
        
        currentMonth: new Date().getMonth(),
        currentYear: new Date().getFullYear(),
        
        selectedDate: null,
        rangeStart: null,
        rangeEnd: null,
        
        monthNames: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        weekDays: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
        
        calendarDays: [],
        
        init() {
            const today = new Date();
            this.currentMonth = today.getMonth();
            this.currentYear = today.getFullYear();
            this.generateCalendar();
        },
        
        updateMinDate() {
            // Read the actual min-date attribute value (which may be bound via x-bind)
            const minDateValue = this.$el.getAttribute('min-date');
            
            if (minDateValue && minDateValue !== 'null' && minDateValue !== '' && minDateValue !== 'undefined') {
                try {
                    const newMinDate = new Date(minDateValue);
                    if (!isNaN(newMinDate.getTime())) {
                        // Compare date values to avoid unnecessary regeneration
                        const currentMinTime = this.minDate ? this.minDate.getTime() : null;
                        const newMinTime = newMinDate.getTime();
                        
                        if (currentMinTime !== newMinTime) {
                            this.minDate = newMinDate;
                            this.generateCalendar();
                        }
                    }
                } catch (e) {
                    // Invalid date, ignore
                }
            } else {
                // No min date restriction
                if (this.minDate !== null) {
                    this.minDate = null;
                    this.generateCalendar();
                }
            }
        },
        
        generateCalendar() {
            const firstDay = new Date(this.currentYear, this.currentMonth, 1);
            const lastDay = new Date(this.currentYear, this.currentMonth + 1, 0);
            const daysInMonth = lastDay.getDate();
            const startDayOfWeek = firstDay.getDay();
            
            const days = [];
            
            for (let i = 0; i < startDayOfWeek; i++) {
                days.push({ date: '', empty: true, enabled: false });
            }
            
            for (let date = 1; date <= daysInMonth; date++) {
                const currentDate = new Date(this.currentYear, this.currentMonth, date);
                const dateStr = this.formatDate(currentDate);
                
                const isToday = this.isToday(currentDate);
                const isEnabled = this.isDateEnabled(currentDate);
                const isSelected = this.selectedDate && dateStr === this.selectedDate;
                
                const isRangeStart = this.rangeStart && dateStr === this.rangeStart;
                const isRangeEnd = this.rangeEnd && dateStr === this.rangeEnd;
                const inRange = this.isInRange(currentDate);
                
                days.push({
                    date: date,
                    dateObj: currentDate,
                    dateStr: dateStr,
                    empty: false,
                    enabled: isEnabled,
                    isToday: isToday,
                    isSelected: isSelected,
                    isRangeStart: isRangeStart,
                    isRangeEnd: isRangeEnd,
                    inRange: inRange
                });
            }
            
            this.calendarDays = days;
        },
        
        selectDate(day) {
            if (!day.enabled || day.empty) return;
            
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
        
        canGoPrev() {
            if (!this.minDate) return true;
            const firstOfMonth = new Date(this.currentYear, this.currentMonth, 1);
            return firstOfMonth > this.minDate;
        },
        
        canGoNext() {
            if (!this.maxDate) return true;
            const lastOfMonth = new Date(this.currentYear, this.currentMonth + 1, 0);
            return lastOfMonth < this.maxDate;
        },
        
        isDateEnabled(date) {
            if (this.minDate && date < this.minDate) return false;
            if (this.maxDate && date > this.maxDate) return false;
            return true;
        },
        
        isToday(date) {
            const today = new Date();
            return date.getDate() === today.getDate() &&
                   date.getMonth() === today.getMonth() &&
                   date.getFullYear() === today.getFullYear();
        },
        
        isInRange(date) {
            if (!this.rangeStart || !this.rangeEnd) return false;
            const start = new Date(this.rangeStart);
            const end = new Date(this.rangeEnd);
            return date > start && date < end;
        },
        
        formatDate(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        },
        
        setRangeMode(start, end) {
            this.rangeStart = start;
            this.rangeEnd = end;
            this.generateCalendar();
        }
    };
}
</script>
