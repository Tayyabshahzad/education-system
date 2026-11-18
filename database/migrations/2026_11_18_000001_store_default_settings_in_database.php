<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Commission amounts (now 7 levels) and the other business settings live in the
     * `settings` table, editable from Admin > Settings. This copies any default that is
     * not stored yet into the table; amounts an admin already saved stay as they are.
     */
    public function up(): void
    {
        Setting::insertMissingDefaults();
    }

    public function down(): void
    {
        // Settings rows are kept: they may hold values an admin changed.
    }
};
