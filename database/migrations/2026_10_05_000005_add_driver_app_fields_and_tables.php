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
        // 1. Update drivers table
        Schema::table('drivers', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
            $table->string('email')->nullable()->change();
            if (!Schema::hasColumn('drivers', 'first_name')) {
                $table->string('first_name')->nullable()->after('name');
            }
            if (!Schema::hasColumn('drivers', 'last_name')) {
                $table->string('last_name')->nullable()->after('first_name');
            }
            if (!Schema::hasColumn('drivers', 'address')) {
                $table->text('address')->nullable()->after('email');
            }
            if (!Schema::hasColumn('drivers', 'account_holder_name')) {
                $table->string('account_holder_name')->nullable()->after('bank_name');
            }
            if (!Schema::hasColumn('drivers', 'dl_expiry_date')) {
                $table->dateTime('dl_expiry_date')->nullable()->after('license_doc_url');
            }
            if (!Schema::hasColumn('drivers', 'insurance_expiry_date')) {
                $table->dateTime('insurance_expiry_date')->nullable()->after('insurance_doc_url');
            }
            if (!Schema::hasColumn('drivers', 'bearing')) {
                $table->decimal('bearing', 6, 2)->nullable()->after('current_lng');
            }
            if (!Schema::hasColumn('drivers', 'speed')) {
                $table->decimal('speed', 6, 2)->nullable()->after('bearing');
            }
            if (!Schema::hasColumn('drivers', 'active_session_started_at')) {
                $table->timestamp('active_session_started_at')->nullable()->after('online_status');
            }
        });

        // 2. Create driver_tokens table
        if (!Schema::hasTable('driver_tokens')) {
            Schema::create('driver_tokens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
                $table->string('token_id')->unique();
                $table->string('token_type')->default('refresh');
                $table->string('token_hash');
                $table->boolean('is_revoked')->default(false);
                $table->timestamp('expires_at');
                $table->timestamps();
            });
        }

        // 3. Create driver_documents table
        if (!Schema::hasTable('driver_documents')) {
            Schema::create('driver_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
                $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
                $table->string('document_type'); // DL, AADHAAR, PASSPORT, BIRTH_CERTIFICATE, RC_BOOK, INSURANCE, OWNER_CERT, PUC
                $table->string('document_number')->nullable();
                $table->date('expiry_date')->nullable();
                $table->string('front_image_url')->nullable();
                $table->string('back_image_url')->nullable();
                $table->string('verification_status')->default('PENDING_VERIFICATION');
                $table->string('status')->default('UPLOADED');
                $table->timestamp('uploaded_at')->nullable();
                $table->timestamps();
            });
        }

        // 4. Create driver_settings table
        if (!Schema::hasTable('driver_settings')) {
            Schema::create('driver_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('driver_id')->unique()->constrained('drivers')->cascadeOnDelete();
                $table->boolean('notifications_enabled')->default(true);
                $table->string('language')->default('English');
                $table->string('theme_mode')->default('system');
                $table->boolean('auto_accept_rides')->default(false);
                $table->string('navigation_app')->default('google_maps');
                $table->timestamps();
            });
        }

        // 5. Update vehicles table
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('owner_name')->nullable()->change();
            if (!Schema::hasColumn('vehicles', 'service_type')) {
                $table->string('service_type')->nullable()->after('reg_number');
            }
        });

        // 6. Update rides table
        Schema::table('rides', function (Blueprint $table) {
            if (!Schema::hasColumn('rides', 'polyline')) {
                $table->text('polyline')->nullable()->after('status');
            }
            if (!Schema::hasColumn('rides', 'accepted_at')) {
                $table->timestamp('accepted_at')->nullable()->after('polyline');
            }
            if (!Schema::hasColumn('rides', 'arrived_at')) {
                $table->timestamp('arrived_at')->nullable()->after('accepted_at');
            }
            if (!Schema::hasColumn('rides', 'reached_destination_at')) {
                $table->timestamp('reached_destination_at')->nullable()->after('arrived_at');
            }
            if (!Schema::hasColumn('rides', 'toll_charges')) {
                $table->decimal('toll_charges', 10, 2)->default(0.00)->after('platform_fee');
            }
            if (!Schema::hasColumn('rides', 'parking_charges')) {
                $table->decimal('parking_charges', 10, 2)->default(0.00)->after('toll_charges');
            }
            if (!Schema::hasColumn('rides', 'cancellation_fee')) {
                $table->decimal('cancellation_fee', 10, 2)->default(0.00)->after('parking_charges');
            }
            if (!Schema::hasColumn('rides', 'cancellation_fee_eligible')) {
                $table->boolean('cancellation_fee_eligible')->default(false)->after('cancellation_fee');
            }
            if (!Schema::hasColumn('rides', 'customer_review')) {
                $table->text('customer_review')->nullable()->after('cancellation_fee_eligible');
            }
            if (!Schema::hasColumn('rides', 'customer_rating')) {
                $table->decimal('customer_rating', 3, 2)->nullable()->after('customer_review');
            }
        });

        // 7. Update notifications table
        Schema::table('notifications', function (Blueprint $table) {
            if (!Schema::hasColumn('notifications', 'type')) {
                $table->string('type')->default('general')->after('title');
            }
            if (!Schema::hasColumn('notifications', 'description')) {
                $table->text('description')->nullable()->after('message');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_settings');
        Schema::dropIfExists('driver_documents');
        Schema::dropIfExists('driver_tokens');

        Schema::table('notifications', function (Blueprint $table) {
            if (Schema::hasColumn('notifications', 'description')) {
                $table->dropColumn('description');
            }
            if (Schema::hasColumn('notifications', 'type')) {
                $table->dropColumn('type');
            }
        });

        Schema::table('rides', function (Blueprint $table) {
            $cols = [
                'customer_rating', 'customer_review', 'cancellation_fee_eligible',
                'cancellation_fee', 'parking_charges', 'toll_charges',
                'reached_destination_at', 'arrived_at', 'accepted_at', 'polyline'
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('rides', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('vehicles', function (Blueprint $table) {
            if (Schema::hasColumn('vehicles', 'service_type')) {
                $table->dropColumn('service_type');
            }
        });

        Schema::table('drivers', function (Blueprint $table) {
            $cols = [
                'active_session_started_at', 'speed', 'bearing', 'insurance_expiry_date',
                'dl_expiry_date', 'account_holder_name', 'address', 'last_name', 'first_name'
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('drivers', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
