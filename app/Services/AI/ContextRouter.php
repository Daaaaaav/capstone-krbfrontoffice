<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\ContextProviderInterface;
use App\Services\AI\Context\AnalyticsContextProvider;
use App\Services\AI\Context\DeliveryContextProvider;
use App\Services\AI\Context\GuestbookContextProvider;
use App\Services\AI\Context\RoomContextProvider;
use App\Services\AI\Context\VehicleContextProvider;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ContextRouter
{
    private string $tz = 'Asia/Jakarta';
    private array $providers;

    public function __construct()
    {
        $this->providers = [
            'rooms'     => app(RoomContextProvider::class),
            'vehicles'  => app(VehicleContextProvider::class),
            'analytics' => app(AnalyticsContextProvider::class),
            'guestbook' => app(GuestbookContextProvider::class),
            'deliveries'=> app(DeliveryContextProvider::class),
        ];
    }

    public function route(string $message, ?int $companyId, string $role, array $history = []): string
    {
        $domains = $this->detect($message, $role, $history);
        $params  = $this->extractParams($message);

        Log::info('ContextRouter: routing message', [
            'stage'           => 'context_routing',
            'role'            => $role,
            'detected_domains'=> $domains,
            'extracted_params'=> $params,
            'message_preview' => mb_substr($message, 0, 80),
        ]);

        $blocks = [];
        foreach ($domains as $domain) {
            if (isset($this->providers[$domain])) {
                Log::info('ContextRouter: loading provider', [
                    'stage'    => 'context_routing',
                    'provider' => get_class($this->providers[$domain]),
                    'domain'   => $domain,
                    'params'   => $params,
                ]);

                try {
                    $block = $this->providers[$domain]->load($companyId, $params);
                    if ($block !== '') {
                        $blocks[] = $block;
                        Log::info('ContextRouter: provider loaded', [
                            'stage'    => 'context_routing',
                            'domain'   => $domain,
                            'chars'    => strlen($block),
                        ]);
                    } else {
                        Log::info('ContextRouter: provider returned empty block', [
                            'stage'  => 'context_routing',
                            'domain' => $domain,
                        ]);
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

        if (empty($blocks)) {
            Log::info('ContextRouter: no domains matched — loading fallback (rooms + vehicles)', [
                'stage' => 'context_routing',
            ]);
            $blocks[] = $this->providers['rooms']->load($companyId, $params);
            $blocks[] = $this->providers['vehicles']->load($companyId, $params);
        }

        $assembled = "=== CONTEXT ({$this->now()}) ===\n\n" . implode("\n\n", $blocks);

        Log::info('ContextRouter: context assembled', [
            'stage'        => 'context_routing',
            'total_chars'  => strlen($assembled),
            'blocks_count' => count($blocks),
        ]);

        return $assembled;
    }

    public function detectDomains(string $message, string $role, array $history = []): array
    {
        return $this->detect($message, $role, $history);
    }

    private function detect(string $message, string $role, array $history): array
    {
        $msg     = mb_strtolower($message);
        $domains = [];

        if ($role === 'manager') {
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
            if ($this->matches($content, ['statistic', 'total', 'trend', 'analytic'])) $domains[] = 'analytics';
            if ($this->matches($content, ['guest', 'visitor', 'tamu'])) $domains[] = 'guestbook';
            if ($this->matches($content, ['package', 'document', 'delivery'])) $domains[] = 'deliveries';
        }
        return array_unique($domains);
    }

    private function extractParams(string $message): array
    {
        $params = [];
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
