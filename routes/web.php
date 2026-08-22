<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

use App\Http\Controllers\GuestbookScanController;
// use App\Http\Controllers\AttachmentController;
// use App\Http\Controllers\VehicleAttachmentController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\ChatExportController;

use App\Livewire\Pages\Manager\Dashboard as ManagerDashboard;
use App\Livewire\Pages\Manager\ReceptionistUsers as ReceptionistUsers;
use App\Livewire\Pages\Manager\RoomBookingStatistics as RoomBookingStatistics;
use App\Livewire\Pages\Manager\VehicleBookingStatistics as VehicleBookingStatistics;
use App\Livewire\Pages\Manager\DeliveryStatistics as DeliveryStatistics;
use App\Livewire\Pages\Manager\GuestbookStatistics as GuestbookStatistics;
use App\Livewire\Pages\Manager\AISecurityReports as AISecurityReports;
use App\Livewire\Pages\Manager\Settings as ManagerSettings;
use App\Livewire\Pages\Manager\Help as ManagerHelp;
use App\Livewire\Pages\Manager\Announcement;
use App\Livewire\Pages\Manager\Information;
use App\Livewire\Pages\Manager\Report;
use App\Livewire\Pages\Manager\Account as UserManagement;
use App\Livewire\Pages\Manager\Department as DepartmentPage;
use App\Livewire\Pages\Manager\Bookingroom as ManagerBookingroom;
use App\Livewire\Pages\Manager\Ticketsupport as ManagerTicketsupport;
use App\Livewire\Pages\Manager\Manageroom as Manageroom;
use App\Livewire\Pages\Manager\Managerequirement as Managerequirements;
use App\Livewire\Pages\Manager\Storage as StoragePage;
use App\Livewire\Pages\Manager\Vehicle as VehiclePage;
use App\Livewire\Pages\Manager\Packagemanagement as Packagemanagement;
use App\Livewire\Pages\Manager\Documentsmanagement as Documentsmanagement;
use App\Livewire\Pages\Manager\Guestbookmanagement as Guestbookmanagement;
use App\Livewire\Pages\Manager\Bookingvehicle as ManagerBookingvehicle;
use App\Livewire\Pages\Manager\WifiManagement as ManagerWifiManagement;
use App\Livewire\Pages\Manager\PriorityRoomBooking as ManagerPriorityRoomBooking;
use App\Livewire\Pages\Manager\PriorityVehicleBooking as ManagerPriorityVehicleBooking;
use App\Livewire\Pages\Manager\GuestbookForm as ManagerGuestbookForm;
use App\Livewire\Pages\Manager\DocPackForm as ManagerDocPackForm;
use App\Livewire\Pages\Manager\DocPackStatus as ManagerDocPackStatus;

// ========== Livewire Pages (Receptionist) ==========
use App\Livewire\Pages\Receptionist\Dashboard as ReceptionistDashboard;
use App\Livewire\Pages\Receptionist\Documents as Documents;
use App\Livewire\Pages\Receptionist\Package as ReceptionistPackage;
use App\Livewire\Pages\Receptionist\Guestbook as Guestbook;
use App\Livewire\Pages\Receptionist\MeetingSchedule as MeetingSchedule;
use App\Livewire\Pages\Receptionist\BookingsApproval;
use App\Livewire\Pages\Receptionist\RoomApproval;
use App\Livewire\Pages\Receptionist\BookingHistory;
use App\Livewire\Pages\Receptionist\GuestbookHistory;
use App\Livewire\Pages\Receptionist\GuestbookStatus;
use App\Livewire\Pages\Receptionist\GuestbookCheckout;
use App\Livewire\Pages\Receptionist\DocPackHistory;
use App\Livewire\Pages\Receptionist\DocPackStatus;
use App\Livewire\Pages\Receptionist\DocPackForm;
use App\Livewire\Pages\Receptionist\Bookingvehicle;
use App\Livewire\Pages\Receptionist\Vehicleshistory;
use App\Livewire\Pages\Receptionist\Vehiclestatus as ReceptionistVehiclestatus;
use App\Livewire\Pages\Receptionist\Settings as ReceptionistSettings;
use App\Livewire\Pages\Receptionist\Help as ReceptionistHelp;

