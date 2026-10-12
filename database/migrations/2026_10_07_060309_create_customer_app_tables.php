<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Saved Places (Home, Work, Favorites)
        if (!Schema::hasTable('customer_saved_places')) {
            Schema::create('customer_saved_places', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->string('title');          // "Home", "Work", "Favourite"
                $table->string('subtitle')->nullable();
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->text('formatted_address')->nullable();
                $table->string('short_address')->nullable();
                $table->timestamps();
            });
        }

        // 2. Wallet Transactions (Passbook)
        if (!Schema::hasTable('wallet_transactions')) {
            Schema::create('wallet_transactions', function (Blueprint $table) {
                $table->id();
                $table->string('transaction_number')->unique();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('ride_id')->nullable()->constrained('rides')->nullOnDelete();
                $table->enum('type', ['credit', 'debit']);
                $table->decimal('amount', 12, 2);
                $table->string('description')->nullable();
                $table->enum('status', ['pending', 'completed', 'failed'])->default('completed');
                $table->string('reference_id')->nullable();   // gateway order id
                $table->string('payment_gateway')->nullable();// razorpay, stripe
                $table->timestamps();
            });
        }

        // 3. Customer Device Tokens (FCM / APNs)
        // customer_tokens already exists but only for JWT refresh tokens
        // Add a separate table for push-notification device tokens
        if (!Schema::hasTable('customer_device_tokens')) {
            Schema::create('customer_device_tokens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->string('fcm_token');
                $table->enum('platform', ['android', 'ios', 'web'])->default('android');
                $table->string('device_id')->nullable();
                $table->timestamps();
            });
        }

        // 4. Extend customers table with new fields
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'emergency_contact')) {
                $table->string('emergency_contact', 20)->nullable()->after('avatar');
            }
            if (!Schema::hasColumn('customers', 'notification_prefs')) {
                $table->json('notification_prefs')->nullable()->after('emergency_contact');
            }
            if (!Schema::hasColumn('customers', 'location_prefs')) {
                $table->json('location_prefs')->nullable()->after('notification_prefs');
            }
        });

        // 5. Extend rides table with new fields
        Schema::table('rides', function (Blueprint $table) {
            if (!Schema::hasColumn('rides', 'tip_amount')) {
                $table->decimal('tip_amount', 10, 2)->default(0.00)->after('discount_amount');
            }
            if (!Schema::hasColumn('rides', 'ride_category')) {
                $table->enum('ride_category', ['daily', 'rentals', 'outstation', 'parcel'])->default('daily')->after('vehicle_type');
            }
            if (!Schema::hasColumn('rides', 'scheduled_at')) {
                $table->timestamp('scheduled_at')->nullable()->after('completed_at');
            }
            if (!Schema::hasColumn('rides', 'driver_review')) {
                $table->text('driver_review')->nullable()->after('customer_review');
            }
            if (!Schema::hasColumn('rides', 'driver_rating')) {
                $table->decimal('driver_rating', 3, 2)->nullable()->after('driver_review');
            }
            if (!Schema::hasColumn('rides', 'share_token')) {
                $table->string('share_token')->nullable()->unique()->after('otp');
            }
            if (!Schema::hasColumn('rides', 'parcel_details')) {
                $table->json('parcel_details')->nullable()->after('share_token');
            }
            if (!Schema::hasColumn('rides', 'promo_code')) {
                $table->string('promo_code')->nullable()->after('discount_amount');
            }
        });

        // 6. SOS Events
        if (!Schema::hasTable('sos_events')) {
            Schema::create('sos_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('ride_id')->nullable()->constrained('rides')->nullOnDelete();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->string('emergency_contact')->nullable();
                $table->string('status')->default('triggered'); // triggered, resolved
                $table->timestamp('triggered_at');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sos_events');
        Schema::dropIfExists('customer_device_tokens');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('customer_saved_places');

        Schema::table('rides', function (Blueprint $table) {
            foreach (['tip_amount', 'ride_category', 'scheduled_at', 'driver_review', 'driver_rating', 'share_token', 'parcel_details', 'promo_code'] as $col) {
                if (Schema::hasColumn('rides', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            foreach (['emergency_contact', 'notification_prefs', 'location_prefs'] as $col) {
                if (Schema::hasColumn('customers', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
