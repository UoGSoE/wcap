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
        Schema::table('plan_entries', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->dropColumn('location');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('default_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->dropColumn('default_location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plan_entries', function (Blueprint $table) {
            $table->string('location', 120)->nullable();
            $table->dropForeign(['location_id']);
            $table->dropColumn('location_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('default_location')->default('');
            $table->dropForeign(['default_location_id']);
            $table->dropColumn('default_location_id');
        });
    }
};
