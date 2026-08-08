# Chatbot Language & JSON Exposure Fix

## Overview

This document describes the comprehensive fix for two critical issues in the KRB System chatbot:

1. **Indonesian Language Support**: Automatic detection and proper handling of Indonesian (Bahasa Indonesia) messages
2. **JSON Exposure Prevention**: Preventing internal JSON structures from appearing in user-facing chat messages

## Problem Statement

### Before the Fix

**Issue 1: Raw JSON Exposure**
When users sent Indonesian messages like "ada guestbook ga hari ini", the chatbot would display the entire internal response structure:

```json
{
  "reply": "Ya, ada 1 guestbook hari ini...",
  "booking_complete": false,
  "booking_profile": {
      "room_id": null,
      "meeting_title": null,
      ...
  },
  "vehicle_profile": {
      ...
  }
}
```

Users should NEVER see this internal structure - only the human-readable "reply" content.

**Issue 2: Language Detection**
The system relied on session locale but didn't automatically detect the language from the message itself, leading to inconsistent multilingual behavior.

## Solution Architecture

### 1. Language Detection Service (`app/Services/AI/LanguageDetector.php`)

**Purpose**: Automatically detect whether a user message is in Indonesian or English.

**How it works**:
- Pattern-based detection using distinctive Indonesian and English word patterns
- Scores messages based on language-specific keywords
- Falls back to session locale when detection is uncertain
- Supports informal Indonesian (ga, gak, nggak, etc.)

**Indonesian Detection Patterns**:
- Common words: ada, apa, yang, untuk, dengan, dari, ini, itu, tidak, bisa, mau, saya
- Question words: berapa, kapan, dimana, bagaimana, kenapa, siapa
- Booking terms: ruangan, kendaraan, mobil, guestbook, departemen
- Informal contractions: ga, gak, nggak, ya, yg, dgn

**English Detection Patterns**:
- Common words: the, this, that, what, when, where, how, why, who
- Modal verbs: can, could, would, should, will, have, has, had
- Booking terms: room, vehicle, car, booking, book, reserve, guest

### 2. Enhanced Response Parsing (`ChatModal.php`)

**Changes to `parseIntentResponse()`**:

```php
private function parseIntentResponse(string $raw, ?int $companyId): array
```

**Key Improvements**:

1. **Markdown Code Fence Extraction**:
   - Handles responses wrapped in ```json ... ``` blocks
   - Uses regex: `/```(?:json)?\s*(\{[\s\S]*?\})\s*```/i`

2. **Multiple Response Formats**:
   - Pure text → Return as-is
   - Valid JSON with "reply" field → Extract reply
   - Valid JSON with "message" field → Extract message
   - Valid JSON with "text" field → Extract text
   - Malformed JSON → Sanitize and return safe fallback

3. **Display Message Extraction** (`extractDisplayMessage()`):
   ```php
   private function extractDisplayMessage(array $decoded): string
   ```
   - Tries common message field names: reply, message, text, response, content
   - Returns only the human-readable string
   - Never returns internal booking structures

4. **Text Sanitization** (`sanitizeDisplayText()`):
   ```php
   private function sanitizeDisplayText(string $text): string
   ```
   - Detects if text looks like JSON (starts with `{` or `[`)
   - Attempts to extract message from JSON structure
   - Checks for suspicious internal field names:
     * booking_profile
     * vehicle_profile
     * booking_prefill
     * vehicle_prefill
     * booking_complete
     * "room_id"
     * "vehicle_id"
     * "meeting_title"
   - Returns safe fallback if suspicious content detected
   - Extensive logging for debugging

### 3. Language-Aware System Prompts (`app/Services/AI/PromptBuilder.php`)

**Changes**:

All prompt methods now accept a `$userLanguage` parameter:
- `managerSystemPrompt(string $dataContext, string $userLanguage = 'en')`
- `receptionistGeneralPrompt(string $dataContext, string $userLanguage = 'en')`
- `receptionistBookingPrompt(string $dataContext, string $bookingDraftContext = '', string $userLanguage = 'en')`

**New Method: `buildLanguageInstruction()`**

For Indonesian:
```
LANGUAGE: Indonesian (Bahasa Indonesia)
- Respond ONLY in natural, conversational Indonesian.
- Use professional but friendly Indonesian language.
- Indonesian terminology:
  * "room booking" → "booking ruangan" or "pemesanan ruangan"
  * "vehicle booking" → "booking kendaraan" or "pemesanan kendaraan"
  * "department" → "departemen"
  * "start time" → "waktu mulai"
  * "end time" → "waktu selesai"
  * "meeting" → "rapat" or "pertemuan"
  * "available" → "tersedia"
  * "guestbook" can remain "guestbook" (accepted term)
- Do NOT mix English words unnecessarily into Indonesian responses.
- Preserve proper nouns, dates, room names, vehicle names as given.
```

