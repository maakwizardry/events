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
        Schema::create('ticket_types', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('quantity')->nullable();
            $table->integer('quantity_sold')->default(0);
            $table->integer('order')->default(0);

            // Pricing
            $table->decimal('price', 10, 2)->default(0.00);
            $table->string('currency', 3)->default('USD');

            // Availability
            $table->timestamp('sales_start_at')->nullable();
            $table->timestamp('sales_end_at')->nullable();
            $table->boolean('is_visible')->default(true);

            // Limits per registration
            $table->integer('min_per_order')->default(1);
            $table->integer('max_per_order')->default(10);

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['event_id', 'is_visible', 'order']);
            $table->index('sales_start_at');
            $table->index('sales_end_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_types');
    }
};
