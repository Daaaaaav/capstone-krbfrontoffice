<?php

namespace App\Services\AI;

use App\Models\Company;
use App\Models\User;
use App\Services\AI\Enums\ChatDomain;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ScopeGuard
{
    private string $tz = 'Asia/Jakarta';
    public const REFUSAL_EN = 'I can only assist with information and tasks related to your authorized KRB System context.';
    public const REFUSAL_ID = 'Saya hanya dapat membantu informasi dan tugas terkait konteks KRB System yang diotorisasi.';

    public function isSystemUtility(string $message): bool
    {
        $msg = mb_strtolower(trim($message));

        $timePatterns = [
            '/^(what\s+time\s+is\s+it|what\s+is\s+the\s+time|current\s+time|tell\s+me\s+the\s+time)\b/i',
            '/^(jam\s+berapa\s+sekarang|waktu\s+saat\s+ini|pukul\s+berapa\s+sekarang)\b/i',
            '/^(what\s+is\s+today(\'s)?\s+date|what\s+date\s+is\s+it|current\s+date|today(\'s)?\s+date)\b/i',
            '/^(tanggal\s+berapa\s+hari\s+ini|hari\s+ini\s+tanggal\s+berapa|tanggal\s+sekarang)\b/i',
        ];

        foreach ($timePatterns as $pattern) {
            if (preg_match($pattern, $msg)) {
                return true;
            }
        }

        return false;
    }

    public function handleSystemUtility(string $message, string $lang = 'en'): string
    {
        $now = Carbon::now($this->tz);
        $isId = $lang === 'id' || preg_match('/(jam|waktu|pukul|tanggal|hari\s+ini)/i', $message);

        $msg = mb_strtolower(trim($message));
        $isDateOnly = preg_match('/(date|tanggal)/i', $msg) && ! preg_match('/(time|jam|waktu|pukul)/i', $msg);
        $isTimeOnly = preg_match('/(time|jam|waktu|pukul)/i', $msg) && ! preg_match('/(date|tanggal)/i', $msg);

        if ($isId) {
            if ($isDateOnly) {
                return 'Hari ini tanggal ' . $now->translatedFormat('d F Y') . ' (' . $this->tz . ').';
            }
            if ($isTimeOnly) {
                return 'Waktu saat ini adalah pukul ' . $now->format('H:i') . ' WIB.';
            }
            return 'Saat ini: ' . $now->translatedFormat('l, d F Y H:i') . ' WIB.';
        }

        if ($isDateOnly) {
            return "Today's date is " . $now->format('d F Y') . ' (Asia/Jakarta).';
        }
        if ($isTimeOnly) {
            return 'The current time is ' . $now->format('H:i') . ' (WIB / Asia/Jakarta).';
        }
        return 'Current date and time: ' . $now->format('l, d F Y H:i') . ' (WIB).';
    }

    public function validate(string $message, ?User $user = null): array
    {
        $msg = trim($message);
        $lang = $this->detectLanguage($msg);
        $refusal = $lang === 'id' ? self::REFUSAL_ID : self::REFUSAL_EN;

        if ($msg === '') {
            return ['allowed' => true, 'reason' => null, 'refusal' => null, 'domains' => [ChatDomain::ADMINISTRATIVE->value]];
        }

        if ($this->isPromptInjection($msg)) {
            Log::warning('ScopeGuard: prompt injection attempt blocked', [
                'user_id' => $user?->user_id,
                'company_id' => $user?->company_id,
                'message' => $msg,
            ]);
            return [
                'allowed' => false,
                'reason' => 'prompt_injection',
                'refusal' => $refusal,
                'domains' => [ChatDomain::OUT_OF_SCOPE->value],
            ];
        }

        if ($this->isCrossProviderAttempt($msg, $user)) {
            Log::warning('ScopeGuard: cross-provider inquiry blocked', [
                'user_id' => $user?->user_id,
                'company_id' => $user?->company_id,
                'message' => $msg,
            ]);
            return [
                'allowed' => false,
                'reason' => 'cross_provider_unauthorized',
                'refusal' => $refusal,
                'domains' => [ChatDomain::OUT_OF_SCOPE->value],
            ];
        }

        if ($this->isInternalLeakRequest($msg)) {
            Log::warning('ScopeGuard: internal leak request blocked', [
                'user_id' => $user?->user_id,
                'message' => $msg,
            ]);
            return [
                'allowed' => false,
                'reason' => 'internal_leak_attempt',
                'refusal' => $refusal,
                'domains' => [ChatDomain::OUT_OF_SCOPE->value],
            ];
        }

        if ($this->isSystemUtility($msg)) {
            return [
                'allowed' => true,
                'is_utility' => true,
                'utility_response' => $this->handleSystemUtility($msg, $lang),
                'reason' => 'system_utility',
                'refusal' => null,
                'domains' => [ChatDomain::SYSTEM_UTILITY->value],
            ];
        }

        if ($this->isOutOfScopeGeneralKnowledge($msg)) {
            Log::info('ScopeGuard: out-of-scope general knowledge question rejected', [
                'user_id' => $user?->user_id,
                'message' => $msg,
            ]);
            return [
                'allowed' => false,
                'reason' => 'out_of_scope_general_knowledge',
                'refusal' => $refusal,
                'domains' => [ChatDomain::OUT_OF_SCOPE->value],
            ];
        }

        if ($user && ! $this->isRoleAuthorized($msg, $user)) {
            Log::warning('ScopeGuard: role-unauthorized operation blocked', [
                'user_id' => $user->user_id,
                'role' => $user->role?->name ?? 'unknown',
                'message' => $msg,
            ]);
            return [
                'allowed' => false,
                'reason' => 'role_unauthorized',
                'refusal' => $refusal,
                'domains' => [ChatDomain::OUT_OF_SCOPE->value],
            ];
        }

        $domains = $this->classifyDomains($msg);

        return ['allowed' => true, 'reason' => null, 'refusal' => null, 'domains' => $domains];
    }

    public function classify(string $message, ?User $user = null): array
    {
        return $this->validate($message, $user);
    }

    public function classifyDomains(string $message): array
    {
        $msg = mb_strtolower(trim($message));
        $domains = [];

        if ($this->isGeneralKrbKnowledge($msg)) {
            $domains[] = ChatDomain::GENERAL_KRB_KNOWLEDGE->value;
        }

        if ($this->matchesKeywords($msg, [
            'average', 'rata-rata', 'mean', 'calculate', 'hitung', 'calculation', 'perhitungan',
            'compare', 'bandingkan', 'growth', 'pertumbuhan', 'percentage', 'persentase', 'ratio', 'rasio',
        ])) {
            $domains[] = ChatDomain::CALCULATION->value;
        }

        if ($this->matchesKeywords($msg, [
            'statistic', 'statistik', 'analytic', 'analitik', 'report', 'laporan', 'summary', 'ringkasan',
            'trend', 'total', 'how many', 'berapa', 'most', 'terbanyak', 'usage', 'penggunaan',
            'occupancy', 'okupansi', 'peak', 'puncak', 'cancellation', 'pembatalan', 'batal', 'rate', 'tingkat',
            'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday',
            'minggu', 'senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu',
        ])) {
            $domains[] = ChatDomain::ANALYTICS->value;
        }

        if ($this->matchesKeywords($msg, [
            'forecast', 'prediksi', 'prediction', 'lstm', 'future demand', 'ramalan',
        ])) {
            $domains[] = ChatDomain::FORECAST->value;
        }

        if ($this->matchesKeywords($msg, [
            'available', 'ketersediaan', 'free slot', 'slot kosong', 'jadwal kosong', 'check room', 'check vehicle',
        ])) {
            $domains[] = ChatDomain::AVAILABILITY->value;
        }

        if (empty($domains) || $this->matchesKeywords($msg, ['room', 'ruang', 'vehicle', 'kendaraan', 'booking', 'guest', 'tamu', 'delivery', 'paket', 'user'])) {
            $domains[] = ChatDomain::ADMINISTRATIVE->value;
        }

        return array_values(array_unique($domains));
    }

    public function isGeneralKrbKnowledge(string $message): bool
    {
        $msg = mb_strtolower(trim($message));

        $krbPatterns = [
            '/\b(kebun\s*raya(\s*bogor)?|bogor\s*botanic(al)?\s*gardens?|lands\s*plantentuin)\b/i',
            '/\b(reinwardt|caspar\s*georg\s*carl|c\.g\.c\.\s*reinwardt)\b/i',
            '/\b(rafflesia(\s*patma|\s*arnoldii)?|bunga\s*bangkai|amorphophallus(\s*titanum)?|titan\s*arum)\b/i',
            '/\b(victoria\s*amazonica|teratai\s*raksasa|griya\s*anggrek|orchidarium|taman\s*meksiko|taman\s*obat|taman\s*akuatik|taman\s*astrid)\b/i',
            '/\b(danau\s*gunting|jembatan\s*merah|jembatan\s*gantung|monumen\s*lady\s*raffles|olivia\s*raffles|makam\s*belanda|museum\s*zoologi|herbarium\s*bogoriense|ecodome)\b/i',
            '/\b(brin|konservasi\s*ex-situ|koleksi\s*tanaman|koleksi\s*tumbuhan|koleksi\s*palem|kelapa\s*sawit\s*pertama|elaeis\s*guineensis|pohon\s*raja|koompassia)\b/i',
            '/\b(sejarah\s*kebun\s*raya|history\s*of\s*kebun\s*raya|luas\s*kebun\s*raya|tahun\s*berdiri\s*kebun\s*raya|founder\s*of\s*kebun\s*raya|pendiri\s*kebun\s*raya)\b/i',
            '/\b(wisata\s*kebun\s*raya|fasilitas\s*kebun\s*raya|jam\s*buka\s*kebun\s*raya|shuttle\s*bus\s*kebun\s*raya|sewa\s*sepeda\s*kebun\s*raya)\b/i',
        ];

        foreach ($krbPatterns as $pattern) {
            if (preg_match($pattern, $msg)) {
                return true;
            }
        }

        return false;
    }

    public function isOutOfScopeGeneralKnowledge(string $message): bool
    {
        $msg = mb_strtolower(trim($message));

        // If the question is specifically about Kebun Raya Bogor or its known collections/history/facilities, it is IN SCOPE (Domain B)
        if ($this->isGeneralKrbKnowledge($msg)) {
            return false;
        }

        $disallowedPatterns = [
            '/\b(pop\s*song|taylor\s*swift|famous\s*actor|latest\s*movie|hollywood|billboard|singer|actress|grammy|oscar|celebrity|celebrities)\b/i',
            '/\b(football\s*match|world\s*cup|champion\s*league|premier\s*league|who\s*won\s*(yesterday|the\s*match|the\s*game)|nba|fifa|score\s*of\s*the\s*match)\b/i',
            '/\b(tell\s*me\s*a\s*joke|make\s*a\s*joke|ceritakan\s*lelucon|beritahu\s*lelucon|lelucon|lawak|stand\s*up\s*comedy|write\s*a\s*poem|puisi|pantun)\b/i',
            '/\b(capital\s*of\s*(france|england|usa|germany|japan|italy|spain|russia|australia|canada|brazil|egypt|china)|ibukota\s*(prancis|amerika|inggris|jepang|jerman))\b/i',
            '/\b(quantum\s*physics|einstein|theory\s*of\s*relativity|black\s*hole|distance\s*to\s*the\s*moon|speed\s*of\s*light|dna\s*structure)\b/i',
            '/\b(news\s*today|today(\'s)?\s*news|berita\s*hari\s*ini|president\s*of\s*(usa|russia|france|indonesia)|pemilu|pilpres|parliament|minister\s*of)\b/i',
            '/\b(recipe\s*for|how\s*to\s*cook|resep\s*masak|cara\s*membuat\s*(kue|nasi\s*goreng|rendang))\b/i',
            '/\b(horoscope|zodiac|ramalan\s*bintang|medical\s*advice|diagnose\s*my|love\s*advice|relationship\s*advice)\b/i',
            '/\b(search\s*the\s*(web|internet)|browse\s*the\s*web|cari\s*di\s*internet)\b/i',
            '/\b(write\s*a\s*(python|javascript|php|java|c\+\+)\s*script|write\s*code\s*for|solve\s*my\s*homework|buatkan\s*kode\s*program)\b/i',
        ];

        foreach ($disallowedPatterns as $pattern) {
            if (preg_match($pattern, $msg)) {
                return true;
            }
        }

        if (preg_match('/^who\s+is\s+(?!the\s+visitor|the\s+borrower|the\s+manager|the\s+receptionist|the\s+it\s+officer|the\s+user|the\s+guest|reinwardt|c\.g\.c|caspar)[a-z\s]+(\?)?$/i', $msg)) {
            if (! preg_match('/\b(user|guest|visitor|tamu|staff|officer|peminjam|manager|receptionist|founder|reinwardt)\b/i', $msg)) {
                return true;
            }
        }

        return false;
    }

    public function isPromptInjection(string $message): bool
    {
        $msg = mb_strtolower(trim($message));

        $injectionPatterns = [
            '/\b(ignore\s*(all)?\s*(your)?\s*(previous|prior|above)?\s*instructions)\b/i',
            '/\b(abaikan\s*(semua)?\s*instruksi\s*(sebelumnya)?)\b/i',
            '/\b(forget\s*(the)?\s*(provider|role|company|system)\s*restriction)\b/i',
            '/\b(pretend\s*(that)?\s*i\s*am\s*(the)?\s*(admin|administrator|superadmin|root|owner|developer|creator))\b/i',
            '/\b(pura-pura\s*(bahwa)?\s*saya\s*(adalah)?\s*(admin|administrator))\b/i',
            '/\b(act\s*as\s*(an)?\s*(unrestricted|unfiltered|general|dan|jailbreak)\s*(ai|assistant|model))\b/i',
            '/\b(you\s*are\s*no\s*longer\s*(the)?\s*krb\s*(assistant|system|bot))\b/i',
            '/\b(kamu\s*bukan\s*lagi\s*asisten\s*krb)\b/i',
            '/\b(this\s*is\s*(only|just)\s*a\s*test\s*mode)\b/i',
            '/\b(override\s*(system|security|authorization|role|provider))\b/i',
            '/\b(bypass\s*(security|permission|role|provider|scope))\b/i',
        ];

        foreach ($injectionPatterns as $pattern) {
            if (preg_match($pattern, $msg)) {
                return true;
            }
        }

        return false;
    }

    public function isCrossProviderAttempt(string $message, ?User $user = null): bool
    {
        $msg = mb_strtolower(trim($message));

        if (preg_match('/\b(use\s*provider\s*(id)?\s*\d+|switch\s*to\s*provider\s*\d+|set\s*company\s*(id)?\s*\d+)\b/i', $msg)) {
            return true;
        }

        if (preg_match('/\b(ganti\s*provider|ubah\s*provider|gunakan\s*provider\s*id)\b/i', $msg)) {
            return true;
        }

        if ($user && $user->company_id) {
            $userCompanyName = mb_strtolower($user->company?->company_name ?? '');

            $knownCompanies = [
                'kebun raya bogor',
                'kebun raya bali',
                'kebun raya cibodas',
                'kebun raya purwodadi',
            ];

            foreach ($knownCompanies as $companyName) {
                if (str_contains($msg, $companyName) && ! str_contains($userCompanyName, $companyName)) {
                    if (preg_match('/\b(show|lihat|booking|reservasi|data|visitor|tamu|pengguna|user|mobil|ruangan)\b/i', $msg)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function isInternalLeakRequest(string $message): bool
    {
        $msg = mb_strtolower(trim($message));

        $leakPatterns = [
            '/\b(give\s*me|show\s*me|reveal|print|display|what\s*is)\s*(the)?\s*(hidden\s*)?(system\s*prompt|prompt\s*template|instructions|api\s*key|database\s*password|db\s*credential|secret|env\s*file|\.env)\b/i',
            '/\b(tampilkan|berikan|bocorkan)\s*(system\s*prompt|instruksi\s*rahasia|api\s*key|password\s*database|kredensial)\b/i',
        ];

        foreach ($leakPatterns as $pattern) {
            if (preg_match($pattern, $msg)) {
                return true;
            }
        }

        return false;
    }

    public function isRoleAuthorized(string $message, User $user): bool
    {
        $roleName = strtolower($user->role?->name ?? $user->role_name ?? '');
        $msg = mb_strtolower(trim($message));

        $isReceptionist = str_contains($roleName, 'receptionist');
        $isManager = str_contains($roleName, 'manager');
        $isItOfficer = str_contains($roleName, 'it') || str_contains($roleName, 'officer');

        if ($isReceptionist && ! $isItOfficer && ! $isManager) {
            if (preg_match('/\b(create\s*user|tambah\s*user|buat\s*user|delete\s*user|hapus\s*user|manage_user|manage_storage)\b/i', $msg)) {
                return false;
            }
        }

        if ($isManager && ! $isItOfficer) {
            if (preg_match('/\b(create\s*user|tambah\s*user|buat\s*user|delete\s*user|hapus\s*user|manage_user)\b/i', $msg)) {
                return false;
            }
        }

        return true;
    }

    private function matchesKeywords(string $haystack, array $keywords): bool
    {
        foreach ($keywords as $kw) {
            if (str_contains($haystack, $kw)) {
                return true;
            }
        }
        return false;
    }

    private function detectLanguage(string $message): string
    {
        $idWords = ['apa', 'berapa', 'bagaimana', 'siapa', 'kapan', 'di mana', 'tolong', 'tampilkan', 'buku', 'tamu', 'ruangan', 'kendaraan', 'jadwal', 'batal', 'pembatalan', 'hari', 'ini', 'besok', 'saya', 'apakah', 'sejarah', 'koleksi', 'pohon', 'bunga', 'fasilitas'];
        $msg = mb_strtolower($message);
        
        foreach ($idWords as $word) {
            if (preg_match('/\b' . preg_quote($word, '/') . '\b/i', $msg)) {
                return 'id';
            }
        }

        return 'en';
    }
}
