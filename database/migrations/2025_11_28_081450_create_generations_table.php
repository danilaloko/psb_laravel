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
        Schema::create('generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_id')->constrained()->onDelete('cascade');
            $table->text('prompt'); // Отправленный промпт
            $table->json('response')->nullable(); // Ответ от ИИ
            $table->decimal('processing_time', 8, 3)->nullable(); // Время обработки в секундах
            $table->enum('status', ['success', 'error', 'timeout', 'rate_limit'])->default('success');
            $table->text('error_message')->nullable(); // Сообщение об ошибке если status=error
            $table->json('metadata')->nullable(); // ВСЕ дополнительные данные
            $table->timestamps();

            // Индексы для производительности
            $table->index(['email_id', 'created_at']);
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
