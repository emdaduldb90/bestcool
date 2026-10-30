<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->index('stock');
        });

        Schema::table('product_images', function (Blueprint $table) {
            $table->boolean('is_primary')->default(false)->index();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->index();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index('created_at');
            $table->index(['status', 'created_at']);
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->primary(['user_id', 'role_id']);
            $table->index('role_id');
        });

        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('product_id')->nullable()->index();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('previous_stock');
            $table->integer('change_quantity');
            $table->unsignedInteger('resulting_stock');
            $table->string('reason', 40);
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['order_id', 'created_at']);
        });

        Schema::create('admin_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80)->index();
            $table->string('subject_type', 100)->nullable();
            $table->string('subject_id', 190)->nullable()->index();
            $table->string('description', 500);
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('email', 190)->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('subject', 190);
            $table->text('message');
            $table->string('status', 20)->default('NEW')->index();
            $table->text('admin_note')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiries');
        Schema::dropIfExists('admin_activities');
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_active'));
        Schema::table('product_images', fn (Blueprint $table) => $table->dropColumn('is_primary'));
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['stock']);
            $table->dropColumn(['meta_title', 'meta_description']);
        });
        Schema::table('categories', fn (Blueprint $table) => $table->dropColumn(['is_active', 'sort_order', 'meta_title', 'meta_description']));
    }
};
