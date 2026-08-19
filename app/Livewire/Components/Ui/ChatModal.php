<?php

namespace App\Livewire\Components\Ui;

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\AI\AIService;
use App\Services\AI\PromptBuilder;
use App\Services\AI\BookingDraftService;
use App\Services\AI\ContextRouter;
use App\Services\AI\DirectBookingService;
use App\Services\AI\ToolDispatcher;
use App\Models\AiChatSession;
use App\Models\AiChatMessage;
use App\Models\Room;
use App\Models\Vehicle;

class ChatModal extends Component
{
    public bool   $isOpen    = false;
    public string $message   = '';
    public bool   $isLoading = false;
    public string $userRole  = 'receptionist';
    public string $panel     = 'chat';

    public array $messages        = [];
    public ?int  $activeSessionId = null;

    public array $conversationHistory = [];

    public array $bookingDraft = [];

   
    public array $contextMemory = [];

    public array  $historySessions     = [];
    public ?int   $viewingSessionId    = null;
    public array  $viewingMessages     = [];
    public string $viewingSessionTitle = '';
    public string $viewingSessionDate  = '';

    public function mount(): void
    {
        $this->userRole     = $this->resolveUserRole();
        $this->bookingDraft = app(BookingDraftService::class)->emptyDraft();
        $this->contextMemory = $this->emptyMemory();

        AiChatSession::where('user_id', Auth::id())
            ->whereNull('ended_at')
            ->whereNotNull('title')
            ->update(['ended_at' => now()]);
    }

    #[On('openChatModal')]
    public function openModal(): void
    {
        $this->isOpen = true;
        $this->panel  = 'chat';

        if (empty($this->messages)) {
            $this->seedGreeting();
        }
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
    }

    public function showHistory(): void
    {
        $this->loadHistorySessions();
        $this->panel = 'history';
    }

    public function showChat(): void
    {
        $this->panel = 'chat';
        $this->dispatch('chat-scroll-bottom');
    }

    public function viewSession(int $sessionId): void
    {
        $session = AiChatSession::with('messages')
            ->where('user_id', Auth::id())->find($sessionId);

        if (! $session) return;

        $this->viewingSessionId    = $sessionId;
        $this->viewingSessionTitle = $session->title ?? 'Untitled session';
        $this->viewingSessionDate  = $session->started_at->format('d M Y, H:i');

        $this->viewingMessages = $session->messages->map(fn(AiChatMessage $m) => [
            'role'            => $m->role,
            'text'            => $m->text,
            'booking_prefill' => is_array($m->booking_prefill) ? ($m->booking_prefill['room']    ?? $m->booking_prefill) : null,
            'vehicle_prefill' => is_array($m->booking_prefill) ? ($m->booking_prefill['vehicle'] ?? null) : null,
            'sent_at'         => $m->sent_at->format('H:i'),
        ])->values()->toArray();

        $this->panel = 'session';
    }

    public function backToHistory(): void
    {
        $this->viewingSessionId = null;
        $this->viewingMessages  = [];
        $this->panel            = 'history';
    }

    public function deleteSession(int $sessionId): void
    {
        AiChatSession::where('user_id', Auth::id())->where('id', $sessionId)->delete();
        $this->loadHistorySessions();
        if ($this->viewingSessionId === $sessionId) {
            $this->backToHistory();
        }
    }