// ========== Auth Pages ==========
use App\Livewire\Pages\Auth\Login as LoginPage;
use App\Livewire\Pages\Auth\Register as RegisterPage;

// ========== Error ==========
use App\Livewire\Pages\Errors\error404 as Error404;

use App\Services\GoogleMeetService;

/*
|--------------------------------------------------------------------------
| Language Toggle
|--------------------------------------------------------------------------
*/
Route::get('/lang/{locale}', function (string $locale) {
    if (!in_array($locale, ['en', 'id'])) {
        abort(400);
    }
    session(['locale' => $locale]);
    return redirect()->back();
})->name('lang.switch');

/*
|--------------------------------------------------------------------------
| Guestbook QR scan (public – no auth required, token is the gate)
|--------------------------------------------------------------------------
*/
Route::get('/guestbook/scan/{token}', [GuestbookScanController::class, 'show'])
    ->name('guestbook.scan')
    ->where('token', '[a-f0-9]{64}');

Route::post('/guestbook/scan/{token}', [GuestbookScanController::class, 'submit'])
    ->name('guestbook.scan.submit')
    ->where('token', '[a-f0-9]{64}');

Route::get('/guestbook/qr/{token}', [GuestbookScanController::class, 'qrImage'])
    ->name('guestbook.qr.image')
    ->where('token', '[a-f0-9]{64}');

/*
|--------------------------------------------------------------------------
| Application Health Check Endpoint (Endpoint-Based Monitoring)
|--------------------------------------------------------------------------
*/
Route::get('/health', function () {
    return response()->json([
        'status'    => 'healthy',
        'service'   => 'KRB Application',
        'timestamp' => now()->toIso8601String(),
    ], 200, ['Content-Type' => 'application/json']);
});

/*
|--------------------------------------------------------------------------
| Root: redirect to login
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return redirect()->route('login');
});

// Lightweight endpoint to refresh the CSRF token for long-lived pages
Route::get('/csrf-token-refresh', function () {
    return response()->json(['token' => csrf_token()]);
})->middleware('web');

/*
|--------------------------------------------------------------------------
| Home: redirect authenticated users to their dashboard
|--------------------------------------------------------------------------
*/
Route::get('/home', function (Request $request) {
    if (!Auth::check()) {
        return redirect()->route('login');
    }

    $user = Auth::user();
    $roleName = $user->role->name ?? $user->role ?? null;

    return match ($roleName) {
        'Manager'       => redirect()->route('manager.dashboard'),
        'Receptionist'  => redirect()->route('receptionist.dashboard'),
        'IT Officer'    => redirect()->route('it-officer.dashboard'),
        default         => (function () use ($request) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->withErrors(['email' => 'Your account role is not authorized. Please contact your administrator.']);
        })(),
    };
})->name('home');