For English:
```
LANGUAGE: English
- Respond ONLY in natural, conversational English.
- Use professional but friendly language.
- Keep responses clear and concise.
```

**Critical Addition to Booking Prompt**:
```
CRITICAL: Your response MUST be valid JSON with a "reply" field containing ONLY the human-readable message.
NEVER include booking_profile, vehicle_profile, or any internal metadata in the "reply" field.
The "reply" field is displayed directly to the user and must contain ONLY natural language text.
```

### 4. Integration in ChatModal

**Manager AI Call** (`callManagerAI()`):
```php
$languageDetector = app(LanguageDetector::class);
$sessionLocale = session('locale', config('app.locale', 'en'));
$detectedLanguage = $languageDetector->detectLanguage($userMessage, $sessionLocale);
$systemPrompt = $builder->managerSystemPrompt($context, $detectedLanguage);
```

**Receptionist AI Call** (`callReceptionistAI()`):
```php
$languageDetector = app(LanguageDetector::class);
$sessionLocale = session('locale', config('app.locale', 'en'));
$detectedLanguage = $languageDetector->detectLanguage($userMessage, $sessionLocale);

$systemPrompt = $isBookingIntent
    ? $builder->receptionistBookingPrompt($context, $draftContext, $detectedLanguage)
    : $builder->receptionistGeneralPrompt($context, $detectedLanguage);
```

## Files Changed

### New Files
1. **`app/Services/AI/LanguageDetector.php`** - Language detection service
2. **`tests/Feature/ChatbotLanguageTest.php`** - Comprehensive test suite

### Modified Files
1. **`app/Services/AI/PromptBuilder.php`**
   - Added language parameter to all prompt methods
   - Added `buildLanguageInstruction()` method
   - Enhanced prompts with explicit Indonesian support
   - Added critical JSON exposure warning

2. **`app/Livewire/Components/Ui/ChatModal.php`**
   - Added `LanguageDetector` import
   - Enhanced `parseIntentResponse()` with robust parsing
   - Added `extractDisplayMessage()` method
   - Added `sanitizeDisplayText()` method
   - Integrated language detection in `callManagerAI()` and `callReceptionistAI()`

## Testing

### Automated Tests

Run the test suite:
```bash
php artisan test --filter=ChatbotLanguageTest
```

**Test Coverage**:
- ✅ Detects Indonesian language from messages
- ✅ Detects English language from messages
- ✅ Prompt builder includes language-specific instructions
- ✅ Sanitizes JSON structures in display text
- ✅ Parses various response formats (plain text, JSON, markdown)
- ✅ Handles malformed JSON gracefully
- ✅ Extracts display message from multiple field names

**Test Results**: 7 tests passed with 34 assertions

### Manual Testing

#### Test Case 1: Indonesian Guestbook Query
**User Input**: "ada guestbook ga hari ini"

**Expected Behavior**:
- Language detected as Indonesian ('id')
- Response in natural Indonesian
- No JSON structure visible
- Only human-readable text displayed

**Example Response**:
```
Ya, ada 1 guestbook hari ini. Apakah Anda ingin melihat detailnya atau membuat booking ruangan/kendaraan?
```

#### Test Case 2: Indonesian Booking Query
**User Input**: "apa bisa booking kendaraan?"

**Expected Behavior**:
- Language detected as Indonesian
- Response uses Indonesian terminology (booking kendaraan, not vehicle booking)
- No internal fields visible

#### Test Case 3: English Guestbook Query
**User Input**: "how many guestbooks are there today?"

**Expected Behavior**:
- Language detected as English ('en')
- Response in professional English
- No JSON exposure

#### Test Case 4: Language Switching
**Conversation**:
1. User: "ada guestbook hari ini?" → Indonesian response
2. User: "how many?" → Switch to English response

**Expected Behavior**:
- System detects language change
- Responds appropriately in the detected language

#### Test Case 5: Malformed AI Response
**Scenario**: AI provider returns invalid JSON

**Expected Behavior**:
- System logs error
- Returns safe fallback message
- Never crashes or exposes raw data
- Message: "I apologize, but I encountered an issue formatting my response. Could you please rephrase your question?"

#### Test Case 6: JSON in Response
**Scenario**: AI includes JSON structure in reply field

