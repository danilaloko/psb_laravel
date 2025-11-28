@extends('layouts.app')

@section('title', $email->subject)

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Back Button -->
    <a href="{{ url()->previous() }}" class="inline-flex items-center text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
        Назад
    </a>
    
    <!-- Email Card -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-4 break-words" style="word-break: break-word; overflow-wrap: break-word;">{{ $email->subject }}</h1>
            
            <div class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <span><strong>От:</strong> {{ $email->from_name ?? 'Не указано' }} ({{ $email->from_address }})</span>
                </div>
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span><strong>Дата:</strong> {{ $email->received_at->format('d.m.Y в H:i') }}</span>
                </div>
                @if($email->thread)
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4V2a1 1 0 011-1h8a1 1 0 011 1v2M7 4H5a2 2 0 00-2 2v12a2 2 0 002 2h14a2 2 0 002-2V6a2 2 0 00-2-2h-2M7 4h10M9 10h6M9 14h6" />
                    </svg>
                    <span><strong>Поток:</strong> {{ $email->thread->title }}</span>
                </div>
                @endif
            </div>
        </div>
        
        <div class="px-6 py-6" style="word-break: break-word; overflow-wrap: break-word;">
            <!-- Content -->
            <div class="prose dark:prose-invert max-w-none">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Содержание:</h3>
                <div class="text-gray-700 dark:text-gray-300 break-words" style="word-break: break-word; overflow-wrap: break-word; white-space: pre-wrap; word-wrap: break-word;">{{ $email->content }}</div>
            </div>

            <!-- AI Analysis Section -->
            <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Анализ письма</h3>
                    <button id="analyze-btn" type="button" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        <span id="analyze-btn-text">Запустить анализ</span>
                    </button>
                </div>

                <!-- Analysis Content -->
                <div id="analysis-content" class="hidden space-y-4">
                    <div id="analysis-status" class="text-sm text-gray-600 dark:text-gray-400"></div>
                    <div id="analysis-results" class="hidden space-y-3">
                        <!-- Analysis results will be inserted here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const emailId = {{ $email->id }};
    const analyzeBtn = document.getElementById('analyze-btn');
    const analyzeBtnText = document.getElementById('analyze-btn-text');
    const analysisContent = document.getElementById('analysis-content');
    const analysisStatus = document.getElementById('analysis-status');
    const analysisResults = document.getElementById('analysis-results');

    // CSRF token for AJAX requests
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    let pollingInterval = null;

    // Analyze button click handler
    analyzeBtn.addEventListener('click', async function() {
        try {
            setAnalyzingState(true);

            const response = await fetch(`{{ route("dashboard.email.analyze", $email) }}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Ошибка при запуске анализа');
            }

            showAnalysisContent();
            updateAnalysisUI('processing');

            // Start polling for status updates
            startPolling();

        } catch (error) {
            console.error('Analysis start error:', error);
            alert('Ошибка: ' + error.message);
            setAnalyzingState(false);
        }
    });

    // Load initial analysis status
    loadAnalysisStatus();

    function setAnalyzingState(isAnalyzing) {
        analyzeBtn.disabled = isAnalyzing;
        analyzeBtnText.textContent = isAnalyzing ? 'Запуск...' : 'Запустить анализ';
    }

    function showAnalysisContent() {
        analysisContent.classList.remove('hidden');
    }

    function updateAnalysisUI(status, analysisData = null) {
        console.log('updateAnalysisUI called with status:', status, 'data:', analysisData);
        
        if (!status) {
            console.warn('Status is undefined or null');
            return;
        }
        
        setAnalyzingState(status === 'processing');

        let statusText = '';
        let statusClass = '';

        switch (status) {
            case 'processing':
                statusText = '🔄 Анализ выполняется...';
                statusClass = 'text-blue-600 dark:text-blue-400';
                // Если есть данные от предыдущего анализа, показываем их
                if (analysisData && analysisData.summary) {
                    showAnalysisResults(analysisData);
                }
                break;
            case 'completed':
                statusText = `✅ Анализ завершен (${analysisData?.processing_time}s, ${analysisData?.cost}₽)`;
                statusClass = 'text-green-600 dark:text-green-400';
                if (analysisData) {
                    showAnalysisResults(analysisData);
                }
                stopPolling();
                break;
            case 'error':
                statusText = '❌ Ошибка анализа';
                statusClass = 'text-red-600 dark:text-red-400';
                // Если есть данные от предыдущего анализа, показываем их
                if (analysisData && analysisData.summary) {
                    showAnalysisResults(analysisData);
                }
                setAnalyzingState(false);
                stopPolling();
                break;
            case 'not_started':
                statusText = '📝 Анализ не запускался';
                statusClass = 'text-gray-600 dark:text-gray-400';
                break;
            default:
                statusText = 'Неизвестный статус';
                statusClass = 'text-gray-600 dark:text-gray-400';
                // Если есть данные, показываем их даже для неизвестного статуса
                if (analysisData && analysisData.summary) {
                    showAnalysisResults(analysisData);
                }
        }

        analysisStatus.innerHTML = `<span class="${statusClass}">${statusText}</span>`;
    }

    function showAnalysisResults(data) {
        analysisResults.classList.remove('hidden');
        analysisResults.innerHTML = `
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 space-y-3">
                <div>
                    <strong class="text-gray-900 dark:text-white">Содержание:</strong>
                    <p class="text-gray-700 dark:text-gray-300 mt-1">${data.summary || 'Не указано'}</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <strong class="text-gray-900 dark:text-white">Приоритет:</strong>
                        <span class="ml-2 px-2 py-1 text-xs rounded-full ${
                            data.priority === 'high' ? 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400' :
                            data.priority === 'medium' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400' :
                            'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400'
                        }">
                            ${data.priority === 'high' ? 'Высокий' : data.priority === 'medium' ? 'Средний' : 'Низкий'}
                        </span>
                    </div>

                    <div>
                        <strong class="text-gray-900 dark:text-white">Категория:</strong>
                        <span class="ml-2 text-gray-700 dark:text-gray-300">${data.category || 'Не указана'}</span>
                    </div>

                    <div>
                        <strong class="text-gray-900 dark:text-white">Настроение:</strong>
                        <span class="ml-2 text-gray-700 dark:text-gray-300">${
                            data.sentiment === 'positive' ? '😊 Положительное' :
                            data.sentiment === 'negative' ? '😞 Отрицательное' :
                            '😐 Нейтральное'
                        }</span>
                    </div>

                    <div>
                        <strong class="text-gray-900 dark:text-white">Требуется действие:</strong>
                        <span class="ml-2 text-gray-700 dark:text-gray-300">${
                            data.action_required ? 'Да' : 'Нет'
                        }</span>
                    </div>
                </div>

                ${data.suggested_response ? `
                <div>
                    <strong class="text-gray-900 dark:text-white">Предлагаемый ответ:</strong>
                    <p class="text-gray-700 dark:text-gray-300 mt-1">${data.suggested_response}</p>
                </div>
                ` : ''}

                <div class="text-xs text-gray-500 dark:text-gray-400 border-t border-gray-200 dark:border-gray-600 pt-2">
                    Модель: ${data.model || 'N/A'} | Токены: ${data.tokens || 'N/A'}
                </div>
            </div>
        `;
    }

    async function loadAnalysisStatus() {
        try {
            const response = await fetch(`{{ route("dashboard.email.analysis-status", $email) }}`);
            const data = await response.json();

            console.log('Initial analysis status:', data);

            if (data.status !== 'not_started') {
                showAnalysisContent();
                updateAnalysisUI(data.status, data.analysis);
                
                // Если анализ еще выполняется, запускаем polling
                if (data.status === 'processing') {
                    startPolling();
                }
            }
        } catch (error) {
            console.error('Error loading analysis status:', error);
        }
    }

    function startPolling() {
        if (pollingInterval) {
            clearInterval(pollingInterval);
        }

        let pollCount = 0;
        const maxPolls = 60; // Максимум 2 минуты (60 * 2 секунды)

        pollingInterval = setInterval(async () => {
            try {
                pollCount++;
                const response = await fetch(`{{ route("dashboard.email.analysis-status", $email) }}`);
                const data = await response.json();

                console.log(`Poll #${pollCount}:`, data);

                // Игнорируем статус "not_started" во время polling - продолжаем ждать
                if (data.status === 'not_started') {
                    return; // Пропускаем обновление UI и продолжаем polling
                }

                updateAnalysisUI(data.status, data.analysis);

                // Останавливаем polling если анализ завершен или ошибка, или превышен лимит
                if (data.status === 'completed' || data.status === 'error' || pollCount >= maxPolls) {
                    stopPolling();
                    if (pollCount >= maxPolls) {
                        console.warn('Polling timeout reached');
                    }
                }
            } catch (error) {
                console.error('Polling error:', error);
                stopPolling();
            }
        }, 2000); // Poll every 2 seconds
    }

    function stopPolling() {
        if (pollingInterval) {
            clearInterval(pollingInterval);
            pollingInterval = null;
        }
    }
});
</script>
@endsection

