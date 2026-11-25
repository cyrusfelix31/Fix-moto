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
        Schema::create('dispatch_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mechanic_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('offered');
            $table->timestamp('expires_at');
            $table->timestamp('responded_at')->nullable();
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->unsignedTinyInteger('attempt_number')->default(1);
            $table->unique(['service_request_id', 'mechanic_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dispatch_attempts');
    }
};
