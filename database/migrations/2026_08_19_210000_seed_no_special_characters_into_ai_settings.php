<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the 'no_special_characters' toggle into the ai_settings table.
 *
 * This is intentionally a data-migration rather than a schema change:
 * ai_settings is a generic key/value config store, so new settings are
 * added as rows.  Default value = '1' (ON) so existing installations
 * continue to enforce the validation unchanged.
 */
return new class extends Migration
{
    private const KEY = 'no_special_characters';

    public function up(): void
    {
        // Use updateOrInsert so re-running the migration (or re-seeding) is safe.
        DB::table('ai_settings')->updateOrInsert(
            ['key' => self::KEY],
            [
                'key'         => self::KEY,
                'value'       => '1',
                'type'        => 'bool',
                'group'       => 'validation',
                'label'       => 'No Special Characters Validation',
                'description' => 'When ON (default), text fields that use the NoSpecialCharacters rule reject special characters such as < > { } [ ] | ; : etc. Turn OFF to allow those characters application-wide across all dashboards.',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('ai_settings')->where('key', self::KEY)->delete();
    }
};
