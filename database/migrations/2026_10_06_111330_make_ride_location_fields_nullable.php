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
            Schema::table('rides', function (Blueprint $table) {
                $table->text('pickup_address')->nullable()->change();
                $table->decimal('pickup_lat', 10, 7)->nullable()->change();
                $table->decimal('pickup_lng', 10, 7)->nullable()->change();
                $table->text('dropoff_address')->nullable()->change();
                $table->decimal('dropoff_lat', 10, 7)->nullable()->change();
                $table->decimal('dropoff_lng', 10, 7)->nullable()->change();
            });
        }

        /**
         * Reverse the migrations.
         */
        public function down(): void
        {
            Schema::table('rides', function (Blueprint $table) {
                $table->text('pickup_address')->nullable(false)->change();
                $table->decimal('pickup_lat', 10, 7)->nullable(false)->change();
                $table->decimal('pickup_lng', 10, 7)->nullable(false)->change();
                $table->text('dropoff_address')->nullable(false)->change();
                $table->decimal('dropoff_lat', 10, 7)->nullable(false)->change();
                $table->decimal('dropoff_lng', 10, 7)->nullable(false)->change();
            });
        }
};
