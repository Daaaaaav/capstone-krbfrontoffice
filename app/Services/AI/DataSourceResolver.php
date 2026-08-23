<?php

namespace App\Services\AI;

use App\Models\BookingRoom;
use App\Models\Delivery;
use App\Models\Guestbook;
use App\Models\VehicleBooking;
use App\Services\AI\Enums\ChatDataSource;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DataSourceResolver
{
    private string $tz = 'Asia/Jakarta';

    public function __construct(
        private ?CsvDataReader $csvReader = null,
        private ?ScopeGuard $scopeGuard = null
    ) {
        $this->csvReader = $csvReader ?? app(CsvDataReader::class);
        $this->scopeGuard = $scopeGuard ?? app(ScopeGuard::class);
    }

    /**
     * Detect data source preference from natural language query and context history.
     */
    public function detectSourcePreference(string $message, array $history = [], ?string $sessionPreference = null): ChatDataSource
    {
        $msg = mb_strtolower(trim($message));

        // 1. Explicit Server CSV triggers
        $csvPhrases = [
            'from the server csv',
            'from the csv',
            'use the csv',
            'from server csv',
            'historical csv',
            'server historical data',
            'server historical csv',
            'krb_historical_data.csv',
            'csv data only',
            'csv data',
            'csv only',
            'dari server csv',
            'dari csv',
            'gunakan csv',
            'csv historis',
            'data historis csv',
            'file csv',
        ];

        foreach ($csvPhrases as $phrase) {
            if (str_contains($msg, $phrase)) {
                return ChatDataSource::SERVER_CSV;
            }
        }

        // 2. Explicit End-to-End / Live System Data triggers
        $livePhrases = [
            'use only the live system data',
            'use only the live system',
            'use the live system data',
            'use live system data',
            'live system data only',
            'only the live system data',
            'only live system data',
            'from the live system',
            'live system data',
            'live data only',
            'only live data',
            'live data',
            'live system',
            'current system data',
            'end-to-end data',
            'database data',
            'application data',
            'current records',
            'live system only',
            'live only',
            'don\'t use the csv',
            'dont use the csv',
            'without csv',
            'dari sistem langsung',
            'data live',
            'data sistem saat ini',
            'hanya data live',
            'gunakan data live',
            'hanya sistem live',
            'jangan gunakan csv',
            'tanpa csv',
        ];

        foreach ($livePhrases as $phrase) {
            if (str_contains($msg, $phrase)) {
                return ChatDataSource::END_TO_END;
            }
        }

        // 3. Explicit External / KRB Knowledge Base triggers
        $externalPhrases = [
            'use the kebun raya bogor knowledge dataset',
            'from the krb knowledge base',
            'external dataset',
            'knowledge base only',
            'use knowledge base',
            'dari knowledge base',
            'basis pengetahuan',
        ];

        foreach ($externalPhrases as $phrase) {
            if (str_contains($msg, $phrase)) {
                return ChatDataSource::KRB_KNOWLEDGE_BASE;
            }
        }

        // 4. Check for session continuation (if user previously requested CSV and query is an immediate follow-up)
        if ($sessionPreference === ChatDataSource::SERVER_CSV->value && $this->isShortFollowUp($msg)) {
            return ChatDataSource::SERVER_CSV;
        }

        // 5. Default strategy: COMBINED_AUTO
        return ChatDataSource::COMBINED_AUTO;
    }

    /**
     * Check if a message is a short follow-up referencing previous context.
     */
    private function isShortFollowUp(string $message): bool
    {
        $words = preg_split('/\s+/', trim($message));
        if (count($words) <= 6) {
            if (preg_match('/^(what\s+about|how\s+about|and|then|bagaimana\s+dengan|lalu|kalau)\b/i', $message)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get date coverage for Live System Data.
     */
    public function getLiveCoverage(int $companyId): array
    {
        $earliestRoom = BookingRoom::where('company_id', $companyId)->min('date');
        $latestRoom = BookingRoom::where('company_id', $companyId)->max('date');

        $earliestVeh = VehicleBooking::where('company_id', $companyId)->min('start_at');
        $latestVeh = VehicleBooking::where('company_id', $companyId)->max('start_at');

        $earliestGuest = Guestbook::where('company_id', $companyId)->min('date');
        $latestGuest = Guestbook::where('company_id', $companyId)->max('date');

        $earliestDel = Delivery::where('company_id', $companyId)->min('created_at');
        $latestDel = Delivery::where('company_id', $companyId)->max('created_at');

        $starts = array_filter([
            $earliestRoom,
            $earliestVeh ? substr($earliestVeh, 0, 10) : null,
            $earliestGuest,
            $earliestDel ? substr($earliestDel, 0, 10) : null,
        ]);

        $ends = array_filter([
            $latestRoom,
            $latestVeh ? substr($latestVeh, 0, 10) : null,
            $latestGuest,
            $latestDel ? substr($latestDel, 0, 10) : null,
        ]);

        $startDate = ! empty($starts) ? min($starts) : Carbon::now($this->tz)->startOfYear()->toDateString();
        $endDate = ! empty($ends) ? max($ends) : Carbon::now($this->tz)->toDateString();

        return [
            'type'       => 'end_to_end',
            'start_date' => $startDate,
            'end_date'   => $endDate,
            'has_data'   => ! empty($starts),
        ];
    }

    /**
     * Get date coverage for Server Historical CSV.
     */
    public function getCsvCoverage(): array
    {
        $info = $this->csvReader->serverCsvInfo();

        return [
            'type'       => 'server_csv',
            'start_date' => $info['start'] ?? null,
            'end_date'   => $info['end'] ?? null,
            'total_rows' => $info['rows'] ?? 0,
            'has_data'   => ($info['rows'] ?? 0) > 0,
        ];
    }

    /**
     * Classify the relationship and overlap between Live data and Server CSV.
     */
    public function analyzeCoverageRelationship(int $companyId): array
    {
        $live = $this->getLiveCoverage($companyId);
        $csv = $this->getCsvCoverage();

        if (! $csv['has_data'] && ! $live['has_data']) {
            return [
                'case'             => 'no_data',
                'description'      => 'Neither Live data nor Server CSV contains records.',
                'overlap'          => false,
                'overlap_start'    => null,
                'overlap_end'      => null,
                'live_coverage'    => $live,
                'csv_coverage'     => $csv,
            ];
        }

        if (! $csv['has_data']) {
            return [
                'case'             => 'live_only',
                'description'      => 'Only Live system data is available.',
                'overlap'          => false,
                'overlap_start'    => null,
                'overlap_end'      => null,
                'live_coverage'    => $live,
                'csv_coverage'     => $csv,
            ];
        }

        if (! $live['has_data']) {
            return [
                'case'             => 'csv_only',
                'description'      => 'Only Server CSV data is available.',
                'overlap'          => false,
                'overlap_start'    => null,
                'overlap_end'      => null,
                'live_coverage'    => $live,
                'csv_coverage'     => $csv,
            ];
        }

        $csvStart = $csv['start_date'];
        $csvEnd = $csv['end_date'];
        $liveStart = $live['start_date'];
        $liveEnd = $live['end_date'];

        // Check if there is overlap
        $overlapStart = max($csvStart, $liveStart);
        $overlapEnd = min($csvEnd, $liveEnd);

        if ($overlapStart <= $overlapEnd) {
            // Overlap exists
            $isFullOverlap = ($csvStart >= $liveStart && $csvEnd <= $liveEnd) || ($liveStart >= $csvStart && $liveEnd <= $csvEnd);

            return [
                'case'             => $isFullOverlap ? 'full_overlap' : 'partial_overlap',
                'description'      => $isFullOverlap
                    ? 'Live data and Server CSV fully overlap in time period.'
                    : "Partial overlap between {$overlapStart} and {$overlapEnd}.",
                'overlap'          => true,
                'overlap_start'    => $overlapStart,
                'overlap_end'      => $overlapEnd,
                'live_coverage'    => $live,
                'csv_coverage'     => $csv,
                'authoritative'    => 'end_to_end', // Live system data is authoritative for overlapping periods
            ];
        }

        // Case A: No overlap
        return [
            'case'             => 'no_overlap',
            'description'      => "No overlap: CSV ({$csvStart} to {$csvEnd}) and Live ({$liveStart} to {$liveEnd}).",
            'overlap'          => false,
            'overlap_start'    => null,
            'overlap_end'      => null,
            'live_coverage'    => $live,
            'csv_coverage'     => $csv,
        ];
    }

    /**
     * Resolve data and source attribution for COMBINED_AUTO.
     */
    public function resolveSourcesForAnswer(
        bool $liveUsed,
        bool $csvUsed,
        bool $externalUsed = false,
        ?string $customExternalName = null
    ): array {
        $sources = [];

        if ($liveUsed) {
            $sources[] = [
                'type'        => ChatDataSource::END_TO_END->value,
                'label'       => ChatDataSource::END_TO_END->label(),
                'description' => ChatDataSource::END_TO_END->description(),
            ];
        }

        if ($csvUsed) {
            $sources[] = $this->csvReader->getCsvSourceMetadata();
        }

        if ($externalUsed) {
            $sources[] = [
                'type'        => ChatDataSource::KRB_KNOWLEDGE_BASE->value,
                'label'       => ChatDataSource::KRB_KNOWLEDGE_BASE->label($customExternalName),
                'description' => ChatDataSource::KRB_KNOWLEDGE_BASE->description(),
            ];
        }

        return $sources;
    }
}
