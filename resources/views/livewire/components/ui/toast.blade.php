<div x-data="{
        toasts: [],
        addToast(t) {
            t.id = crypto.randomUUID ? crypto.randomUUID() : Date.now() + Math.random();
            t.type = t.type || 'info';
            
            const textContent = ((t.title || '') + ' ' + (t.message || '')).toLowerCase();
            if (t.type === 'success' && (textContent.includes('hapus') || textContent.includes('delete'))) {
                t.type = 'delete';
            }

            t.message = t.message || '';
            t.title = t.title || '';
            t.duration = Number(t.duration ?? 3500);
            
            // Clear toasts to prevent stacking, keeping only the latest one
            this.toasts = [t];

            if (t.duration > 0) {
                setTimeout(() => this.removeToast(t.id), t.duration);
            }
        },
        removeToast(id) {
            this.toasts = this.toasts.filter(tt => tt.id !== id);
        },
        getToastClasses(type) {
            const base =
            'relative overflow-hidden rounded-full px-7 py-5 border shadow-2xl transition-all duration-500 mx-auto flex items-center gap-5 w-auto min-w-[400px] max-w-lg origin-top';
            const variants = {
                success: 'bg-emerald-600 text-white border-emerald-500/30',
                error:   'bg-rose-600 text-white border-rose-500/30',
                delete:  'bg-rose-600 text-white border-rose-500/30',
                warning: 'bg-amber-500 text-white border-amber-400/30',
                info:    'bg-blue-600 text-white border-blue-500/30',
                neutral: 'bg-[#4A2F24] text-[#CDDEA7] border-[#CDDEA7]/20'
            };
            return base + ' ' + (variants[type] || variants.neutral);
        },
        getIconClasses(type) {
            const base =
            'flex-shrink-0 w-12 h-12 rounded-full flex items-center justify-center font-bold text-lg border';
            const variants = {
                success: 'bg-white/20 text-white border-white/20',
                error:   'bg-white/20 text-white border-white/20',
                delete:  'bg-white/20 text-white border-white/20',
                warning: 'bg-white/20 text-white border-white/20',
                info:    'bg-white/20 text-white border-white/20',
                neutral: 'bg-[#CDDEA7]/10 text-[#CDDEA7] border-[#CDDEA7]/20'
            };
            return base + ' ' + (variants[type] || variants.neutral);
        },
        getIcon(type) {
            const icons = { success: '✓', error: '✕', delete: '✓', warning: '⚠', info: 'ⓘ', neutral: '•' };
            return icons[type] || icons.info;
        }
    }" x-on:toast.window="addToast($event.detail)"
    class="fixed inset-x-0 top-0 z-50 grid justify-items-center pt-6 px-4 pointer-events-none"
    aria-live="polite">

    <style>
        @keyframes dynamic-island-enter {
            0% {
                opacity: 0;
                transform: translateY(-20px) scale(0.85);
            }
            50% {
                opacity: 1;
                transform: translateY(5px) scale(1.02);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        @keyframes dynamic-island-leave {
            0% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
            100% {
                opacity: 0;
                transform: translateY(-20px) scale(0.85);
            }
        }
        .toast-enter-anim {
            animation: dynamic-island-enter 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
        }
        .toast-leave-anim {
            animation: dynamic-island-leave 0.3s cubic-bezier(0.4, 0, 1, 1) forwards;
        }
    </style>

    <template x-for="toast in toasts" :key="toast.id">
        <div x-transition:enter="toast-enter-anim"
            x-transition:leave="toast-leave-anim" 
            class="pointer-events-auto [grid-area:1/1]"
            :class="getToastClasses(toast.type)">
            
            <div :class="getIconClasses(toast.type)">
                <span x-text="getIcon(toast.type)"></span>
            </div>
            
            <div class="flex-1 min-w-0 pr-2">
                <h4 x-show="toast.title" x-text="toast.title"
                    class="font-bold text-[17px] leading-tight tracking-tight opacity-100"></h4>
                <p x-show="toast.message" x-text="toast.message" class="text-[15px] font-medium leading-relaxed opacity-90 mt-1"></p>
            </div>
            
            <button @click="removeToast(toast.id)"
                class="flex-shrink-0 w-10 h-10 flex items-center justify-center rounded-full opacity-60 hover:opacity-100 hover:bg-black/10 transition-colors duration-200 focus:outline-none"
                aria-label="Close notification">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
    </template>
</div>