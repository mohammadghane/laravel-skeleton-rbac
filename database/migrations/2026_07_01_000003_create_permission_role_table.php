<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Old table: `role_perms` (role_id, privilege, resource, pattern).
    // `resource`/`pattern` supported scoping a permission to a specific record (row-level ACL).
    // We keep that capability on the pivot instead of collapsing it away.
    public function up(): void
    {
        Schema::create('permission_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->string('resource')->nullable();
            $table->string('pattern')->nullable();
            $table->timestamps();

            $table->unique(['role_id', 'permission_id', 'resource', 'pattern'], 'role_perm_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_role');
    }
};