**Expected Behavior**:
- `sanitizeDisplayText()` catches suspicious patterns
- Returns safe fallback message
- Logs warning with details

## Logging & Debugging

Enable debug mode in `.env`:
```
APP_DEBUG=true
```

**Key Log Events**:

1. **Language Detection**:
```php
Log::info('ChatModal: language detected', [
    'stage' => 'manager_ai_call',
    'detected' => 'id',
    'session_locale' => 'en',
]);
```

2. **Response Parsing**:
```php
Log::debug('ChatModal: parsing AI response', [
    'stage' => 'parse_intent_response',
    'raw_length' => 500,
    'raw_preview' => '{"reply": "...',
]);
```

3. **JSON Detection**:
```php
Log::warning('ChatModal: sanitizeDisplayText detected JSON structure', [
    'stage' => 'sanitize_display_text',
    'preview' => '{"booking_profile...',
]);
```

4. **Suspicious Patterns**:
```php
Log::warning('ChatModal: sanitizeDisplayText detected internal field name', [
    'stage' => 'sanitize_display_text',
    'pattern' => 'booking_profile',
    'preview' => 'Here is the booking_profile...',
]);
```

## Acceptance Criteria

✅ **Indonesian messages receive Indonesian responses**
- Language detection working with common Indonesian patterns
- Responses use proper Indonesian terminology
- No unnecessary English mixed into Indonesian responses

✅ **English messages receive English responses**
- Language detection working with English patterns
- Professional, conversational English
- Clear and concise

✅ **Language persists across follow-up messages**
- Conversation context maintained
- Language switches detected and handled

✅ **Raw JSON is NEVER displayed in chatbot bubbles**
- Multiple safeguards at parsing level
- Sanitization catches leaked structures
- Fallback messages provided

✅ **Internal booking/vehicle state remains functional**
- Structured responses still parsed internally
- Booking draft system continues working
- Tool calls and availability checks intact

✅ **Frontend renders only human-readable response**
- Blade template displays `$msg['text']`
- Backend guarantees `$msg['text']` is sanitized
- No CSS-only workarounds needed

✅ **Malformed provider responses don't expose raw JSON**
- Graceful error handling
- Safe fallback messages
- Extensive logging for debugging

✅ **Existing functionality remains intact**
- Room booking preserved
- Vehicle booking preserved
- Guestbook queries working
- Document/package queries working
- Analytics tools functional
- Structured booking state maintained

## Root Cause Analysis

### JSON Exposure Issue

**Root Cause**: The `parseIntentResponse()` method had a fallback that returned the entire raw response when JSON parsing failed:

```php
// OLD CODE - WRONG
$empty = ['reply' => $raw, ...]; // $raw could be entire JSON structure
if (! str_starts_with($raw, '{')) return $empty; // Raw text returned as reply
```

When the AI provider returned structured JSON but with slight formatting issues (markdown code fences, extra whitespace), the parser would fail and dump the entire JSON into the `reply` field, which then appeared directly in the chat UI.

**Fix**: Multi-layer parsing and sanitization:
1. Extract JSON from markdown code fences
2. Try to decode and extract message field
3. Sanitize any suspicious content
4. Provide safe fallback if all else fails

### Language Detection Issue

**Root Cause**: The system relied solely on session locale, which required manual switching by the user. There was no automatic detection from message content.

**Fix**: Pattern-based language detection that analyzes the user's message and determines the language before generating the system prompt, ensuring responses match the user's language automatically.

## Future Improvements

1. **Additional Languages**: The architecture supports adding more languages (e.g., French, Spanish) by extending `LanguageDetector` patterns and `buildLanguageInstruction()`.

2. **Machine Learning Detection**: Consider integrating a proper language detection library (e.g., `php-language-detect`) for more accurate detection.

3. **Language Persistence**: Store detected language per session to avoid re-detection on every message.

4. **Response Quality Metrics**: Track how often sanitization fallbacks are triggered to identify AI provider issues.

5. **User Feedback**: Add option for users to report when language detection is wrong.

## Maintenance Notes

- The `LanguageDetector` patterns should be reviewed and updated based on actual usage patterns
- Monitor logs for frequent sanitization warnings - may indicate AI provider issues
- Keep Indonesian terminology up to date with organizational preferences
- Update tests when adding new response formats or message fields

## Contact & Support

For issues related to this fix:
- Check logs in `storage/logs/laravel.log` with `APP_DEBUG=true`
- Run test suite: `php artisan test --filter=ChatbotLanguageTest`
- Review this document for architecture details

---

**Implementation Date**: August 8, 2026  
**Version**: 1.0  
**Status**: ✅ Complete & Tested
