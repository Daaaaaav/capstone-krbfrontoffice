<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

// ========== Controllers ==========
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\VehicleAttachmentController;

// ========== Livewire Pages (Manager) ==========
use App\Livewire\Pages\Manager\Dashboard as ManagerDashboard;
use App\Livewire\Pages\Manager\ReceptionistUsers as ReceptionistUsers;
use App\Livewire\Pages\Manager\RoomBookingStatistics as RoomBookingStatistics;
use App\Livewire\Pages\Manager\VehicleBookingStatistics as VehicleBookingStatistics;
use App\Livewire\Pages\Manager\DeliveryStatistics as DeliveryStatistics;
use App\Livewire\Pages\Manager\GuestbookStatistics as GuestbookStatistics;
use App\Livewire\Pages\Manager\AISecurityReports as AISecurityReports;
use App\Livewire\Pages\Manager\Settings as ManagerSettings;
use App\Livewire\Pages\Manager\Help as ManagerHelp;
use App\Livewire\Pages\Manager\Manageroom as Manageroom;
use App\Livewire\Pages\Manager\Storage as StoragePage;
use App\Livewire\Pages\Manager\Vehicle as VehiclePage;

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
        'Manager', 'Admin', 'Superadmin' => redirect()->route('manager.dashboard'),
        'Receptionist'  => redirect()->route('receptionist.dashboard'),
        default         => abort(403, 'Unauthorized access: Unknown role.'),
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

    // ---------- Attachments API (Local Storage) ----------
    Route::prefix('attachments')->group(function () {
        Route::post('/temp', [AttachmentController::class, 'tempUpload'])
            ->name('attachments.temp');
        Route::delete('/temp', [AttachmentController::class, 'deleteTemp'])
            ->name('attachments.temp.delete');
        Route::post('/finalize', [AttachmentController::class, 'finalizeTemp'])
            ->name('attachments.finalize');
    });

    // ---------- Notifications UI (Static View) ----------
    Route::get('/notifications', function () {
        return view('pages.notifications');
    })->name('notifications.index');

    // ---------- Manager routes ----------
    Route::middleware('is.manager')->group(function () {
        Route::get('/manager-dashboard', ManagerDashboard::class)->name('manager.dashboard');
        Route::get('/receptionists', ReceptionistUsers::class)->name('manager.receptionists');
        Route::get('/room-bookings', RoomBookingStatistics::class)->name('manager.room');
        Route::get('/vehicle-bookings', VehicleBookingStatistics::class)->name('manager.vehicle');
        Route::get('/deliveries', DeliveryStatistics::class)->name('manager.delivery');
        Route::get('/guestbook', GuestbookStatistics::class)->name('manager.guestbook');
        Route::get('/lstm-predictions', \App\Livewire\Pages\Manager\LSTMPredictions::class)->name('manager.lstm-predictions');
        Route::get('/ai-security', AISecurityReports::class)->name('manager.ai-security');
        Route::get('/occupancy-forecasting', \App\Livewire\Pages\Manager\OccupancyForecasting::class)->name('manager.occupancy');
        Route::get('/manage-rooms', Manageroom::class)->name('manager.manageroom');
        Route::get('/manage-vehicles', VehiclePage::class)->name('manager.managevehicle');
        Route::get('/manage-storages', StoragePage::class)->name('manager.managestorage');
        Route::get('/manager-settings', ManagerSettings::class)->name('manager.settings');
        Route::get('/manager-help', ManagerHelp::class)->name('manager.help');
    });

    // ---------- Receptionist routes ----------
    Route::middleware('is.receptionist')->group(function () {
        Route::get('/receptionist-dashboard', ReceptionistDashboard::class)->name('receptionist.dashboard');
        Route::get('/receptionist-guestbook', Guestbook::class)->name('receptionist.guestbook');
        Route::get('/receptionist-guestbookcheckout/{guestbookId}', \App\Livewire\Pages\Receptionist\GuestbookCheckout::class)->name('receptionist.guestbook.checkout');
        Route::get('/receptionist-meetingschedule', MeetingSchedule::class)->name('receptionist.schedule');
        Route::get('/receptionist-document', Documents::class)->name('receptionist.documents');
        Route::get('/receptionist-package', ReceptionistPackage::class)->name('receptionist.package');
        Route::get('/receptionist-bookings', BookingsApproval::class)->name('receptionist.bookings');
        Route::get('/receptionist-roomapproval', RoomApproval::class)->name('receptionist.roomapproval');
        Route::get('/receptionist-bookinghistory', BookingHistory::class)->name('receptionist.bookinghistory');
        Route::get('/receptionist-guestbookhistory', GuestbookHistory::class)->name('receptionist.guestbookhistory');
        Route::get('/receptionist-guestbookstatus', GuestbookStatus::class)->name('receptionist.guestbookstatus');
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