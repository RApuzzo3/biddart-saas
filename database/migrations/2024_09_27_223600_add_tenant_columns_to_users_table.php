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
        Schema::table('users', function (Blueprint $table) {
            // Add any additional columns needed for your users table
            // Example:
            // $table->foreignId('tenant_id')->nullable()->constrained('tenants');
            // $table->boolean('is_admin')->default(false);
            // $table->string('phone')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Reverse the changes made in the up method
            // Example:
            // $table->dropForeign(['tenant_id']);
            // $table->dropColumn(['tenant_id', 'is_admin', 'phone']);
        });
    }
};
