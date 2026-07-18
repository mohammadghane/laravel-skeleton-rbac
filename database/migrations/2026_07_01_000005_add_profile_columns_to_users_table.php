<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Old table: `user`. Laravel ships with a slim `users` table (name, email, password),
    // so we add back the fields the old User model actually read/wrote via exchangeArray().
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('gender')->nullable()->after('name');
            $table->string('family')->nullable()->after('name');
            $table->string('username')->nullable()->unique()->after('family');
            $table->string('cell')->nullable()->unique()->after('email');
            $table->string('tell')->nullable();
            $table->string('national_code')->nullable()->unique();
            $table->string('addr')->nullable();
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->string('zip_code')->nullable();
            $table->string('sana_code')->nullable();
            $table->string('api_token')->nullable()->unique();

            $table->foreignId('center_id')->nullable()->index();
            $table->foreignId('agency_id')->nullable()->index(); // self-referencing "managed by agency" relation
            $table->boolean('is_agency')->default(false);
            $table->boolean('is_provider')->default(false);

            $table->foreignId('setter')->nullable(); // who created this user
            $table->ipAddress('set_with')->nullable();

            $table->softDeletes(); // replaces the old boolean `delete` flag
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn([
                'gender', 'family', 'username', 'cell', 'tell', 'national_code',
                'addr', 'province', 'city', 'zip_code', 'sana_code', 'api_token',
                'center_id', 'agency_id', 'is_agency', 'is_provider', 'setter', 'set_with',
            ]);
        });
    }
};
