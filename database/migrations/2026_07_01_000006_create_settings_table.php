<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Old table: `setting` (id, key, value, user_id, center_id, insert_at).
    // `key` was always stored as "category:item", e.g. "general:site_title".
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key');       // "category:item" - matches config/settings.php structure
            $table->text('value')->nullable();

            // Old `has_pattern` flag on a setting item decided whether it was scoped per
            // center/user or a single global row. Both stay nullable so global settings
            // (center_id/user_id = null) and scoped ones can share the same table.
            $table->foreignId('center_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            // MySQL treats NULLs as distinct, so this only truly enforces uniqueness for
            // center/user-scoped settings - matches the old app-level fetch-then-upsert
            // behaviour closely enough. Global settings (both null) fall back to
            // SettingsManager::save() doing a fetch-first check before inserting.
            $table->unique(['key', 'center_id', 'user_id'], 'settings_key_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
