<?php

namespace App\Services\AI;

class LanguageDetector
{
    /**
     * Detect language from user message.
     * 
     * Returns 'id' for Indonesian, 'en' for English.
     * Falls back to session locale if detection is uncertain.
     */
    public function detectLanguage(string $message, ?string $fallbackLocale = null): string
    {
        $message = mb_strtolower(trim($message));
        
        // Empty message - use fallback
        if (empty($message)) {
            return $this->normalizeLocale($fallbackLocale);
        }
        
        // Indonesian-specific words/patterns (common words that are distinctive)
        $indonesianPatterns = [
            // Common Indonesian words
            '/\b(ada|apa|yang|untuk|dengan|dari|ini|itu|tidak|bisa|mau|saya|kami|kita)\b/',
            // Indonesian question words
            '/\b(berapa|kapan|dimana|bagaimana|kenapa|siapa)\b/',
            // Indonesian verbs
            '/\b(lihat|cari|buat|pesan|booking|punya|butuh|minta)\b/',
            // Indonesian specific patterns
            '/\b(ga|gak|nggak|ya|yg|dgn|utk)\b/', // informal contractions
            '/\b(hari\s+ini|besok|kemarin|minggu|bulan)\b/',
            // Indonesian booking terminology
            '/\b(ruangan|kendaraan|mobil|guestbook|departemen)\b/',
        ];
        
        // English-specific words/patterns
        $englishPatterns = [
            // Common English words that don't appear in Indonesian
            '/\b(the|this|that|what|when|where|how|why|who)\b/',
            '/\b(can|could|would|should|will|have|has|had)\b/',
            '/\b(today|tomorrow|yesterday|week|month|year)\b/',
            '/\b(room|vehicle|car|booking|book|reserve|guest)\b/',
            '/\b(show|find|create|need|want|get|list)\b/',
        ];
        
        $indonesianScore = 0;
        $englishScore = 0;
        
        // Count Indonesian patterns
        foreach ($indonesianPatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                $indonesianScore++;
            }
        }
        
        // Count English patterns
        foreach ($englishPatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                $englishScore++;
            }
        }
        
        // Decisive match
        if ($indonesianScore > $englishScore) {
            return 'id';
        } elseif ($englishScore > $indonesianScore) {
            return 'en';
        }
        
        // Tie or no matches - use fallback
        return $this->normalizeLocale($fallbackLocale);
    }
    
    /**
     * Get language name for display
     */
    public function getLanguageName(string $locale): string
    {
        return match($locale) {
            'id' => 'Indonesian',
            'en' => 'English',
            default => 'English',
        };
    }
    
    /**
     * Normalize locale to supported value
     */
    private function normalizeLocale(?string $locale): string
    {
        if (in_array($locale, ['id', 'en'], true)) {
            return $locale;
        }
        
        // Default to session locale or English
        $sessionLocale = session('locale', config('app.locale', 'en'));
        return in_array($sessionLocale, ['id', 'en'], true) ? $sessionLocale : 'en';
    }
}
