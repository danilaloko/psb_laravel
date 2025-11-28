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
        Schema::table('generations', function (Blueprint $table) {
            $table->foreignId('thread_id')->nullable()->constrained()->onDelete('cascade');
            $table->enum('type', ['analysis', 'reply'])->default('analysis');

            // Индексы для производительности
            $table->index('thread_id');
            $table->index(['thread_id', 'type']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('generations', function (Blueprint $table) {
            $table->dropIndex(['thread_id']);
            $table->dropIndex(['thread_id', 'type']);
            $table->dropIndex(['type']);

            $table->dropForeign(['thread_id']);
            $table->dropColumn(['thread_id', 'type']);
        });
    }
};
