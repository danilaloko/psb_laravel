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
        Schema::table('tasks', function (Blueprint $table) {
            // Переименовываем поля
            $table->renameColumn('subject', 'title');
            $table->renameColumn('user_id', 'executor_id');

            // Добавляем новые поля
            $table->foreignId('thread_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('creator_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->timestamp('due_date')->nullable();

            // Обновляем enum значения
            $table->enum('status', ['new', 'in_progress', 'completed', 'archived', 'cancelled'])->default('new')->change();
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium')->change();

            // Удаляем поля, которые теперь в таблице emails
            $table->dropColumn(['from_email', 'from_name', 'received_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Возвращаем старые поля
            $table->string('from_email');
            $table->string('from_name')->nullable();
            $table->timestamp('received_at');

            // Удаляем новые поля
            $table->dropForeign(['thread_id']);
            $table->dropForeign(['creator_id']);
            $table->dropColumn(['thread_id', 'creator_id', 'due_date']);

            // Возвращаем старые enum значения
            $table->enum('status', ['new', 'in_progress', 'completed', 'archived'])->default('new')->change();
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium')->change();

            // Переименовываем поля обратно
            $table->renameColumn('title', 'subject');
            $table->renameColumn('executor_id', 'user_id');
        });
    }
};
