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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->index();
            $table->text('description')->nullable();
            $table->text('cover_image_url')->nullable();

            // Visibility
            $table->enum('visibility', ['public', 'private'])->default('public')->index();

            // Location
            $table->string('location_type')->default('physical');
            $table->text('location_address')->nullable();
            $table->string('location_city')->nullable()->index();
            $table->string('location_state')->nullable();
            $table->string('location_country')->nullable()->index();
            $table->string('location_zip')->nullable();
            $table->decimal('location_latitude', 10, 7)->nullable();
            $table->decimal('location_longitude', 10, 7)->nullable();
            $table->text('online_meeting_url')->nullable();

            // Date & Time
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->index();
            $table->string('timezone')->default('UTC');

            // Capacity
            $table->integer('capacity')->nullable();
            $table->integer('total_registered')->default(0);
            $table->boolean('enable_waitlist')->default(true);

            // Registration settings
            $table->boolean('auto_approve_registrations')->default(true);
            $table->boolean('require_approval')->default(false);
            $table->timestamp('registration_opens_at')->nullable();
            $table->timestamp('registration_closes_at')->nullable();

            // Category & Tags
            $table->string('category')->nullable()->index();
            $table->json('tags')->nullable();

            // Status
            $table->enum('status', ['draft', 'published', 'cancelled', 'completed'])->default('draft')->index();

            $table->timestamps();
            $table->softDeletes();

            // Composite indexes
            $table->unique(['organization_id', 'slug']);
            $table->index(['status', 'visibility', 'starts_at']);
            $table->index(['location_city', 'starts_at']);
            $table->index(['category', 'starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
