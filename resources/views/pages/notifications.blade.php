@extends('layouts.receptionist', ['title' => 'Notifications'])

@section('content')
    <main class="px-4 sm:px-6 py-6 space-y-6">
        <x-page-header
                title="Notifications"
                subtitle="View all system alerts and messages" />

            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="p-8 text-center border-b border-gray-100">
                    <div class="w-16 h-16 bg-emerald-50 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-4">
                        <x-heroicon-o-bell-alert class="w-8 h-8" />
                    </div>
                    <h3 class="text-lg font-bold text-gray-900">You're all caught up!</h3>
                    <p class="text-sm text-gray-500 mt-2">There are no new notifications at this time.</p>
                </div>

                <div class="divide-y divide-gray-100">
                    {{-- Example Static Notification Items --}}
                    <div class="p-4 sm:p-5 flex gap-4 hover:bg-gray-50/50 transition cursor-pointer group">
                        <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                            <x-heroicon-o-information-circle class="w-5 h-5" />
                        </div>
                        <div class="flex-1">
                            <div class="flex justify-between items-start mb-1">
                                <h4 class="text-sm font-semibold text-gray-900">System Update</h4>
                                <span class="text-[11px] text-gray-400 font-medium bg-gray-100 px-2 py-0.5 rounded-full">New</span>
                            </div>
                            <p class="text-sm text-gray-600 mt-0.5">The application has been updated with new features and performance improvements.</p>
                            <span class="text-[11px] text-gray-400 mt-1.5 block font-medium">Just now</span>
                        </div>
                    </div>

                    <div class="p-4 sm:p-5 flex gap-4 hover:bg-gray-50/50 transition cursor-pointer opacity-75">
                        <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                            <x-heroicon-o-check-circle class="w-5 h-5" />
                        </div>
                        <div class="flex-1">
                            <h4 class="text-sm font-semibold text-gray-900">Vehicle Booking Approved</h4>
                            <p class="text-sm text-gray-600 mt-0.5">Your request for Avanza B 1234 CD has been approved.</p>
                            <span class="text-[11px] text-gray-400 mt-1.5 block font-medium">2 days ago</span>
                        </div>
                    </div>

                    <div class="p-4 sm:p-5 flex gap-4 hover:bg-gray-50/50 transition cursor-pointer opacity-75">
                        <div class="w-10 h-10 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center shrink-0">
                            <x-heroicon-o-clock class="w-5 h-5" />
                        </div>
                        <div class="flex-1">
                            <h4 class="text-sm font-semibold text-gray-900">Late Return Reminder</h4>
                            <p class="text-sm text-gray-600 mt-0.5">Please remember to return the vehicle keys to the receptionist.</p>
                            <span class="text-[11px] text-gray-400 mt-1.5 block font-medium">3 days ago</span>
                        </div>
                    </div>
                </div>
            </div>
    </main>
@endsection
