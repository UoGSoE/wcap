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
        Schema::create('plan_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('entry_date');
            $table->text('note')->nullable();
            $table->string('category', 32)->nullable(); // see app/Enums/Category
            $table->string('location', 120)->nullable(); // see app/Enums/Location
            $table->boolean('is_available')->default(true);
            $table->boolean('is_holiday')->default(false);
            $table->boolean('created_by_manager')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_entries');
    }
};
