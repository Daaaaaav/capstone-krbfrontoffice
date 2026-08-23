<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\ContextProviderInterface;
use App\Services\AI\Context\AnalyticsContextProvider;
use App\Services\AI\Context\DeliveryContextProvider;
use App\Services\AI\Context\GuestbookContextProvider;
use App\Services\AI\Context\KrbKnowledgeContextProvider;
use App\Services\AI\Context\RoomContextProvider;
use App\Services\AI\Context\VehicleContextProvider;
use App\Services\AI\Enums\ContextDetailLevel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ContextRouter
{
    private string $tz = 'Asia/Jakarta';
    private array $providers;

    public function __construct()
    {
        $this->providers = [
            'rooms'         => app(RoomContextProvider::class),
            'vehicles'      => app(VehicleContextProvider::class),
            'analytics'     => app(AnalyticsContextProvider::class),
            'guestbook'     => app(GuestbookContextProvider::class),
            'deliveries'    => app(DeliveryContextProvider::class),
            'krb_knowledge' => app(KrbKnowledgeContextProvider::class),
        ];
    }

    public function routeWithMetadata(string $message, ?int $companyId, string $role, array $history = []): RoutingResult
    {
        $isBookingIntent = $this->detectBookingIntent($message, $history);
        $domains = $this->detect($message, $role, $history);
        $params = $this->extractParams($message);

        $providerDetailLevels = $this->determineDetailLevels($domains, $isBookingIntent, $role);

        if (config('app.debug')) {
            Log::info('ContextRouter: routing with metadata', [
                'stage'               => 'context_routing',
                'role'                => $role,
                'is_booking_intent'   => $isBookingIntent,
                'detected_domains'    => $domains,
                'provider_levels'     => $providerDetailLevels,
                'extracted_params'    => $params,
                'message_preview'     => mb_substr($message, 0, 80),
            ]);
        }

        $blocks = $this->loadProviders($domains, $companyId, $params, $providerDetailLevels);

        $assembled = $this->assembleContext($blocks);

        if (config('app.debug')) {
            Log::info('ContextRouter: context assembled with metadata', [
                'stage'               => 'context_routing',
                'is_booking_intent'   => $isBookingIntent,
                'total_chars'         => strlen($assembled),
                'blocks_count'        => count($blocks),
                'provider_levels'     => $providerDetailLevels,
            ]);
        }

        return RoutingResult::create($isBookingIntent, $domains, $providerDetailLevels, $assembled);
    }

    public function route(string $message, ?int $companyId, string $role, array $history = []): string
    {
        $result = $this->routeWithMetadata($message, $companyId, $role, $history);
        return $result->assembledContext;
    }

    public function detectDomains(string $message, string $role, array $history = []): array
    {
        return $this->detect($message, $role, $history);
    }

    private function detectBookingIntent(string $message, array $history): bool
    {
        $msg = mb_strtolower($message);

        $bookingKeywords = [
            'book', 'reserve', 'booking', 'reservation', 'pesan', 'reservasi',
            'i need', 'i want to book', 'schedule', 'jadwalkan', 'pinjam', 'borrow',
            'create booking', 'buat booking', 'new booking', 'make a reservation',
        ];

        foreach ($bookingKeywords as $keyword) {
            if (str_contains($msg, $keyword)) {
                return true;
            }
        }

        if (!empty($history)) {
            $recent = array_slice($history, -2);
            foreach ($recent as $turn) {
                $content = mb_strtolower($turn['content'] ?? '');
                foreach ($bookingKeywords as $keyword) {
                    if (str_contains($content, $keyword)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function determineDetailLevels(array $domains, bool $isBookingIntent, string $role): array
    {
        $levels = [];

        if ($role === 'manager') {
            foreach ($domains as $domain) {
                $levels[$domain] = ContextDetailLevel::DETAILED;
            }
            return $levels;
        }

        if ($role === 'it-officer') {
            foreach ($domains as $domain) {
                $levels[$domain] = ContextDetailLevel::NORMAL;
            }
            // IT Officer gets detailed analytics for analytics queries
            if (in_array('analytics', $domains, true)) {
                $levels['analytics'] = ContextDetailLevel::DETAILED;
            }
            return $levels;
        }

        foreach ($domains as $domain) {
            if ($isBookingIntent && in_array($domain, ['rooms', 'vehicles'])) {
                $levels[$domain] = ContextDetailLevel::BOOKING;
            } elseif ($domain === 'analytics') {
                $levels[$domain] = ContextDetailLevel::NORMAL;
            } elseif (in_array($domain, ['rooms', 'vehicles'])) {
                $levels[$domain] = ContextDetailLevel::NORMAL;
            } else {
                $levels[$domain] = ContextDetailLevel::MINIMAL;
            }
        }

        return $levels;
    }

    private function loadProviders(array $domains, ?int $companyId, array $params, array $providerDetailLevels): array
    {
        $blocks = [];

        foreach ($domains as $domain) {
            if (isset($this->providers[$domain])) {
                $detailLevel = $providerDetailLevels[$domain] ?? ContextDetailLevel::DETAILED;

                if (config('app.debug')) {
                    Log::info('ContextRouter: loading provider', [
                        'stage'        => 'context_routing',
                        'provider'     => get_class($this->providers[$domain]),
                        'domain'       => $domain,
                        'detail_level' => $detailLevel->value,
                        'params'       => $params,
                    ]);
                }

                try {
                    $block = $this->providers[$domain]->load($companyId, $params, $detailLevel);
                    if ($block !== '') {
                        $blocks[] = $block;
                        if (config('app.debug')) {
                            Log::info('ContextRouter: provider loaded', [
                                'stage'        => 'context_routing',
                                'domain'       => $domain,
                                'detail_level' => $detailLevel->value,
                                'chars'        => strlen($block),
                            ]);
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error('ContextRouter: provider threw an exception', [
                        'stage'    => 'context_routing',
                        'domain'   => $domain,
                        'provider' => get_class($this->providers[$domain]),
                        'class'    => get_class($e),
                        'error'    => $e->getMessage(),
                        'file'     => $e->getFile() . ':' . $e->getLine(),
                    ]);
                }
            }
        }

        return $blocks;
    }

    private function assembleContext(array $blocks): string
    {
        if (empty($blocks)) {
            return "=== CONTEXT ({$this->now()}) ===\n\n(no specific context loaded)";
        }

        return "=== CONTEXT ({$this->now()}) ===\n\n" . implode("\n\n", $blocks);
    }

    private function detect(string $message, string $role, array $history): array
    {
        $msg     = mb_strtolower($message);
        $domains = [];

        if ($role === 'manager' || $role === 'it-officer') {
            $domains[] = 'analytics';
        }

        if ($this->matches($msg, [
            'book', 'reserve', 'ruang', 'meeting room', 'room', 'rapat', 'schedule',
            'aula', 'hall', 'available', 'free slot', 'slot', 'konfirmasi', 'approve',
            'pending', 'approval', 'online meeting', 'zoom', 'google meet',
        ])) {
            $domains[] = 'rooms';
        }

        if ($this->matches($msg, [
            'vehicle', 'car', 'kendaraan', 'mobil', 'borrow', 'pinjam', 'trip',
            'driver', 'drive', 'transport', 'perjalanan', 'dinas', 'destination',
        ])) {
            $domains[] = 'vehicles';
        }

        if ($this->matches($msg, [
            'statistic', 'statistik', 'analytic', 'report', 'laporan', 'summary',
            'trend', 'total', 'how many', 'berapa', 'most', 'terbanyak', 'usage',
            'occupancy', 'peak', 'rejection', 'year', 'month', 'week', 'tahun',
            'bulan', 'minggu', 'compare', 'increase', 'decrease', 'naik', 'turun',
            'cancel', 'cancellation', 'cancelled', 'batal', 'pembatalan', 'dibatal', 'rate',
            'average', 'rata-rata', 'mean', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday',
            'senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu',
        ])) {
            $domains[] = 'analytics';
        }

        if ($this->matches($msg, [
            'guest', 'visitor', 'tamu', 'guestbook', 'check-in', 'checkin',
            'checkout', 'visit', 'kunjungan', 'who came', 'siapa yang datang',
        ])) {
            $domains[] = 'guestbook';
        }

        if ($this->matches($msg, [
            'package', 'paket', 'document', 'dokumen', 'delivery', 'pengiriman',
            'surat', 'letter', 'parcel', 'item', 'stored', 'tersimpan',
        ])) {
            $domains[] = 'deliveries';
        }

        if ($this->matches($msg, [
            'kebun raya', 'reinwardt', 'rafflesia', 'bunga bangkai', 'amorphophallus', 'titan arum',
            'victoria amazonica', 'teratai raksasa', 'griya anggrek', 'taman meksiko', 'taman obat',
            'danau gunting', 'jembatan merah', 'monumen lady raffles', 'makam belanda', 'astrid',
            'museum zoologi', 'herbarium', 'ecodome', 'sejarah', 'founder', 'pendiri', 'koleksi',
            'kelapa sawit', 'sawit', 'spesies', 'luas', 'hektar', 'brin', 'jam buka', 'jam operasional',
        ]) || app(ScopeGuard::class)->isGeneralKrbKnowledge($msg)) {
            $domains[] = 'krb_knowledge';
        }

        $wordCount = str_word_count($msg);
        if ($wordCount <= 4 && ! empty($history)) {
            $prevDomains = $this->detectFromHistory($history);
            $domains     = array_unique(array_merge($domains, $prevDomains));
        }

        return array_unique($domains);
    }

    private function detectFromHistory(array $history): array
    {
        $recent  = array_slice($history, -4);
        $domains = [];
        foreach ($recent as $turn) {
            $content = mb_strtolower($turn['content'] ?? '');
            if ($this->matches($content, ['room', 'booking', 'meeting', 'rapat'])) $domains[] = 'rooms';
            if ($this->matches($content, ['vehicle', 'car', 'kendaraan'])) $domains[] = 'vehicles';
            if ($this->matches($content, ['statistic', 'total', 'trend', 'analytic', 'cancel', 'batal'])) $domains[] = 'analytics';
            if ($this->matches($content, ['guest', 'visitor', 'tamu'])) $domains[] = 'guestbook';
            if ($this->matches($content, ['package', 'document', 'delivery'])) $domains[] = 'deliveries';
            if ($this->matches($content, ['kebun raya', 'reinwardt', 'rafflesia', 'griya anggrek', 'koleksi', 'sejarah'])) $domains[] = 'krb_knowledge';
        }
        return array_unique($domains);
    }

    private function extractParams(string $message): array
    {
        $params = [
            'query'   => $message,
            'message' => $message,
        ];
        $now    = Carbon::now($this->tz);
        $msg    = mb_strtolower($message);

        if (str_contains($msg, 'tomorrow') || str_contains($msg, 'besok')) {
            $params['date'] = $now->copy()->addDay()->toDateString();
        } elseif (str_contains($msg, 'today') || str_contains($msg, 'hari ini')) {
            $params['date'] = $now->toDateString();
        }

        if (str_contains($msg, 'this week') || str_contains($msg, 'minggu ini')) {
            $params['period'] = 'this_week';
        } elseif (str_contains($msg, 'this month') || str_contains($msg, 'bulan ini')) {
            $params['period'] = 'this_month';
        } elseif (str_contains($msg, 'this year') || str_contains($msg, 'tahun ini')) {
            $params['period'] = 'this_year';
        } elseif (str_contains($msg, 'last month') || str_contains($msg, 'bulan lalu')) {
            $params['period'] = 'last_month';
        } elseif (str_contains($msg, 'last year') || str_contains($msg, 'tahun lalu')) {
            $params['period'] = 'last_year';
        }

        if (preg_match('/\b(19\d{2}|20\d{2})\b/', $msg, $m)) {
            $params['year'] = (int) $m[1];
        }

        $weekdays = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'minggu', 'senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu'];
        foreach ($weekdays as $wd) {
            if (str_contains($msg, $wd)) {
                $params['weekday'] = $wd;
                break;
            }
        }

        return $params;
    }

    private function matches(string $haystack, array $keywords): bool
    {
        foreach ($keywords as $kw) {
            if (str_contains($haystack, $kw)) {
                return true;
            }
        }
        return false;
    }

    private function now(): string
    {
        return Carbon::now($this->tz)->format('d M Y, H:i') . ' WIB';
    }
}
