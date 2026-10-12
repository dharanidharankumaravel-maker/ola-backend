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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->string('reg_number')->unique(); // e.g. MH12CD1000
            $table->enum('vehicle_type', ['bike', 'auto', 'mini', 'sedan', 'suv'])->default('sedan');
            $table->string('make'); // e.g. Maruti Suzuki, Hyundai
            $table->string('model'); // e.g. Swift Dzire, WagonR
            $table->string('year')->nullable();
            $table->string('color')->nullable();
            $table->string('owner_name');
            $table->enum('status', ['active', 'maintenance', 'inactive'])->default('active');
            $table->timestamps();
        });
        Schema::table('drivers', function (Blueprint $table) {
            $table->foreignId('vehicle_id')->nullable()->after('rc_number')->constrained('vehicles')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropForeign(['vehicle_id']);
            $table->dropColumn('vehicle_id');
        });
        Schema::dropIfExists('vehicles');
    }
};
