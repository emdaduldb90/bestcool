<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createTableIfMissing('tracking_destinations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 100);
            $table->boolean('enabled')->default(false);
            $table->boolean('browser_enabled')->default(false);
            $table->boolean('server_enabled')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        $this->createTableIfMissing('tracking_destination_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destination_id')->unique()->constrained('tracking_destinations')->cascadeOnDelete();
            $table->longText('secrets');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('updated_at')->nullable();
        });

        $this->createTableIfMissing('tracking_event_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->unique();
            $table->string('label', 100);
            $table->boolean('enabled')->default(true);
            $table->string('consent_category', 24)->default('analytics');
            $table->json('allowed_properties')->nullable();
            $table->timestamps();
        });

        $this->createTableIfMissing('tracking_event_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_definition_id')->constrained('tracking_event_definitions')->cascadeOnDelete();
            $table->foreignId('destination_id')->constrained('tracking_destinations')->cascadeOnDelete();
            $table->string('channel', 16);
            $table->string('consent_category', 24)->default('analytics');
            $table->boolean('enabled')->default(false);
            $table->unsignedSmallInteger('priority')->default(100);
            $table->json('property_mapping')->nullable();
            $table->timestamps();
            $table->unique(['event_definition_id', 'destination_id', 'channel'], 'tracking_event_route_unique');
            $table->index(['destination_id', 'channel', 'enabled']);
        });

        $this->createTableIfMissing('tracking_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('event_name', 64);
            $table->string('source', 16);
            $table->timestamp('occurred_at')->useCurrent();
            $table->string('session_hash', 64)->nullable();
            $table->string('page_path', 512)->nullable();
            $table->string('referrer_host', 190)->nullable();
            $table->string('product_id', 64)->nullable();
            $table->string('product_sku', 100)->nullable();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->decimal('value', 12, 2)->nullable();
            $table->char('currency', 3)->nullable();
            $table->longText('private_context')->nullable();
            $table->string('link_type', 16)->nullable();
            $table->string('link_url', 512)->nullable();
            $table->string('link_host', 190)->nullable();
            $table->string('link_label', 190)->nullable();
            $table->boolean('analytics_consent')->default(false);
            $table->boolean('advertising_consent')->default(false);
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->json('properties')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['event_name', 'occurred_at'], 'tracking_events_name_time_index');
            $table->index(['source', 'occurred_at'], 'tracking_events_source_time_index');
            $table->index(['session_hash', 'occurred_at'], 'tracking_events_session_time_index');
            $table->index(['order_id', 'event_name'], 'tracking_events_order_name_index');
            $table->unique(['order_id', 'event_name'], 'tracking_event_order_name_unique');
            $table->index(['link_type', 'occurred_at'], 'tracking_events_link_time_index');
        });

        $this->createTableIfMissing('tracking_event_observations', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id');
            $table->string('source', 16);
            $table->timestamp('observed_at')->useCurrent();
            $table->json('properties')->nullable();
            $table->unique(['event_id', 'source'], 'tracking_observation_event_source_unique');
            $table->index('observed_at');
        });

        $this->createTableIfMissing('tracking_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracking_event_id')->constrained('tracking_events')->cascadeOnDelete();
            $table->foreignId('destination_id')->constrained('tracking_destinations')->cascadeOnDelete();
            $table->string('channel', 16);
            $table->string('status', 16)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('response_code', 80)->nullable();
            $table->string('error_summary', 500)->nullable();
            $table->string('provider_event_id', 190)->nullable();
            $table->timestamps();
            $table->unique(['tracking_event_id', 'destination_id', 'channel'], 'tracking_delivery_event_destination_channel_unique');
            $table->index(['status', 'next_attempt_at'], 'tracking_delivery_status_due_index');
            $table->index(['destination_id', 'status', 'created_at'], 'tracking_delivery_destination_status_index');
        });

        $this->createTableIfMissing('tracking_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_hash', 64)->unique();
            $table->timestamp('first_seen_at')->useCurrent();
            $table->timestamp('last_seen_at')->useCurrent();
            $table->boolean('analytics_consent')->default(false);
            $table->boolean('advertising_consent')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['last_seen_at', 'analytics_consent'], 'tracking_session_seen_consent_index');
        });

        $this->createTableIfMissing('tracking_consents', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_hash', 64)->unique();
            $table->boolean('analytics')->default(false);
            $table->boolean('advertising')->default(false);
            $table->unsignedSmallInteger('policy_version')->default(1);
            $table->timestamp('chosen_at')->useCurrent();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });

        $this->createTableIfMissing('tracking_daily_aggregates', function (Blueprint $table) {
            $table->id();
            $table->date('aggregate_date');
            $table->string('event_name', 64);
            $table->string('source', 16);
            $table->unsignedBigInteger('event_count')->default(0);
            $table->unsignedBigInteger('unique_sessions')->default(0);
            $table->decimal('order_value', 14, 2)->default(0);
            $table->timestamps();
            $table->unique(['aggregate_date', 'event_name', 'source'], 'tracking_daily_aggregate_unique');
            $table->index(['event_name', 'aggregate_date'], 'tracking_daily_event_date_index');
        });

        $this->createTableIfMissing('tracking_link_aggregates', function (Blueprint $table) {
            $table->id();
            $table->date('aggregate_date');
            $table->char('link_hash', 64);
            $table->string('link_type', 16);
            $table->string('link_url', 512);
            $table->string('page_path', 512)->nullable();
            $table->string('link_label', 190)->nullable();
            $table->unsignedBigInteger('click_count')->default(0);
            $table->unsignedBigInteger('unique_sessions')->default(0);
            $table->timestamps();
            $table->unique(['aggregate_date', 'link_hash'], 'tracking_link_aggregate_unique');
            $table->index(['aggregate_date', 'link_type'], 'tracking_link_aggregate_type_index');
        });

        $this->createTableIfMissing('tracking_ai_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('period_from');
            $table->date('period_to');
            $table->string('language', 8)->default('bn');
            $table->string('model', 100);
            $table->string('status', 16)->default('completed');
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->char('input_fingerprint', 64);
            $table->json('report');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['period_from', 'period_to', 'created_at'], 'tracking_ai_report_period_index');
        });

        $this->createTableIfMissing('tracking_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->string('target', 100)->nullable();
            $table->json('properties')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['action', 'created_at'], 'tracking_audit_action_time_index');
            $table->index(['actor_id', 'created_at'], 'tracking_audit_actor_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_audit_logs');
        Schema::dropIfExists('tracking_ai_reports');
        Schema::dropIfExists('tracking_link_aggregates');
        Schema::dropIfExists('tracking_daily_aggregates');
        Schema::dropIfExists('tracking_consents');
        Schema::dropIfExists('tracking_sessions');
        Schema::dropIfExists('tracking_deliveries');
        Schema::dropIfExists('tracking_event_observations');
        Schema::dropIfExists('tracking_events');
        Schema::dropIfExists('tracking_event_routes');
        Schema::dropIfExists('tracking_event_definitions');
        Schema::dropIfExists('tracking_destination_credentials');
        Schema::dropIfExists('tracking_destinations');
    }

    private function createTableIfMissing(string $name, \Closure $callback): void
    {
        if (! Schema::hasTable($name)) {
            Schema::create($name, $callback);
        }
    }
};
