<?php

$sidebarPath = __DIR__ . '/resources/views/livewire/components/partials/receptionist/sidebar.blade.php';

$content = file_get_contents($sidebarPath);

// 1. Remove x-data="{ search: '' }"
$content = str_replace('<div class="sidebar-root" x-data="{ search: \'\' }">', '<div class="sidebar-root">', $content);

// 2. Remove the search HTML block
$searchHtml = <<<'HTML'
            {{-- Search --}}
            <div class="sidebar-unified-search" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'opacity-100' : 'opacity-0 pointer-events-none'">
                <div class="relative w-full">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-white/40 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input x-model="search" type="text" placeholder="{{ __('app.search_modules') }}" class="sidebar-search-input w-full" tabindex="-1" />
                </div>
            </div>
HTML;
$content = str_replace($searchHtml, '', $content);

// 3. Remove x-show directives related to search
$content = preg_replace('/x-show="!search \|\| [^"]+" /', '', $content);
$content = preg_replace('/x-show="!search \|\| [^"]+"/', '', $content);

file_put_contents($sidebarPath, $content);
echo "Done\n";