/*
|--------------------------------------------------------------------------
| Google OAuth (Real Implementation)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {
    Route::get('/google/auth', [\App\Http\Controllers\GoogleAuthController::class, 'auth'])->name('google.auth');
    Route::get('/google/callback', [\App\Http\Controllers\GoogleAuthController::class, 'callback'])->name('google.callback');
});

/*
|--------------------------------------------------------------------------
| Guest only
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', LoginPage::class)->name('login');
    Route::post('/login', function () {
        return redirect()->route('login');
    })->middleware('throttle:5,1');
    Route::get('/register', RegisterPage::class)->name('register');
});

/*
|--------------------------------------------------------------------------
| Auth only
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {

    // ---------- Delivery image serving (works without storage:link) ----------
    Route::get('/delivery-image/{path}', function (string $path) {
        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        $fullPath = 'images/deliveries/' . $path;

        if (!$disk->exists($fullPath)) {
            abort(404);
        }

        return response()->file(
            $disk->path($fullPath),
            ['Cache-Control' => 'public, max-age=86400']
        );
    })->where('path', '.*')->name('delivery.image');

    // ---------- Attachments API (Local Storage) ----------
    // Route::prefix('attachments')->group(function () {
    //     Route::post('/temp', [AttachmentController::class, 'tempUpload'])
    //         ->name('attachments.temp');
    //     Route::delete('/temp', [AttachmentController::class, 'deleteTemp'])
    //         ->name('attachments.temp.delete');
    //     Route::post('/finalize', [AttachmentController::class, 'finalizeTemp'])
    //         ->name('attachments.finalize');
    // });

    // ---------- Notifications UI (Static View) ----------
    Route::get('/notifications', function () {
        return view('pages.notifications');
    })->name('notifications.index');

    // ---------- Chat export (manager chatbot → PDF / CSV) ----------
    Route::middleware('is.manager')->group(function () {
        Route::get('/chat-export/pdf', [ChatExportController::class, 'exportPdf'])->name('chat.export.pdf');
        Route::get('/chat-export/csv', [ChatExportController::class, 'exportCsv'])->name('chat.export.csv');
    });

    // ---------- Manager routes ----------
    Route::middleware('is.manager')->group(function () {
        Route::get('/manager-dashboard', ManagerDashboard::class)->name('manager.dashboard');
        Route::get('/room-bookings', RoomBookingStatistics::class)->name('manager.room');
        Route::get('/vehicle-bookings', VehicleBookingStatistics::class)->name('manager.vehicle');
        Route::get('/deliveries', DeliveryStatistics::class)->name('manager.delivery');
        Route::get('/guestbook', GuestbookStatistics::class)->name('manager.guestbook');
        Route::get('/lstm-predictions', \App\Livewire\Pages\Manager\LSTMPredictions::class)->name('manager.lstm-predictions');
        Route::get('/ai-security', AISecurityReports::class)->name('manager.ai-security');
        Route::get('/occupancy-forecasting', \App\Livewire\Pages\Manager\OccupancyForecasting::class)->name('manager.occupancy');
        Route::get('/manager-settings', ManagerSettings::class)->name('manager.settings');
        Route::get('/manager-help', ManagerHelp::class)->name('manager.help');
        // Priority bookings & operational forms
        Route::get('/manager-priority-room', ManagerPriorityRoomBooking::class)->name('manager.priority-room');
        Route::get('/manager-priority-room-status', \App\Livewire\Pages\Manager\PriorityRoomBookingStatus::class)->name('manager.priority-room-status');
        Route::get('/manager-priority-room-history', \App\Livewire\Pages\Manager\PriorityRoomBookingHistory::class)->name('manager.priority-room-history');
        Route::get('/manager-priority-vehicle', ManagerPriorityVehicleBooking::class)->name('manager.priority-vehicle');
        Route::get('/manager-priority-vehicle-status', \App\Livewire\Pages\Manager\PriorityVehicleBookingStatus::class)->name('manager.priority-vehicle-status');
        Route::get('/manager-priority-vehicle-history', \App\Livewire\Pages\Manager\PriorityVehicleBookingHistory::class)->name('manager.priority-vehicle-history');
        Route::get('/manager-guestbook-form', ManagerGuestbookForm::class)->name('manager.guestbook-form');
        Route::get('/manager-docpack-form', ManagerDocPackForm::class)->name('manager.docpack-form');
        Route::get('/manager-docpack-status', fn() => redirect()->route('manager.docpack-form'))->name('manager.docpack-status');
    });

    // ---------- IT Officer routes ----------
    Route::middleware('is.it.officer')->group(function () {
        Route::get('/it-officer-dashboard', \App\Livewire\Pages\ItOfficer\Dashboard::class)->name('it-officer.dashboard');
        Route::get('/it-officer-receptionists', \App\Livewire\Pages\ItOfficer\ReceptionistUsers::class)->name('it-officer.receptionists');
        Route::get('/it-officer-managers', \App\Livewire\Pages\ItOfficer\ManagerUsers::class)->name('it-officer.managers');
        Route::get('/it-officer-users-per-department', \App\Livewire\Pages\ItOfficer\UsersPerDepartment::class)->name('it-officer.users-per-department');
        Route::get('/it-officer-manage-rooms', \App\Livewire\Pages\ItOfficer\Manageroom::class)->name('it-officer.manageroom');
        Route::get('/it-officer-manage-vehicles', \App\Livewire\Pages\ItOfficer\Vehicle::class)->name('it-officer.managevehicle');
        Route::get('/it-officer-manage-storages', \App\Livewire\Pages\ItOfficer\Storage::class)->name('it-officer.managestorage');
        Route::get('/it-officer-id-types', \App\Livewire\Pages\ItOfficer\IdTypes::class)->name('it-officer.id-types');
        Route::get('/it-officer-visitor-lanyards', \App\Livewire\Pages\ItOfficer\VisitorLanyards::class)->name('it-officer.visitor-lanyards');
        Route::get('/it-officer-requirements', \App\Livewire\Pages\ItOfficer\Requirements::class)->name('it-officer.requirements');
        Route::get('/it-officer-lstm-predictions', \App\Livewire\Pages\ItOfficer\LSTMPredictions::class)->name('it-officer.lstm-predictions');
        Route::get('/it-officer-occupancy', \App\Livewire\Pages\ItOfficer\OccupancyForecasting::class)->name('it-officer.occupancy');
        Route::get('/it-officer-ai-security', \App\Livewire\Pages\ItOfficer\AISecurityReports::class)->name('it-officer.ai-security');
        Route::get('/it-officer-settings', \App\Livewire\Pages\ItOfficer\Settings::class)->name('it-officer.settings');
        Route::get('/it-officer-help', \App\Livewire\Pages\ItOfficer\Help::class)->name('it-officer.help');
    });

    // ---------- Receptionist routes ----------
    Route::middleware('is.receptionist')->group(function () {
        Route::get('/receptionist-dashboard', ReceptionistDashboard::class)->name('receptionist.dashboard');
        Route::get('/receptionist-guestbook', Guestbook::class)->name('receptionist.guestbook');
        Route::get('/receptionist-meetingschedule', MeetingSchedule::class)->name('receptionist.schedule');
        Route::get('/receptionist-document', Documents::class)->name('receptionist.documents');
        Route::get('/receptionist-package', ReceptionistPackage::class)->name('receptionist.package');
        Route::get('/receptionist-bookings', BookingsApproval::class)->name('receptionist.bookings');
        Route::get('/receptionist-roomapproval', RoomApproval::class)->name('receptionist.roomapproval');
        Route::get('/receptionist-bookinghistory', BookingHistory::class)->name('receptionist.bookinghistory');
        Route::get('/receptionist-guestbookhistory', GuestbookHistory::class)->name('receptionist.guestbookhistory');
        Route::get('/receptionist-guestbookstatus', GuestbookStatus::class)->name('receptionist.guestbookstatus');
        Route::get('/receptionist-guestbook-checkout/{guestbookId}', GuestbookCheckout::class)->name('receptionist.guestbook.checkout');
        Route::post('/api/guestbook/checkout-scan', [GuestbookScanController::class, 'checkoutScan'])->name('guestbook.checkout.scan');
        Route::get('/receptionist-docpackhistory', DocPackHistory::class)->name('receptionist.docpackhistory');
        Route::get('/receptionist-docpackstatus', DocPackStatus::class)->name('receptionist.docpackstatus');
        Route::get('/receptionist-docpackform', DocPackForm::class)->name('receptionist.docpackform');
        route::get('/receptionist-bookingvehicle', Bookingvehicle::class)->name('receptionist.bookingvehicle');
        Route::get('/receptionist-vehicleshistory', Vehicleshistory::class)->name('receptionist.vehicleshistory');
        Route::get('/receptionist-vehiclestatus', ReceptionistVehiclestatus::class)->name('receptionist.vehiclestatus');
        Route::get('/receptionist-settings', ReceptionistSettings::class)->name('receptionist.settings');
        Route::get('/receptionist-help', ReceptionistHelp::class)->name('receptionist.help');
    });

    // ---------- Logout ----------
    Route::post('/logout', function (Request $request) {
        Auth::logout();
        $request->session()->forget('url.intended');
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    })->name('logout');

}); // end auth middleware group

/*
|--------------------------------------------------------------------------
| Fallback 404
|--------------------------------------------------------------------------
*/
Route::fallback(function () {
    abort(404);
});