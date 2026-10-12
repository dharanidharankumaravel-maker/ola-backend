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
        Schema::create('rides', function (Blueprint $table) {
            $table->id();
            $table->string('ride_number')->unique(); // e.g. TR-842
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->enum('vehicle_type', ['bike', 'auto', 'mini', 'sedan', 'suv'])->default('sedan');
            
            // Locations
            $table->text('pickup_address')->nullable();
            $table->decimal('pickup_lat', 10, 7)->nullable();
            $table->decimal('pickup_lng', 10, 7)->nullable();
            $table->text('dropoff_address')->nullable();
            $table->decimal('dropoff_lat', 10, 7)->nullable();
            $table->decimal('dropoff_lng', 10, 7)->nullable();
            
            // Trip metrics
            $table->decimal('distance_km', 8, 2)->default(0.00);
            $table->unsignedInteger('duration_mins')->default(0);
            
            // Pricing Breakdown
            $table->decimal('base_fare', 10, 2)->default(0.00);
            $table->decimal('distance_fare', 10, 2)->default(0.00);
            $table->decimal('time_fare', 10, 2)->default(0.00);
            $table->decimal('tax_amount', 10, 2)->default(0.00);
            $table->decimal('surge_amount', 10, 2)->default(0.00);
            $table->decimal('discount_amount', 10, 2)->default(0.00);
            $table->decimal('total_fare', 10, 2)->default(0.00);
            $table->decimal('driver_earning', 10, 2)->default(0.00);
            $table->decimal('platform_fee', 10, 2)->default(0.00);
            
            // Payment & Status
            $table->enum('payment_method', ['cash', 'wallet', 'upi', 'card'])->default('cash');
            $table->enum('payment_status', ['pending', 'paid', 'failed'])->default('pending');
            $table->enum('status', [
                'requested', 
                'driver_assigned', 
                'driver_arrived', 
                'in_progress', 
                'completed', 
                'cancelled'
            ])->default('requested');
            
            $table->string('cancel_reason')->nullable();
            $table->string('cancelled_by')->nullable(); // 'customer', 'driver', 'admin'
            $table->string('otp', 6)->nullable(); // OTP verification for trip start
            
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rides');
    }
};
