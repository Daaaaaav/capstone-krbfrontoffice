<?php

namespace App\Services\AI\Enums;

enum ChatDataSource: string
{
    case COMBINED_AUTO              = 'combined_auto';
    case END_TO_END                = 'end_to_end';
    case SERVER_CSV                = 'server_csv';
    case KRB_KNOWLEDGE_BASE        = 'krb_knowledge_base';
    case APPROVED_EXTERNAL_DATASET = 'approved_external_dataset';
    case COMBINED                  = 'combined';

    public function label(?string $customName = null): string
    {
        return match ($this) {
            self::END_TO_END                => 'Live KRB System Data',
            self::SERVER_CSV                => 'Server Historical CSV (krb_historical_data.csv)',
            self::KRB_KNOWLEDGE_BASE        => 'Approved Kebun Raya Bogor Knowledge Base',
            self::APPROVED_EXTERNAL_DATASET => $customName ? "Approved KRB Data Source: {$customName}" : 'Approved Kebun Raya Bogor knowledge dataset',
            self::COMBINED_AUTO,
            self::COMBINED                  => 'Live KRB System Data + Server Historical CSV',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::END_TO_END                => 'Retrieved from current authoritative application database records',
            self::SERVER_CSV                => 'Retrieved from server-side historical time-series dataset (krb_historical_data.csv)',
            self::KRB_KNOWLEDGE_BASE        => 'Retrieved from verified Kebun Raya Bogor knowledge documents and compendium',
            self::APPROVED_EXTERNAL_DATASET => 'Retrieved from explicitly approved external Kebun Raya Bogor dataset',
            self::COMBINED_AUTO,
            self::COMBINED                  => 'Combined metrics from live application records and historical CSV data with safe overlap resolution',
        };
    }

    public static function formatSourcesTag(array $sources): string
    {
        if (empty($sources)) {
            return '';
        }

        $labels = [];
        foreach ($sources as $source) {
            if (is_string($source)) {
                $enum = self::tryFrom($source);
                if ($enum) {
                    $labels[] = $enum->label();
                } else {
                    $labels[] = $source;
                }
            } elseif (is_array($source)) {
                $type = $source['type'] ?? '';
                $enum = self::tryFrom($type);
                if ($enum) {
                    $custom = $source['source_name'] ?? $source['display_name'] ?? $source['file'] ?? null;
                    $labels[] = $source['label'] ?? $enum->label($custom);
                } elseif (! empty($source['label'])) {
                    $labels[] = $source['label'];
                }
            }
        }

        $uniqueLabels = array_unique(array_filter($labels));
        if (empty($uniqueLabels)) {
            return '';
        }

        if (count($uniqueLabels) === 1) {
            return "**Data source:** " . reset($uniqueLabels);
        }

        return "**Data sources:** " . implode(' + ', $uniqueLabels);
    }
}

