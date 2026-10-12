<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add otp, otp_expires_at and fcm_key to users table
        Schema::table('users', function (Blueprint $table) {
            $table->string('otp', 6)->nullable()->after('status');
            $table->timestamp('otp_expires_at')->nullable()->after('otp');
            $table->string('fcm_key')->nullable()->after('otp_expires_at');
        });

        // Remove otp, otp_expires_at and fcm_key from customers table
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['otp', 'otp_expires_at', 'fcm_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['otp', 'otp_expires_at', 'fcm_key']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('otp', 6)->nullable()->after('status');
            $table->timestamp('otp_expires_at')->nullable()->after('otp');
            $table->string('fcm_key')->nullable()->after('otp_expires_at');
        });
    }
};
