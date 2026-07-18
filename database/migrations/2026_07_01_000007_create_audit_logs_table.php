<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Who did it. Nullable + nullOnDelete so a log entry outlives the user that made
            // it (e.g. an admin account later gets deleted) - the log is the whole point.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // What it was done to. Polymorphic so any current or future Auditable model works
            // without a new migration - see app/Concerns/Auditable.php.
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id')->nullable();

            $table->string('action'); // created | updated | deleted
            $table->string('label')->nullable(); // human-readable identifier, e.g. "کاربر: علی رضایی"

            // { "email": { "old": "a@b.c", "new": "aaa@bbb.ccc" }, ... } - null for actions
            // with nothing meaningful to diff (e.g. a delete, or a create where every field
            // was excluded).
            $table->json('changes')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamps();

            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
