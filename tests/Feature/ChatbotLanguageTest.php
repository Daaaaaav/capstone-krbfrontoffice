<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\AI\LanguageDetector;
use App\Services\AI\PromptBuilder;
use App\Livewire\Components\Ui\ChatModal;

class ChatbotLanguageTest extends TestCase
{
    /**
     * Test language detection for Indonesian messages
     */
    public function test_detects_indonesian_language(): void
    {
        $detector = new LanguageDetector();
        
        $indonesianMessages = [
            'ada guestbook ga hari ini',
            'berapa jumlah guestbook hari ini?',
            'ada booking ruangan hari ini?',
            'apa bisa booking kendaraan?',
            'saya mau pesan ruangan untuk besok',
            'kapan ruang meeting tersedia?',
        ];
        
        foreach ($indonesianMessages as $message) {
            $detected = $detector->detectLanguage($message, 'en');
            $this->assertEquals('id', $detected, "Failed to detect Indonesian for: {$message}");
        }
    }
    
    /**
     * Test language detection for English messages
     */
    public function test_detects_english_language(): void
    {
        $detector = new LanguageDetector();
        
        $englishMessages = [
            'how many guestbooks are there today?',
            'can I book a room?',
            'show me available vehicles',
            'what rooms are available tomorrow?',
            'I need to reserve a meeting room',
        ];
        
        foreach ($englishMessages as $message) {
            $detected = $detector->detectLanguage($message, 'id');
            $this->assertEquals('en', $detected, "Failed to detect English for: {$message}");
        }
    }
    
    /**
     * Test that prompt builder includes language-specific instructions
     */
    public function test_prompt_builder_includes_language_instructions(): void
    {
        $builder = new PromptBuilder();
        
        // Test Indonesian prompt
        $idPrompt = $builder->receptionistGeneralPrompt('test context', 'id');
        $this->assertStringContainsString('Indonesian', $idPrompt);
        $this->assertStringContainsString('booking ruangan', $idPrompt);
        $this->assertStringContainsString('booking kendaraan', $idPrompt);
        
        // Test English prompt
        $enPrompt = $builder->receptionistGeneralPrompt('test context', 'en');
        $this->assertStringContainsString('English', $enPrompt);
        $this->assertStringContainsString('natural, conversational', $enPrompt);
    }
    
    /**
     * Test JSON sanitization prevents exposure of internal structures
     */
    public function test_sanitizes_json_in_display_text(): void
    {
        // Use reflection to test private method
        $chatModal = new ChatModal();
        $reflection = new \ReflectionClass($chatModal);
        $method = $reflection->getMethod('sanitizeDisplayText');
        $method->setAccessible(true);
        
        // Test plain text passes through
        $plainText = 'Ya, ada 1 guestbook hari ini.';
        $result = $method->invoke($chatModal, $plainText);
        $this->assertEquals($plainText, $result);
        
        // Test JSON structure gets sanitized
        $jsonText = '{"reply": "Test message", "booking_profile": {"room_id": 5}}';
        $result = $method->invoke($chatModal, $jsonText);
        $this->assertStringNotContainsString('booking_profile', $result);
        $this->assertStringNotContainsString('room_id', $result);
        
        // Test suspicious patterns are caught
        $suspiciousText = 'Here is the booking_profile with room_id 5';
        $result = $method->invoke($chatModal, $suspiciousText);
        $this->assertStringContainsString('issue formatting', $result);
    }
    
    /**
     * Test response parsing extracts display message correctly
     */
    public function test_parses_various_response_formats(): void
    {
        $chatModal = new ChatModal();
        $reflection = new \ReflectionClass($chatModal);
        $method = $reflection->getMethod('parseIntentResponse');
        $method->setAccessible(true);
        
        // Test plain text
        $plainResponse = 'This is a simple response';
        $result = $method->invoke($chatModal, $plainResponse, null);
        $this->assertEquals($plainResponse, $result['reply']);
        $this->assertEmpty($result['booking_prefill']);
        
        // Test valid JSON
        $jsonResponse = json_encode([
            'reply' => 'Ya, ada 1 guestbook hari ini.',
            'booking_complete' => false,
            'booking_prefill' => ['meeting_title' => null],
            'vehicle_prefill' => ['vehicle_id' => null],
        ]);
        $result = $method->invoke($chatModal, $jsonResponse, null);
        $this->assertEquals('Ya, ada 1 guestbook hari ini.', $result['reply']);
        $this->assertArrayHasKey('booking_prefill', $result);
        
        // Test JSON with markdown code fence
        $markdownResponse = "```json\n" . $jsonResponse . "\n```";
        $result = $method->invoke($chatModal, $markdownResponse, null);
        $this->assertEquals('Ya, ada 1 guestbook hari ini.', $result['reply']);
        
        // Test JSON with alternative message field
        $altResponse = json_encode(['message' => 'Alternative field', 'booking_complete' => false]);
        $result = $method->invoke($chatModal, $altResponse, null);
        $this->assertEquals('Alternative field', $result['reply']);
    }
    
    /**
     * Test that malformed JSON doesn't crash the parser
     */
    public function test_handles_malformed_json_gracefully(): void
    {
        $chatModal = new ChatModal();
        $reflection = new \ReflectionClass($chatModal);
        $method = $reflection->getMethod('parseIntentResponse');
        $method->setAccessible(true);
        
        $malformedJson = '{"reply": "Test", "booking_complete": false'; // Missing closing brace
        $result = $method->invoke($chatModal, $malformedJson, null);
        
        // Should return a sanitized response, not crash
        $this->assertIsString($result['reply']);
        $this->assertNotEmpty($result['reply']);
        $this->assertStringNotContainsString('booking_complete', $result['reply']);
    }
    
    /**
     * Test extract display message finds the right field
     */
    public function test_extracts_display_message_from_multiple_formats(): void
    {
        $chatModal = new ChatModal();
        $reflection = new \ReflectionClass($chatModal);
        $method = $reflection->getMethod('extractDisplayMessage');
        $method->setAccessible(true);
        
        // Test 'reply' field
        $data = ['reply' => 'Message 1', 'other' => 'data'];
        $result = $method->invoke($chatModal, $data);
        $this->assertEquals('Message 1', $result);
        
        // Test 'message' field
        $data = ['message' => 'Message 2', 'other' => 'data'];
        $result = $method->invoke($chatModal, $data);
        $this->assertEquals('Message 2', $result);
        
        // Test 'text' field
        $data = ['text' => 'Message 3', 'other' => 'data'];
        $result = $method->invoke($chatModal, $data);
        $this->assertEquals('Message 3', $result);
        
        // Test priority (reply should win)
        $data = ['reply' => 'Priority message', 'message' => 'Secondary', 'text' => 'Tertiary'];
        $result = $method->invoke($chatModal, $data);
        $this->assertEquals('Priority message', $result);
        
        // Test no valid field returns empty string
        $data = ['booking_profile' => 'Should not be extracted'];
        $result = $method->invoke($chatModal, $data);
        $this->assertEquals('', $result);
    }
}
