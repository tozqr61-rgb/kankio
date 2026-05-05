<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('deactivated_at')->nullable()->after('last_avatar_update');
            $table->foreignId('deactivated_by')->nullable()->after('deactivated_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->softDeletes();
            $table->foreignId('deleted_by')->nullable()->after('deleted_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('is_archived');
            $table->foreignId('archived_by')->nullable()->after('archived_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('archived_by');
            $table->dropColumn('archived_at');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropSoftDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deactivated_by');
            $table->dropColumn('deactivated_at');
        });
    }
};
