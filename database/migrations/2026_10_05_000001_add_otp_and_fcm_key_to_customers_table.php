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
        Schema::table('customers', function (Blueprint $table) {
            // Stores the latest OTP sent to this customer
            $table->string('otp', 6)->nullable()->after('status');

            // OTP expiry timestamp
            $table->timestamp('otp_expires_at')->nullable()->after('otp');

            // Firebase Cloud Messaging device token for push notifications
            $table->string('fcm_key')->nullable()->after('otp_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['otp', 'otp_expires_at', 'fcm_key']);
        });
    }
};