    public function restoreSession(int $sessionId): void
    {
        $session = AiChatSession::with('messages')
            ->where('user_id', Auth::id())->find($sessionId);

        if (! $session) return;

        $this->archiveCurrentSession();
        $this->activeSessionId = $session->id;
        $this->messages = $session->messages->map(fn(AiChatMessage $m) => [
            'role'            => $m->role,
            'text'            => $m->text,
            'booking_prefill' => is_array($m->booking_prefill) ? ($m->booking_prefill['room']    ?? $m->booking_prefill) : null,
            'vehicle_prefill' => is_array($m->booking_prefill) ? ($m->booking_prefill['vehicle'] ?? null) : null,
            'sent_at'         => $m->sent_at->format('H:i'),
        ])->values()->toArray();

        $this->conversationHistory = [];
        foreach ($this->messages as $msg) {
            $this->appendToHistory($msg['role'] === 'user' ? 'user' : 'assistant', $msg['text']);
        }

        $this->panel = 'chat';
        $this->dispatch('chat-scroll-bottom');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Quick Actions (Receptionist & Manager)
    // ─────────────────────────────────────────────────────────────────────────

    public function quickBookRoom(): void
    {
        $this->ensureSession();
        $this->bookingDraft = app(BookingDraftService::class)->startRoomDraft($this->bookingDraft);

        $isId = app()->getLocale() === 'id';
        $text = $isId
            ? "Baik! Mari buat pemesanan ruangan. Apa judul rapat/keperluan, ruangan yang diinginkan, tanggal, dan waktunya?"
            : "Sure! Let's create a room booking. What is the meeting title/purpose, desired room, date, start time, and end time?";

        $this->messages[] = [
            'role'            => 'assistant',
            'text'            => $text,
            'booking_prefill' => null,
            'vehicle_prefill' => null,
            'sent_at'         => now()->format('H:i'),
        ];
        $this->persistMessage('assistant', $text, null, null);
        $this->appendToHistory('assistant', $text);
        $this->isOpen = true;
        $this->panel  = 'chat';
        $this->dispatch('chat-scroll-bottom');
    }

    public function quickBookVehicle(): void
    {
        $this->ensureSession();
        $this->bookingDraft = app(BookingDraftService::class)->startVehicleDraft($this->bookingDraft);

        $isId = app()->getLocale() === 'id';
        $text = $isId
            ? "Baik! Mari buat pemesanan kendaraan. Siapa nama peminjam, kendaraan apa yang dibutuhkan, tanggal pemakaian, waktu, tujuan, dan keperluannya?"
            : "Sure! Let's create a vehicle booking. What is the borrower's name, vehicle required, date, time, destination, and purpose?";

        $this->messages[] = [
            'role'            => 'assistant',
            'text'            => $text,
            'booking_prefill' => null,
            'vehicle_prefill' => null,
            'sent_at'         => now()->format('H:i'),
        ];
        $this->persistMessage('assistant', $text, null, null);
        $this->appendToHistory('assistant', $text);
        $this->isOpen = true;
        $this->panel  = 'chat';
        $this->dispatch('chat-scroll-bottom');
    }

    public function quickAnalytics(string $type): void
    {
        $isId = app()->getLocale() === 'id';
        $query = match ($type) {
            'guestbook' => $isId
                ? 'Tampilkan ringkasan analitik buku tamu dan pengunjung termasuk total kunjungan, tren, dan pengunjung terkini.'
                : 'Show guestbook and visitor analytics overview including visitor counts, recent visitors, and trends.',
            'room' => $isId
                ? 'Tampilkan ringkasan analitik pemesanan ruangan termasuk total booking, utilisasi, status disetujui, pending, dan pembatalan.'
                : 'Show room booking analytics summary including total room bookings, utilization, approved, pending, and cancelled bookings.',
            'vehicle' => $isId
                ? 'Tampilkan ringkasan analitik pemesanan kendaraan termasuk total booking, utilisasi, status disetujui, pending, selesai, dan pembatalan.'
                : 'Show vehicle booking analytics summary including total vehicle bookings, vehicle utilization, approved, pending, completed, and cancelled bookings.',
            'delivery' => $isId
                ? 'Tampilkan ringkasan analitik pengiriman dokumen dan paket termasuk total dokumen/paket diterima, dikirim, status pending, dan tersimpan.'
                : 'Show document and package delivery analytics summary including packages/documents received, sent, pending, and completed deliveries.',
            default => 'Show an analytics overview.',
        };

        $this->isOpen = true;
        $this->panel  = 'chat';
        $this->sendMessageText($query);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Send Message
    // ─────────────────────────────────────────────────────────────────────────

    public function sendMessage(): void
    {
        $this->sendMessageText($this->message);
    }

    public function sendMessageText(string $input): void
    {
        $text = trim($input);
        if ($text === '' || $this->isLoading) return;

        $this->ensureMemoryDefaults();

        $this->ensureSession();
        $sentAt = now()->format('H:i');

        $this->messages[] = [
            'role'            => 'user',
            'text'            => $text,
            'booking_prefill' => null,
            'vehicle_prefill' => null,
            'sent_at'         => $sentAt,
        ];
        $this->message   = '';
        $this->isLoading = true;

        $this->persistMessage('user', $text, null, null);
        $this->appendToHistory('user', $text);

        $reply    = 'Sorry, something went wrong. Please try again.';
        $prefill  = null;
        $vprefill = null;

        try {
            if ($this->userRole() === 'manager') {
                [$reply] = $this->callManagerAI($text);
            } else {
                [$reply, $prefill, $vprefill] = $this->callReceptionistAI($text);
            }
        } catch (\Throwable $e) {
            Log::error('ChatModal: AI call failed', [
                'stage'     => $this->userRole() === 'manager' ? 'manager_ai_call' : 'receptionist_ai_call',
                'class'     => get_class($e),
                'error'     => $e->getMessage(),
                'file'      => $e->getFile() . ':' . $e->getLine(),
                'user_role' => $this->userRole(),
            ]);
        }

        $this->messages[] = [
            'role'            => 'assistant',
            'text'            => $reply,
            'booking_prefill' => $prefill,
            'vehicle_prefill' => $vprefill,
            'sent_at'         => now()->format('H:i'),
        ];
        $this->isLoading = false;

        $this->persistMessage('assistant', $reply, $prefill, $vprefill);
        $this->appendToHistory('assistant', $reply);

        $this->dispatch('chat-scroll-bottom');
    }

    public function clearChat(): void
    {
        $this->archiveCurrentSession();
        $this->messages            = [];
        $this->message             = '';
        $this->isLoading           = false;
        $this->activeSessionId     = null;
        $this->conversationHistory = [];
        $this->bookingDraft        = app(BookingDraftService::class)->emptyDraft();
        $this->contextMemory       = $this->emptyMemory();
        $this->panel               = 'chat';
        $this->seedGreeting();
    }

    private function callManagerAI(string $userMessage): array
    {
        $companyId = Auth::user()->company_id;

        $router  = app(ContextRouter::class);
        $context = $router->route($userMessage, $companyId, 'manager', $this->getRecentHistory());

        $domains = $router->detectDomains($userMessage, 'manager', $this->getRecentHistory());
        $this->contextMemory['active_domains'] = $domains;

        $builder      = app(PromptBuilder::class);
        $systemPrompt = $builder->managerSystemPrompt($context);
        $history      = $this->getRecentHistory(exclude: 'last');

        $ai    = app(AIService::class);
        $raw   = $ai->chat($systemPrompt, $userMessage, $history);
        $reply = $this->stripThinkBlocks($raw);

        return [$reply];
    }

    private function callReceptionistAI(string $userMessage): array
    {
        $companyId    = Auth::user()->company_id;
        $builder      = app(PromptBuilder::class);
        $draftService = app(BookingDraftService::class);
        $router       = app(ContextRouter::class);

        $history = $this->getRecentHistory();
        $routingResult = $router->routeWithMetadata($userMessage, $companyId, 'receptionist', $history);

        $domains = $routingResult->domains;
        $this->contextMemory['active_domains'] = $domains;

        $context = $routingResult->assembledContext;
        $isBookingIntent = $routingResult->isBookingIntent;

        if (config('app.debug')) {
            Log::info('ChatModal: receptionist AI routing complete', [
                'stage'              => 'receptionist_ai_call',
                'is_booking_intent'  => $isBookingIntent,
                'detected_domains'   => $domains,
                'context_chars'      => strlen($context),
            ]);
        }

        $memoryHint = $this->buildMemoryHint();
        if ($memoryHint) {
            $context = $memoryHint . "\n\n" . $context;
        }

        $draftContext = $draftService->buildDraftContext($this->bookingDraft);
        
        $systemPrompt = $isBookingIntent
            ? $builder->receptionistBookingPrompt($context, $draftContext)
            : $builder->receptionistGeneralPrompt($context);
        
        $recentHistory = $this->getRecentHistory(exclude: 'last');

        if (config('app.debug')) {
            $promptType = $isBookingIntent ? 'booking' : 'general';
            Log::info('ChatModal: prompt selected', [
                'stage'          => 'receptionist_ai_call',
                'prompt_type'    => $promptType,
                'system_chars'   => strlen($systemPrompt),
                'has_draft'      => !empty($draftContext),
                'history_turns'  => count($recentHistory),
            ]);
        }

        $ai  = app(AIService::class);
        $raw = $ai->chat($systemPrompt, $userMessage, $recentHistory);

        $parsed     = $this->parseIntentResponse($raw, $companyId);
        $reply      = $parsed['reply'];
        $prefill    = $parsed['booking_prefill']  ?? [];
        $vprefill   = $parsed['vehicle_prefill']  ?? [];
        $isComplete = $parsed['booking_complete'] ?? false;

        $prevDraft = $this->bookingDraft;

        $this->bookingDraft = $draftService->mergePrefill(
            $this->bookingDraft,
            $this->hasAnyValue($prefill)  ? $prefill  : null,
            $this->hasAnyValue($vprefill) ? $vprefill : null
        );
        $this->bookingDraft = $draftService->resolveDraftDates($this->bookingDraft);
        $this->bookingDraft = $draftService->resolveRoomId($this->bookingDraft, $companyId);
        $this->bookingDraft = $draftService->resolveVehicleId($this->bookingDraft, $companyId);

        if (config('app.debug')) {
            Log::info('ChatModal: booking draft updated', [
                'stage'          => 'booking_draft_updated',
                'type'           => $this->bookingDraft['type'],
                'turns'          => $this->bookingDraft['turns'],
                'ai_complete'    => $isComplete,
                'room_fields'    => $this->bookingDraft['room'],
                'vehicle_fields' => $this->bookingDraft['vehicle'],
            ]);
        }

        $this->updateMemory($prefill, $vprefill);

        $roomReady    = $isComplete && $this->bookingDraft['type'] === 'room';
        $vehicleReady = $isComplete && $this->bookingDraft['type'] === 'vehicle';

        if (! $roomReady)    $roomReady    = $draftService->isRoomDraftComplete($this->bookingDraft);
        if (! $vehicleReady) $vehicleReady = $draftService->isVehicleDraftComplete($this->bookingDraft);

        if ($roomReady) {
            $payload = $draftService->buildRoomPayload($this->bookingDraft);

            if (config('app.debug')) {
                Log::info('ChatModal: room draft complete — attempting direct creation', [
                    'stage'   => 'booking_draft_complete',
                    'type'    => 'room',
                    'payload' => $payload,
                ]);

                Log::info('ChatModal: dispatching to DirectBookingService', [
                    'stage' => 'booking_dispatch_started',
                    'type'  => 'room',
                ]);
            }

            $result = app(DirectBookingService::class)->createRoomBooking($payload);

            if ($result['ok']) {
                $this->bookingDraft = $draftService->resetDraft();

                $roomName = $payload['roomId']
                    ? ($this->bookingDraft['room']['room_name'] ?? "Room #{$payload['roomId']}")
                    : 'the meeting room';
                $roomLabel = $prevDraft['room']['room_name'] ?? ($payload['title'] ? '' : $roomName);

                $reply = "Booking saved successfully and is now **pending approval**."
                    . "\n\n**{$payload['title']}**"
                    . ($prevDraft['room']['room_name'] ? " — {$prevDraft['room']['room_name']}" : '')
                    . "\n{$payload['ymd']}  {$payload['time']}-{$payload['endTime']}"
                    . "\n\nBooking #{$result['booking_id']} has been submitted to the approval queue.";

                if (config('app.debug')) {
                    Log::info('ChatModal: room booking confirmed', [
                        'stage'      => 'booking_created',
                        'booking_id' => $result['booking_id'],
                    ]);
                }

                return [$reply, null, null];
            }

            Log::warning('ChatModal: room booking creation failed', [
                'stage' => 'booking_failed',
                'error' => $result['error'],
            ]);

            $reply = "I wasn't able to save the booking. **Reason:** {$result['error']}\n\nPlease correct the details and try again.";
            return [$reply, null, null];
        }

        if ($vehicleReady) {
            $payload = $draftService->buildVehiclePayload($this->bookingDraft);

            if (config('app.debug')) {
                Log::info('ChatModal: vehicle draft complete — attempting direct creation', [
                    'stage'   => 'booking_draft_complete',
                    'type'    => 'vehicle',
                    'payload' => $payload,
                ]);

                Log::info('ChatModal: dispatching to DirectBookingService', [
                    'stage' => 'booking_dispatch_started',
                    'type'  => 'vehicle',
                ]);
            }

            $result = app(DirectBookingService::class)->createVehicleBooking($payload);

            if ($result['ok']) {
                $this->bookingDraft = $draftService->resetDraft();

                $vehicleName = $prevDraft['vehicle']['vehicle_name'] ?? "Vehicle #{$payload['vehicleId']}";
                $reply = "Vehicle booking saved successfully and is now **pending approval**."
                    . "\n\n**{$vehicleName}**"
                    . " — {$payload['borrowerName']}"
                    . "\n{$payload['dateFrom']}  {$payload['startTime']}–{$payload['endTime']}"
                    . "\n\nBooking #{$result['booking_id']} has been submitted to the approval queue.";

                if (config('app.debug')) {
                    Log::info('ChatModal: vehicle booking confirmed', [
                        'stage'      => 'booking_created',
                        'booking_id' => $result['booking_id'],
                    ]);
                }

                return [$reply, null, null];
            }

            Log::warning('ChatModal: vehicle booking creation failed', [
                'stage' => 'booking_failed',
                'error' => $result['error'],
            ]);

            $reply = "I wasn't able to save the vehicle booking. **Reason:** {$result['error']}\n\nPlease correct the details and try again.";
            return [$reply, null, null];
        }

        if (config('app.debug')) {
            Log::info('ChatModal: booking draft still incomplete', [
                'stage'       => 'booking_draft_updated',
                'type'        => $this->bookingDraft['type'],
                'turns'       => $this->bookingDraft['turns'],
                'missing_room'    => $this->bookingDraft['type'] === 'room'
                    ? $this->missingRoomSummary($this->bookingDraft['room'])
                    : [],
                'missing_vehicle' => $this->bookingDraft['type'] === 'vehicle'
                    ? $this->missingVehicleSummary($this->bookingDraft['vehicle'])
                    : [],
            ]);
        }

        $outPrefill  = $this->hasAnyValue($prefill)  ? $prefill  : null;
        $outVprefill = $this->hasAnyValue($vprefill) ? $vprefill : null;

        return [$reply, $outPrefill, $outVprefill];
    }

    private function missingRoomSummary(array $r): array
    {
        $missing = [];
        if (empty($r['meeting_title'])) $missing[] = 'meeting_title';
        if (empty($r['date']))          $missing[] = 'date';
        if (empty($r['start_time']))    $missing[] = 'start_time';
        if (empty($r['end_time']))      $missing[] = 'end_time';
        $isOnline = ($r['booking_type'] ?? '') === 'online_meeting';
        if (! $isOnline && empty($r['room_id'])) $missing[] = 'room_id';
        return $missing;
    }

    private function missingVehicleSummary(array $v): array
    {
        $missing = [];
        if (empty($v['vehicle_id']))    $missing[] = 'vehicle_id';
        if (empty($v['borrower_name'])) $missing[] = 'borrower_name';
        if (empty($v['date_from']))     $missing[] = 'date_from';
        if (empty($v['date_to']))       $missing[] = 'date_to';
        if (empty($v['start_time']))    $missing[] = 'start_time';
        if (empty($v['end_time']))      $missing[] = 'end_time';
        if (empty($v['purpose']))       $missing[] = 'purpose';
        if (empty($v['destination']))   $missing[] = 'destination';
        if (empty($v['purpose_type']))  $missing[] = 'purpose_type';
        return $missing;
    }

    private function emptyMemory(): array
    {
        return [
            'last_room_id'    => null,
            'last_room_name'  => null,
            'last_vehicle_id' => null,
            'last_date'       => null,
            'active_domains'  => [],
        ];
    }

    private function updateMemory(array $prefill, array $vprefill): void
    {
        if (! empty($prefill['room_id']))   $this->contextMemory['last_room_id']   = $prefill['room_id'];
        if (! empty($prefill['room_name'])) $this->contextMemory['last_room_name'] = $prefill['room_name'];
        if (! empty($prefill['date']))      $this->contextMemory['last_date']       = $prefill['date'];
        if (! empty($vprefill['vehicle_id'])) $this->contextMemory['last_vehicle_id'] = $vprefill['vehicle_id'];
        if (! empty($vprefill['date_from']))  $this->contextMemory['last_date']       = $vprefill['date_from'];
    }

    private function ensureMemoryDefaults(): void
    {
        $defaults = $this->emptyMemory();
        foreach ($defaults as $key => $default) {
            if (! array_key_exists($key, $this->contextMemory)) {
                $this->contextMemory[$key] = $default;
            }
        }
    }

    private function buildMemoryHint(): string
    {
        $this->ensureMemoryDefaults();

        $lines = [];

        if ($this->contextMemory['last_room_name'] ?? null) {
            $lines[] = "Last discussed room: {$this->contextMemory['last_room_name']}"
                . (($this->contextMemory['last_room_id'] ?? null) ? " (ID:{$this->contextMemory['last_room_id']})" : '');
        }
        if ($this->contextMemory['last_vehicle_id'] ?? null) {
            $lines[] = "Last discussed vehicle ID: {$this->contextMemory['last_vehicle_id']}";
        }
        if ($this->contextMemory['last_date'] ?? null) {
            $lines[] = "Last discussed date: {$this->contextMemory['last_date']}";
        }

        if (empty($lines)) return '';

        return "SESSION MEMORY (carry forward — do not re-ask unless changed):\n"
            . implode("\n", $lines);
    }

    private function parseIntentResponse(string $raw, ?int $companyId): array
    {
        $empty = ['reply' => $raw, 'booking_prefill' => [], 'vehicle_prefill' => [], 'booking_complete' => false];

        $raw = trim($this->stripThinkBlocks($raw));
        $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
        $raw = preg_replace('/\s*```$/', '', $raw);
        $raw = trim($raw);

        if (! str_starts_with($raw, '{')) return $empty;

        $decoded = json_decode($raw, true);
        if (! is_array($decoded) || ! isset($decoded['reply'])) return $empty;

        $reply          = (string) ($decoded['reply']            ?? '');
        $bookingComplete = (bool)  ($decoded['booking_complete'] ?? false);

        $prefill = $decoded['booking_prefill'] ?? [];
        if (is_array($prefill)) {
            if (empty($prefill['room_id']) && ! empty($prefill['room_name'])) {
                $room = Room::when($companyId, fn($q) => $q->where('company_id', $companyId))
                    ->where('room_name', 'like', '%' . trim($prefill['room_name']) . '%')->first();
                $prefill['room_id']   = $room?->room_id;
                $prefill['room_name'] = $room?->room_name ?? $prefill['room_name'];
            }
            $prefill['room_id']             = isset($prefill['room_id'])             ? (int) $prefill['room_id']             : null;
            $prefill['number_of_attendees'] = isset($prefill['number_of_attendees']) ? (int) $prefill['number_of_attendees'] : null;
            $prefill['special_notes']   = $prefill['special_notes']   ?? null;
            $prefill['department']      = $prefill['department']      ?? null;
            $prefill['historical_user'] = $prefill['historical_user'] ?? null;
            $validBT = ['meeting', 'online_meeting'];
            $prefill['booking_type']    = in_array($prefill['booking_type'] ?? '', $validBT, true) ? $prefill['booking_type'] : null;
            $validP  = ['google_meet', 'zoom'];
            $prefill['online_provider'] = in_array($prefill['online_provider'] ?? '', $validP, true) ? $prefill['online_provider'] : null;
            if (($prefill['booking_type'] ?? '') === 'online_meeting') { $prefill['room_id'] = null; $prefill['room_name'] = null; }
        }

        $vprefill = $decoded['vehicle_prefill'] ?? [];
        if (is_array($vprefill)) {
            if (empty($vprefill['vehicle_id']) && (! empty($vprefill['vehicle_name']) || ! empty($vprefill['plate_number']))) {
                $vq = Vehicle::when($companyId, fn($q) => $q->where('company_id', $companyId));
                if (! empty($vprefill['vehicle_name'])) $vq->where('name', 'like', '%' . trim($vprefill['vehicle_name']) . '%');
                elseif (! empty($vprefill['plate_number'])) $vq->where('plate_number', 'like', '%' . trim($vprefill['plate_number']) . '%');
                $vehicle = $vq->first();
                $vprefill['vehicle_id']   = $vehicle?->vehicle_id;
                $vprefill['vehicle_name'] = $vehicle?->name         ?? $vprefill['vehicle_name']  ?? null;
                $vprefill['plate_number'] = $vehicle?->plate_number ?? $vprefill['plate_number']  ?? null;
            }
            $vprefill['vehicle_id']    = isset($vprefill['vehicle_id'])   ? (int) $vprefill['vehicle_id'] : null;
            $vprefill['borrower_name'] = $vprefill['borrower_name'] ?? null;
            $vprefill['department']    = $vprefill['department']    ?? null;
            $vprefill['date_from']     = $vprefill['date_from']     ?? null;
            $vprefill['date_to']       = $vprefill['date_to']       ?? null;
            $vprefill['start_time']    = $vprefill['start_time']    ?? null;
            $vprefill['end_time']      = $vprefill['end_time']      ?? null;
            $vprefill['purpose']       = $vprefill['purpose']       ?? null;
            $vprefill['destination']   = $vprefill['destination']   ?? null;
            $validT = ['dinas', 'operasional', 'antar_jemput', 'lainnya'];
            $vprefill['purpose_type']  = in_array($vprefill['purpose_type'] ?? '', $validT, true) ? $vprefill['purpose_type'] : null;
        }

        return ['reply' => $reply, 'booking_prefill' => $prefill ?? [], 'vehicle_prefill' => $vprefill ?? [], 'booking_complete' => $bookingComplete];
    }

    private function ensureSession(): void
    {
        if ($this->activeSessionId) return;
        $session = AiChatSession::create(['user_id' => Auth::id(), 'role' => $this->userRole(), 'title' => null, 'started_at' => now()]);
        $this->activeSessionId = $session->id;
    }

    private function persistMessage(string $role, string $text, ?array $prefill, ?array $vprefill = null): void
    {
        if (! $this->activeSessionId) return;
        AiChatMessage::create([
            'session_id'      => $this->activeSessionId,
            'role'            => $role,
            'text'            => $text,
            'booking_prefill' => $prefill !== null || $vprefill !== null ? ['room' => $prefill, 'vehicle' => $vprefill] : null,
            'sent_at'         => now(),
        ]);
        if ($role === 'user') {
            $session = AiChatSession::find($this->activeSessionId);
            if ($session && empty($session->title)) {
                $session->update(['title' => AiChatSession::titleFromMessage($text)]);
            }
        }
    }

    private function archiveCurrentSession(): void
    {
        if ($this->activeSessionId) {
            AiChatSession::find($this->activeSessionId)?->close();
            $this->activeSessionId = null;
        }
    }

    private function loadHistorySessions(): void
    {
        $this->historySessions = AiChatSession::where('user_id', Auth::id())
            ->whereNotNull('ended_at')->whereNotNull('title')
            ->withCount('messages')->orderByDesc('started_at')->limit(30)->get()
            ->map(fn(AiChatSession $s) => ['id' => $s->id, 'title' => $s->title, 'role' => $s->role, 'started_at' => $s->started_at->format('d M Y, H:i'), 'message_count' => $s->messages_count])
            ->values()->toArray();
    }

    private function appendToHistory(string $role, string $content): void
    {
        $this->conversationHistory[] = ['role' => $role, 'content' => $content];
        $max = (int) config('ai.max_draft_turns', 10);
        if (count($this->conversationHistory) > $max) {
            $this->conversationHistory = array_slice($this->conversationHistory, -$max);
        }
    }

    private function getRecentHistory(string $exclude = ''): array
    {
        $history = $this->conversationHistory;
        if ($exclude === 'last' && ! empty($history)) array_pop($history);
        
        return $this->compressHistory($history);
    }

    private function compressHistory(array $history): array
    {
        if (count($history) <= 4) {
            return $history;
        }

        $compressed = [];
        
        $recent = array_slice($history, -4);
        
        $older = array_slice($history, 0, -4);
        
        if (!empty($older)) {
            $summary = $this->summarizeOlderTurns($older);
            if ($summary) {
                $compressed[] = [
                    'role' => 'system',
                    'content' => $summary
                ];
            }
        }
        
        foreach ($recent as $turn) {
            $compressed[] = $turn;
        }
        
        if (config('app.debug')) {
            Log::info('ChatModal: history compressed', [
                'stage' => 'history_compression',
                'original_turns' => count($history),
                'compressed_turns' => count($compressed),
                'older_turns_summarized' => count($older),
            ]);
        }
        
        return $compressed;
    }

    private function summarizeOlderTurns(array $older): ?string
    {
        if (empty($older)) {
            return null;
        }

        $topics = [];
        $hasBookingDiscussion = false;
        $roomMentions = [];
        $vehicleMentions = [];

        foreach ($older as $turn) {
            $content = mb_strtolower($turn['content'] ?? '');
            
            if (preg_match('/\b(book|reserve|booking|pesan)\b/i', $content)) {
                $hasBookingDiscussion = true;
            }
            
            if (preg_match('/room\s*([a-z0-9]+)/i', $content, $m)) {
                $roomMentions[] = $m[0];
            }
            
            if (preg_match('/vehicle|kendaraan|mobil/i', $content)) {
                $vehicleMentions[] = 'vehicle';
            }
        }

        $summary = "Earlier conversation context:";
        
        if ($hasBookingDiscussion) {
            $summary .= " Discussed booking.";
        }
        
        if (!empty($roomMentions)) {
            $uniqueRooms = array_unique(array_slice($roomMentions, 0, 2));
            $summary .= " Mentioned: " . implode(', ', $uniqueRooms) . ".";
        }
        
        if (!empty($vehicleMentions)) {
            $summary .= " Discussed vehicles.";
        }

        return strlen($summary) > 30 ? $summary : null;
    }

    public function exportPdf(): void
    {
        if ($this->userRole !== 'manager') return;
        $this->js('window.open(' . json_encode(route('chat.export.pdf')) . ", '_blank')");
    }

    public function exportCsv(): void
    {
        if ($this->userRole !== 'manager') return;
        $this->js('window.open(' . json_encode(route('chat.export.csv')) . ", '_blank')");
    }

    private function seedGreeting(): void
    {
        $role   = $this->userRole();
        $locale = app()->getLocale();
        $isId   = $locale === 'id';

        if ($role === 'manager') {
            $text = $isId
                ? "Halo! Saya KRB Manager Assistant. Saya siap membantu Anda menganalisis operasional:\n\n• **Buku Tamu & Pengunjung** (tren, kunjungan, statistik)\n• **Pemesanan Ruangan** (utilisasi, status, pembatalan)\n• **Pemesanan Kendaraan** (utilisasi, status, pembatalan)\n• **Pengiriman Dokumen & Paket** (status, riwayat pengiriman)\n\nGunakan tombol pintas di atas atau tanyakan langsung."
                : "Hello! I'm the KRB Manager Assistant. I can help you analyze operations:\n\n• **Guestbook & Visitors** (trends, counts, statistics)\n• **Room Bookings** (utilization, statuses, cancellations)\n• **Vehicle Bookings** (utilization, statuses, cancellations)\n• **Document & Package Deliveries** (status, delivery logs)\n\nUse the quick actions above or ask any question.";
        } else {
            $text = $isId
                ? "Halo! Saya KRB Receptionist Assistant. Saya bisa membantu Anda:\n\n• **Auto Room Booking** (buat reservasi ruangan rapat secara cepat)\n• **Auto Vehicle Booking** (buat reservasi kendaraan dinas/operasional)\n• **Informasi Jadwal & Ketersediaan** fasilitas dan kendaraan\n\nGunakan tombol pintas di atas atau ketik detail reservasi Anda."
                : "Hello! I'm the KRB Receptionist Assistant. I can help you with:\n\n• **Auto Room Booking** (quickly create meeting room reservations)\n• **Auto Vehicle Booking** (quickly create vehicle reservations)\n• **Schedules & Availability** for rooms and vehicles\n\nUse the quick actions above or type your booking details directly.";
        }

        $this->messages[] = [
            'role'            => 'assistant',
            'text'            => $text,
            'booking_prefill' => null,
            'vehicle_prefill' => null,
            'sent_at'         => now()->format('H:i'),
        ];
    }

    private function userRole(): string { 
        return $this->resolveUserRole(); 
    }

    private function resolveUserRole(): string
    {
        $user = Auth::user();
        if (! $user) return 'receptionist';
        $roleName = strtolower($user->role?->name ?? $user->role_name ?? '');
        return str_contains($roleName, 'manager') ? 'manager' : 'receptionist';
    }

    private function stripThinkBlocks(string $raw): string
    {
        return trim(preg_replace('/<think>.*?<\/think>/si', '', $raw));
    }

    private function hasAnyValue(array $arr): bool
    {
        foreach ($arr as $v) { if ($v !== null && $v !== '') return true; }
        return false;
    }

    public function render()
    {
        return view('livewire.components.ui.chat-modal');
    }
}
