<?php

namespace App\Livewire\Pages\Receptionist;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\Guestbook as GuestbookModel;

#[Layout('layouts.receptionist')]
#[Title('Guestbook Checkout Scanner')]
class GuestbookCheckout extends Component
{
    public int $guestbookId;

    // Passed to the view
    public ?string $guestName = null;
    public ?string $instansi = null;
    public ?string $keperluan = null;
    public ?string $jamIn = null;
    public ?string $date = null;
    public int $totalVisitors = 0;
    public int $scannedCount = 0;
    public string $qrStatus = 'pending';

    public function mount(int $guestbookId): void
    {
        $entry = GuestbookModel::where('guestbook_id', $guestbookId)
            ->where('company_id', Auth::user()?->company_id)
            ->firstOrFail();

        $this->guestbookId   = $entry->guestbook_id;
        $this->guestName     = $entry->name;
        $this->instansi      = $entry->instansi;
        $this->keperluan     = $entry->keperluan;
        $this->jamIn         = $entry->jam_in;
        $this->date          = $entry->date ? $entry->date->format('d M Y') : null;
        $this->totalVisitors = $entry->visitor_count;
        $this->scannedCount  = $entry->scannedQrCount();
        $this->qrStatus      = $entry->qr_status ?? 'pending';
    }

    public function render()
    {
        return view('livewire.pages.receptionist.guestbook-checkout');
    }
}
