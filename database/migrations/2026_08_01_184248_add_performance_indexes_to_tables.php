<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration adds additional performance indexes for common query patterns
     * that were not covered in the initial schema migrations.
     */
    public function up(): void
    {
        // Add indexes to registrations table for improved query performance
        Schema::table('registrations', function (Blueprint $table) {
            // Index on checked_in_at for time-based check-in analytics
            // Used for "check-ins by hour" statistics
            $table->index('checked_in_at', 'idx_registrations_checked_in_at');

            // Composite index for waitlist ordering (status + created_at)
            // Used to find oldest waitlisted registrations for promotion
            // Note: ['event_id', 'status'] already exists from initial migration
            $table->index(['status', 'created_at'], 'idx_registrations_status_created');
        });

        // Add indexes to events table
        Schema::table('events', function (Blueprint $table) {
            // Composite index for organization event listings
            // Used when fetching events for a specific organization with status filter
            $table->index(['organization_id', 'status'], 'idx_events_org_status');

            // Composite index for capacity management queries
            // Used to find events approaching capacity
            $table->index(['capacity', 'total_registered'], 'idx_events_capacity_tracking');

            // Index on registration_opens_at for scheduled registration opening
            $table->index('registration_opens_at', 'idx_events_reg_opens');

            // Index on registration_closes_at for scheduled registration closing
            $table->index('registration_closes_at', 'idx_events_reg_closes');
        });

        // Add indexes to ticket_types table
        Schema::table('ticket_types', function (Blueprint $table) {
            // Composite index for availability checking
            // Used to find available ticket types for an event
            $table->index(['event_id', 'quantity', 'quantity_sold'], 'idx_ticket_types_availability');
        });

        // Add indexes to organization_members table
        Schema::table('organization_members', function (Blueprint $table) {
            // Index on joined_at for member activity tracking
            $table->index('joined_at', 'idx_org_members_joined_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropIndex('idx_registrations_checked_in_at');
            $table->dropIndex('idx_registrations_status_created');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex('idx_events_org_status');
            $table->dropIndex('idx_events_capacity_tracking');
            $table->dropIndex('idx_events_reg_opens');
            $table->dropIndex('idx_events_reg_closes');
        });

        Schema::table('ticket_types', function (Blueprint $table) {
            $table->dropIndex('idx_ticket_types_availability');
        });

        Schema::table('organization_members', function (Blueprint $table) {
            $table->dropIndex('idx_org_members_joined_at');
        });
    }
};
