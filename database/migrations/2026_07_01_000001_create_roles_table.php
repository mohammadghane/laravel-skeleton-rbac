<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Old table: `role` (id, name, desk, center_id, hidden, is_default)
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('desk')->nullable(); // description, kept old column name for continuity
            $table->foreignId('center_id')->nullable()->index();
            $table->boolean('hidden')->default(false);   // auto-generated center-owner roles etc.
            $table->boolean('is_default')->default(false); // "system-manager"/base role, non-deletable
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
