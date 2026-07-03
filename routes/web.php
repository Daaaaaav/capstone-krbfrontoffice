<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

// ========== Controllers ==========
use App\Http\Controllers\GuestbookScanController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\VehicleAttachmentController;
use App\Http\Controllers\GoogleAuthController;

// ========== Livewire Pages (Superadmin) ==========
use App\Livewire\Pages\Superadmin\Dashboard as SuperadminDashboard;
use App\Livewire\Pages\Superadmin\ReceptionistUsers as ReceptionistUsers;
use App\Livewire\Pages\Superadmin\RoomBookingStatistics as RoomBookingStatistics;
use App\Livewire\Pages\Superadmin\VehicleBookingStatistics as VehicleBookingStatistics;
use App\Livewire\Pages\Superadmin\DeliveryStatistics as DeliveryStatistics;
use App\Livewire\Pages\Superadmin\GuestbookStatistics as GuestbookStatistics;
use App\Livewire\Pages\Superadmin\AISecurityReports as AISecurityReports;
use App\Livewire\Pages\Superadmin\Settings as SuperadminSettings;
use App\Livewire\Pages\Superadmin\Help as SuperadminHelp;
use App\Livewire\Pages\Superadmin\Announcement;
use App\Livewire\Pages\Superadmin\Information;
use App\Livewire\Pages\Superadmin\Report;
use App\Livewire\Pages\Superadmin\Account as UserManagement;
use App\Livewire\Pages\Superadmin\Department as DepartmentPage;
use App\Livewire\Pages\Superadmin\Bookingroom as SuperadminBookingroom;
use App\Livewire\Pages\Superadmin\Ticketsupport as SuperadminTicketsupport;
use App\Livewire\Pages\Superadmin\Manageroom as Manageroom;
use App\Livewire\Pages\Superadmin\Managerequirement as Managerequirements;
use App\Livewire\Pages\Superadmin\Storage as StoragePage;
use App\Livewire\Pages\Superadmin\Vehicle as VehiclePage;
use App\Livewire\Pages\Superadmin\Packagemanagement as Packagemanagement;
use App\Livewire\Pages\Superadmin\Documentsmanagement as Documentsmanagement;
use App\Livewire\Pages\Superadmin\Guestbookmanagement as Guestbookmanagement;
use App\Livewire\Pages\Superadmin\Bookingvehicle as SuperadminBookingvehicle;
use App\Livewire\Pages\Superadmin\Adminmanagement as AdminmanagementPage;
use App\Livewire\Pages\Superadmin\WifiManagement as SuperadminWifiManagement;

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
Route::get('/home', function () {
    if (!Auth::check()) {
        return redirect()->route('login');
    }

    $user = Auth::user();
    $roleName = $user->role->name ?? $user->role ?? null;

    return match ($roleName) {
        'Superadmin'    => redirect()->route('superadmin.dashboard'),
        'Receptionist'  => redirect()->route('receptionist.dashboard'),
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
    Route::prefix('attachments')->group(function () {
        Route::post('/temp', [AttachmentController::class, 'tempUpload'])
            ->name('attachments.temp');
        Route::delete('/temp', [AttachmentController::class, 'deleteTemp'])
            ->name('attachments.temp.delete');
        Route::post('/finalize', [AttachmentController::class, 'finalizeTemp'])
            ->name('attachments.finalize');
    });

    // ---------- Superadmin routes ----------
    Route::middleware('is.superadmin')->group(function () {
        Route::get('/superadmin-dashboard', SuperadminDashboard::class)->name('superadmin.dashboard');
        Route::get('/receptionists', ReceptionistUsers::class)->name('superadmin.receptionists');
        Route::get('/room-bookings', RoomBookingStatistics::class)->name('superadmin.room');
        Route::get('/vehicle-bookings', VehicleBookingStatistics::class)->name('superadmin.vehicle');
        Route::get('/deliveries', DeliveryStatistics::class)->name('superadmin.delivery');
        Route::get('/guestbook', GuestbookStatistics::class)->name('superadmin.guestbook');
        Route::get('/lstm-predictions', \App\Livewire\Pages\Superadmin\LSTMPredictions::class)->name('superadmin.lstm-predictions');
        Route::get('/ai-security', AISecurityReports::class)->name('superadmin.ai-security');
        // Route::get('/weather', \App\Livewire\Pages\Superadmin\WeatherDashboard::class)->name('superadmin.weather');
        Route::get('/occupancy-forecasting', \App\Livewire\Pages\Superadmin\OccupancyForecasting::class)->name('superadmin.occupancy');
        // Resource Management
        Route::get('/manage-rooms', Manageroom::class)->name('superadmin.manageroom');
        Route::get('/manage-vehicles', VehiclePage::class)->name('superadmin.managevehicle');
        Route::get('/manage-storages', StoragePage::class)->name('superadmin.managestorage');
        Route::get('/superadmin-settings', SuperadminSettings::class)->name('superadmin.settings');
        Route::get('/superadmin-help', SuperadminHelp::class)->name('superadmin.help');
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