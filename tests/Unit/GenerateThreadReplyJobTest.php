<?php

namespace Tests\Unit;

use App\Jobs\GenerateThreadReply;
use App\Models\Thread;
use Tests\TestCase;

class GenerateThreadReplyJobTest extends TestCase
{
    /**
     * Тест что job принимает indexId параметр
     */
    public function test_job_accepts_index_id_parameter(): void
    {
        $thread = new Thread(['title' => 'Test Thread', 'status' => 'active']);
        $thread->id = 1; // Имитируем сохраненный объект

        // Создаем job с indexId
        $job = new GenerateThreadReply($thread, 'test-index-id');

        // Проверяем через рефлексию, что indexId сохранен
        $reflection = new \ReflectionClass($job);
        $indexIdProp = $reflection->getProperty('indexId');
        $indexIdProp->setAccessible(true);

        $this->assertEquals('test-index-id', $indexIdProp->getValue($job));
    }

    /**
     * Тест что job работает без indexId
     */
    public function test_job_works_without_index_id(): void
    {
        $thread = new Thread(['title' => 'Test Thread', 'status' => 'active']);
        $thread->id = 1; // Имитируем сохраненный объект

        // Создаем job без indexId
        $job = new GenerateThreadReply($thread);

        // Проверяем через рефлексию, что indexId null
        $reflection = new \ReflectionClass($job);
        $indexIdProp = $reflection->getProperty('indexId');
        $indexIdProp->setAccessible(true);

        $this->assertNull($indexIdProp->getValue($job));
    }
}
