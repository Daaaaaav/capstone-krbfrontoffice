<?php

namespace App\Logging;

use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;

/**
 * Custom JsonFormatter for the 'security' logging channel.
 *
 * Monolog 3.x names every logger after APP_ENV ('local', 'production', etc.)
 * by default.  This means the JSON "channel" field would be "local" even when
 * the entry is written via Log::channel('security'), which breaks the Wazuh
 * decoder prematch  (\"channel\":\"security\").
 *
 * This formatter extends the standard JsonFormatter and forces the "channel"
 * field to the fixed string "security" so every entry written through the
 * security logging channel is unambiguously identified by the Wazuh decoder
 * in wazuh/decoders/krb_laravel_security_decoders.xml.
 *
 * Usage in config/logging.php (already configured — do not change):
 *   'security' => [
 *       'driver'    => 'daily',
 *       ...
 *       'formatter' => \App\Logging\SecurityJsonFormatter::class,
 *   ],
 */
class SecurityJsonFormatter extends JsonFormatter
{
    /**
     * Force the channel name to 'security' regardless of the Monolog
     * logger name (which defaults to APP_ENV in Laravel).
     *
     * @param  LogRecord  $record
     * @return string
     */
    public function format(LogRecord $record): string
    {
        // Clone the record with the channel forced to 'security'.
        // LogRecord is a readonly-friendly value object in Monolog 3;
        // with() returns a new instance with the changed field.
        $fixed = $record->with(channel: 'security');

        return parent::format($fixed);
    }
}
