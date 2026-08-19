<?php

namespace App\Services\AI;

use App\Models\Company;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ScopeGuard
{
    private string $tz = 'Asia/Jakarta';

    /**
     * Standard scope refusal messages.
     */
    public const REFUSAL_EN = 'I can only assist with information and tasks related to your authorized KRB System context.';
    public const REFUSAL_ID = 'Saya hanya dapat membantu informasi dan tugas terkait konteks KRB System yang diotorisasi.';

    /**
     * Check if a message is a system utility request (e.g. current date/time).
     */
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

    /**
     * Handle a system utility request directly.
     */
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

    /**
     * Validate user message against allowed KRB System scope and authorized role/provider.
     * Returns array: ['allowed' => bool, 'reason' => string|null, 'refusal' => string|null]
     */
    public function validate(string $message, ?User $user = null): array
    {
        $msg = trim($message);
        $lang = $this->detectLanguage($msg);
        $refusal = $lang === 'id' ? self::REFUSAL_ID : self::REFUSAL_EN;

        if ($msg === '') {
            return ['allowed' => true, 'reason' => null, 'refusal' => null];
        }

        // 1. Check for prompt injection attempts trying to alter role, provider, or bypass instructions
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
            ];
        }

        // 2. Check for requests attempting to switch to or inspect unauthorized providers/companies
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
            ];
        }

        // 3. Check for requests attempting to access sensitive internal implementation or credentials
        if ($this->isInternalLeakRequest($msg)) {
            Log::warning('ScopeGuard: internal leak request blocked', [
                'user_id' => $user?->user_id,
                'message' => $msg,
            ]);
            return [
                'allowed' => false,
                'reason' => 'internal_leak_attempt',
                'refusal' => $refusal,
            ];
        }

        // 4. Check for system utilities (date/time)
        if ($this->isSystemUtility($msg)) {
            return [
                'allowed' => true,
                'is_utility' => true,
                'utility_response' => $this->handleSystemUtility($msg, $lang),
                'reason' => 'system_utility',
                'refusal' => null,
            ];
        }

        // 5. Check if the message is explicitly out-of-scope general-knowledge / entertainment
        if ($this->isOutOfScopeGeneralKnowledge($msg)) {
            Log::info('ScopeGuard: out-of-scope general knowledge question rejected', [
                'user_id' => $user?->user_id,
                'message' => $msg,
            ]);
            return [
                'allowed' => false,
                'reason' => 'out_of_scope_general_knowledge',
                'refusal' => $refusal,
            ];
        }

        // 6. Role-based unauthorized operations check
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
            ];
        }

        return ['allowed' => true, 'reason' => null, 'refusal' => null];
    }

    /**
     * Detect general knowledge, trivia, pop culture, entertainment, sports, politics,
     * jokes, personal advice, etc. that do not belong to KRB facility management.
     */
    public function isOutOfScopeGeneralKnowledge(string $message): bool
    {
        $msg = mb_strtolower(trim($message));

        // Obvious general trivia / entertainment patterns
        $disallowedPatterns = [
            // Pop culture, songs, singers, actors, movies
            '/\b(pop\s*song|taylor\s*swift|famous\s*actor|latest\s*movie|hollywood|billboard|singer|actress|grammy|oscar|celebrity|celebrities)\b/i',
            // Sports & football matches
            '/\b(football\s*match|world\s*cup|champion\s*league|premier\s*league|who\s*won\s*(yesterday|the\s*match|the\s*game)|nba|fifa|score\s*of\s*the\s*match)\b/i',
            // Jokes & poems
            '/\b(tell\s*me\s*a\s*joke|make\s*a\s*joke|ceritakan\s*lelucon|beritahu\s*lelucon|lelucon|lawak|stand\s*up\s*comedy|write\s*a\s*poem|puisi|pantun)\b/i',
            // General geography / capitals / countries trivia
            '/\b(capital\s*of\s*(france|england|usa|germany|japan|italy|spain|russia|australia|canada|brazil|egypt|china)|ibukota\s*(prancis|amerika|inggris|jepang|jerman))\b/i',
            // General science / physics / astronomy
            '/\b(quantum\s*physics|einstein|theory\s*of\s*relativity|black\s*hole|distance\s*to\s*the\s*moon|speed\s*of\s*light|photosynthesis|dna\s*structure)\b/i',
            // General politics & world news
            '/\b(news\s*today|today(\'s)?\s*news|berita\s*hari\s*ini|president\s*of\s*(usa|russia|france|indonesia)|pemilu|pilpres|parliament|minister\s*of)\b/i',
            // General recipe / cooking
            '/\b(recipe\s*for|how\s*to\s*cook|resep\s*masak|cara\s*membuat\s*(kue|nasi\s*goreng|rendang))\b/i',
            // General personal advice / health / horoscope
            '/\b(horoscope|zodiac|ramalan\s*bintang|medical\s*advice|diagnose\s*my|love\s*advice|relationship\s*advice)\b/i',
            // Search web / internet
            '/\b(search\s*the\s*(web|internet)|browse\s*the\s*web|cari\s*di\s*internet)\b/i',
        ];

        foreach ($disallowedPatterns as $pattern) {
            if (preg_match($pattern, $msg)) {
                return true;
            }
        }

        // Questions starting with "who is [celebrity]" or "what is the capital of"
        if (preg_match('/^who\s+is\s+(?!the\s+visitor|the\s+borrower|the\s+manager|the\s+receptionist|the\s+it\s+officer|the\s+user|the\s+guest)[a-z\s]+(\?)?$/i', $msg)) {
            // If it's a person not related to system, reject
            if (! preg_match('/\b(user|guest|visitor|tamu|staff|officer|peminjam|manager|receptionist)\b/i', $msg)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect prompt injection / jailbreak patterns.
     */
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

    /**
     * Detect attempts to switch provider or access other providers' data.
     */
    public function isCrossProviderAttempt(string $message, ?User $user = null): bool
    {
        $msg = mb_strtolower(trim($message));

        // Patterns trying to force another provider ID or provider switch
        if (preg_match('/\b(use\s*provider\s*(id)?\s*\d+|switch\s*to\s*provider\s*\d+|set\s*company\s*(id)?\s*\d+)\b/i', $msg)) {
            return true;
        }

        if (preg_match('/\b(ganti\s*provider|ubah\s*provider|gunakan\s*provider\s*id)\b/i', $msg)) {
            return true;
        }

        // If user is bound to a specific company, detect explicit attempts to query another company's specific name
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
                    // Asking explicitly about another facility's private bookings/data
                    if (preg_match('/\b(show|lihat|booking|reservasi|data|visitor|tamu|pengguna|user|mobil|ruangan)\b/i', $msg)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Detect attempts to reveal internal system prompts, tokens, API keys, database credentials.
     */
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

    /**
     * Check if the user's role is authorized for the requested action.
     */
    public function isRoleAuthorized(string $message, User $user): bool
    {
        $roleName = strtolower($user->role?->name ?? $user->role_name ?? '');
        $msg = mb_strtolower(trim($message));

        $isReceptionist = str_contains($roleName, 'receptionist');
        $isManager = str_contains($roleName, 'manager');
        $isItOfficer = str_contains($roleName, 'it') || str_contains($roleName, 'officer');

        // Receptionist cannot perform IT management write operations (creating users, changing storage, etc.)
        if ($isReceptionist && ! $isItOfficer && ! $isManager) {
            if (preg_match('/\b(create\s*user|tambah\s*user|buat\s*user|delete\s*user|hapus\s*user|manage_user|manage_storage)\b/i', $msg)) {
                return false;
            }
        }

        // Manager cannot create users via chat
        if ($isManager && ! $isItOfficer) {
            if (preg_match('/\b(create\s*user|tambah\s*user|buat\s*user|delete\s*user|hapus\s*user|manage_user)\b/i', $msg)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Detect language based on simple word heuristics.
     */
    private function detectLanguage(string $message): string
    {
        $idWords = ['apa', 'berapa', 'bagaimana', 'siapa', 'kapan', 'di mana', 'tolong', 'tampilkan', 'buku', 'tamu', 'ruangan', 'kendaraan', 'jadwal', 'batal', 'pembatalan', 'hari', 'ini', 'besok', 'saya', 'apakah'];
        $msg = mb_strtolower($message);
        
        foreach ($idWords as $word) {
            if (preg_match('/\b' . preg_quote($word, '/') . '\b/i', $msg)) {
                return 'id';
            }
        }

        return 'en';
    }
}
