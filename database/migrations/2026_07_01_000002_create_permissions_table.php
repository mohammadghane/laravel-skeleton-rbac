<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Old code kept the privilege as a raw string on role_perms (e.g. 'admin/users', 'system-manager').
    // We normalize those strings into a real permissions table so they can be seeded/managed/validated,
    // while still keeping the human key so existing privilege strings in code (isAllow('admin/users')) keep working.
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();     // e.g. 'admin/users', 'system-manager'
            $table->string('module')->nullable(); // grouping, e.g. 'users', 'orders' (from old config/permisions.php)
            $table->string('title')->nullable();  // Persian display label
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
