@php
$routeName = request()->route() ? request()->route()->getName() : '';
$path = request()->path();

// Predefined routes mapping
$breadcrumbs = [];

// Home / Dashboard base
if (str_starts_with($routeName, 'manager.')) {
    $breadcrumbs[] = ['label' => __('app.dashboard'), 'url' => route('manager.dashboard')];
} elseif (str_starts_with($routeName, 'receptionist.')) {
    $breadcrumbs[] = ['label' => __('app.home'), 'url' => route('receptionist.dashboard')];
} else {
    $breadcrumbs[] = ['label' => __('app.home'), 'url' => '/home'];
}

// Map sub-pages
$routeMappings = [
    // Receptionist Page Mappings
    'receptionist.schedule' => [
        ['label' => __('app.room_management')],
        ['label' => __('app.booking_room'), 'url' => route('receptionist.schedule')]
    ],
    'receptionist.bookings' => [
        ['label' => __('app.room_management')],
        ['label' => __('app.room_book_approval'), 'url' => route('receptionist.bookings')]
    ],
    'receptionist.bookinghistory' => [
        ['label' => __('app.room_management')],
        ['label' => __('app.booking_history'), 'url' => route('receptionist.bookinghistory')]
    ],
    'receptionist.bookingvehicle' => [
        ['label' => __('app.vehicle_management')],
        ['label' => __('app.vehicle_book'), 'url' => route('receptionist.bookingvehicle')]
    ],
    'receptionist.vehiclestatus' => [
        ['label' => __('app.vehicle_management')],
        ['label' => __('app.vehicle_status_menu'), 'url' => route('receptionist.vehiclestatus')]
    ],
    'receptionist.vehicleshistory' => [
        ['label' => __('app.vehicle_management')],
        ['label' => __('app.vehicle_history'), 'url' => route('receptionist.vehicleshistory')]
    ],
    'receptionist.guestbook' => [
        ['label' => __('app.guest_management')],
        ['label' => __('app.guestbook'), 'url' => route('receptionist.guestbook')]
    ],
    'receptionist.guestbookhistory' => [
        ['label' => __('app.guest_management')],
        ['label' => __('app.guestbook_history'), 'url' => route('receptionist.guestbookhistory')]
    ],
    'receptionist.docpackform' => [
        ['label' => __('app.docpac_management')],
        ['label' => __('app.docpac_form'), 'url' => route('receptionist.docpackform')]
    ],
    'receptionist.docpackstatus' => [
        ['label' => __('app.docpac_management')],
        ['label' => __('app.docpac_status'), 'url' => route('receptionist.docpackstatus')]
    ],
    'receptionist.docpackhistory' => [
        ['label' => __('app.docpac_management')],
        ['label' => __('app.docpac_history'), 'url' => route('receptionist.docpackhistory')]
    ],
    'receptionist.settings' => [
        ['label' => __('app.settings'), 'url' => route('receptionist.settings')]
    ],
    'receptionist.help' => [
        ['label' => __('app.help'), 'url' => route('receptionist.help')]
    ],

    // Manager Page Mappings
    'manager.receptionists' => [
        ['label' => __('app.user_management')],
        ['label' => __('app.receptionists'), 'url' => route('manager.receptionists')]
    ],
    'manager.room' => [
        ['label' => __('app.analytics')],
        ['label' => __('app.room_bookings'), 'url' => route('manager.room')]
    ],
    'manager.vehicle' => [
        ['label' => __('app.analytics')],
        ['label' => __('app.vehicle_bookings'), 'url' => route('manager.vehicle')]
    ],
    'manager.delivery' => [
        ['label' => __('app.analytics')],
        ['label' => __('app.deliveries'), 'url' => route('manager.delivery')]
    ],
    'manager.guestbook' => [
        ['label' => __('app.analytics')],
        ['label' => __('app.guestbook'), 'url' => route('manager.guestbook')]
    ],
    'manager.lstm-predictions' => [
        ['label' => __('app.ai_security')],
        ['label' => __('app.visitor_predictions'), 'url' => route('manager.lstm-predictions')]
    ],
    'manager.occupancy' => [
        ['label' => __('app.ai_security')],
        ['label' => __('app.occupancy_forecast'), 'url' => route('manager.occupancy')]
    ],
    'manager.ai-security' => [
        ['label' => __('app.ai_security')],
        ['label' => __('app.security_reports'), 'url' => route('manager.ai-security')]
    ],
    'manager.settings' => [
        ['label' => __('app.settings'), 'url' => route('manager.settings')]
    ],
    'manager.help' => [
        ['label' => __('app.help'), 'url' => route('manager.help')]
    ],
];

if (isset($routeMappings[$routeName])) {
    $breadcrumbs = array_merge($breadcrumbs, $routeMappings[$routeName]);
} else {
    // Fallback: parse URL path segments
    $segments = request()->segments();
    foreach ($segments as $index => $segment) {
        if ($index === 0 && ($segment === 'manager-dashboard' || $segment === 'receptionist-dashboard')) {
            continue;
        }
        $label = ucwords(str_replace(['-', '_'], ' ', $segment));
        $breadcrumbs[] = [
            'label' => $label,
            'url' => null
        ];
    }
}
@endphp

<nav class="flex text-sm font-medium font-sans select-none" aria-label="Breadcrumb">
    <ol class="inline-flex items-center space-x-1 md:space-x-2">
        @foreach($breadcrumbs as $index => $item)
            <li class="inline-flex items-center">
                @if($index > 0)
                    <svg class="w-3.5 h-3.5 text-muted-foreground/60 mx-1 md:mx-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                    </svg>
                @endif
                
                @if($loop->last)
                    <span class="text-foreground font-semibold tracking-tight">{{ $item['label'] }}</span>
                @elseif(isset($item['url']))
                    <a href="{{ $item['url'] }}" class="text-muted-foreground hover:text-primary transition-colors duration-200">
                        {{ $item['label'] }}
                    </a>
                @else
                    <span class="text-muted-foreground/80">{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
