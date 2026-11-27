<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_otps', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('email')->index();
            $table->string('otp_hash');
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at')->index();
            $table->timestamp('resend_available_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::table('mechanic_locations', function (Blueprint $table): void {
            $table->foreignId('service_request_id')
                ->nullable()
                ->after('mechanic_id')
                ->constrained('service_requests')
                ->nullOnDelete();
            $table->decimal('accuracy_meters', 7, 2)->nullable()->after('longitude');
            $table->index(['service_request_id', 'reported_at']);
        });
    }

    public function down(): void
    {
        Schema::table('mechanic_locations', function (Blueprint $table): void {
            $table->dropIndex(['service_request_id', 'reported_at']);
            $table->dropConstrainedForeignId('service_request_id');
            $table->dropColumn('accuracy_meters');
        });

        Schema::dropIfExists('registration_otps');
    }
};
