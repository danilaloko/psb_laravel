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

        // Helper functions
        function formatPriority(priority) {
            const colors = {
                'high': 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400',
                'medium': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400',
                'low': 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400'
            };
            const labels = {
                'high': 'Высокий',
                'medium': 'Средний',
                'low': 'Низкий'
            };
            return `<span class="px-2 py-1 text-xs rounded-full ${colors[priority] || colors.low}">${labels[priority] || labels.low}</span>`;
        }

        function formatSentiment(sentiment) {
            const sentiments = {
                'positive': '😊 Положительное',
                'negative': '😞 Отрицательное',
                'neutral': '😐 Нейтральное'
            };
            return sentiments[sentiment] || sentiments.neutral;
        }

        function formatRiskLevel(level) {
            const colors = {
                'high': 'text-red-600',
                'medium': 'text-yellow-600',
                'low': 'text-green-600',
                'none': 'text-gray-600'
            };
            const labels = {
                'high': 'Высокий',
                'medium': 'Средний',
                'low': 'Низкий',
                'none': 'Отсутствуют'
            };
            return `<span class="${colors[level] || colors.none}">${labels[level] || labels.none}</span>`;
        }

        function formatList(items, defaultText = 'Не указаны') {
            if (!items || !Array.isArray(items) || items.length === 0) return defaultText;
            return items.map(item => `<span class="inline-block bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-2 py-1 text-xs rounded mr-1 mb-1">${item}</span>`).join('');
        }

        function formatDate(dateString) {
            if (!dateString) return 'Не указана';
            try {
                const date = new Date(dateString);
                return date.toLocaleString('ru-RU', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            } catch (e) {
                return dateString;
            }
        }

        function translatePrimaryType(type) {
            const types = {
                'information_request': 'Запрос информации',
                'complaint': 'Жалоба',
                'regulatory_request': 'Регуляторный запрос',
                'partnership_proposal': 'Партнёрское предложение',
                'approval_request': 'Запрос на согласование',
                'notification': 'Уведомление'
            };
            return types[type] || type;
        }

        function translateSecondaryType(type) {
            const types = {
                'document_request': 'Запрос документов',
                'service_complaint': 'Жалоба на услугу',
                'supervisory_requirement': 'Требование надзорного органа',
                'business_offer': 'Коммерческое предложение',
                'contract_approval': 'Согласование договора',
                'status_update': 'Обновление статуса'
            };
            return types[type] || type;
        }

        function translateBusinessContext(context) {
            const contexts = {
                'operational': 'Операционная деятельность',
                'financial': 'Финансовые вопросы',
                'legal': 'Юридические аспекты',
                'technical': 'Технические вопросы',
                'commercial': 'Коммерческая деятельность',
                'administrative': 'Административные вопросы'
            };
            return contexts[context] || context;
        }

        function translateFormalityLevel(level) {
            const levels = {
                'high': 'Высокий',
                'medium': 'Средний',
                'low': 'Низкий'
            };
            return levels[level] || level;
        }

        analysisResults.innerHTML = `
            <div class="space-y-6">
                <!-- Основная информация -->
                <div>
                    <h4 class="font-semibold text-gray-900 dark:text-white mb-3">📋 Основная информация</h4>
                    <div class="space-y-2">
                        <div>
                            <strong class="text-gray-900 dark:text-white">Содержание:</strong>
                            <p class="text-gray-700 dark:text-gray-300 mt-1">${data.summary || 'Не указано'}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <strong class="text-gray-900 dark:text-white">Приоритет:</strong>
                                <div class="mt-1">${formatPriority(data.priority)}</div>
                            </div>
                            <div>
                                <strong class="text-gray-900 dark:text-white">Настроение:</strong>
                                <span class="ml-2 text-gray-700 dark:text-gray-300">${formatSentiment(data.sentiment)}</span>
                            </div>
                            <div>
                                <strong class="text-gray-900 dark:text-white">Требуется действие:</strong>
                                <span class="ml-2 text-gray-700 dark:text-gray-300">${data.action_required ? 'Да' : 'Нет'}</span>
                            </div>
                            <div>
                                <strong class="text-gray-900 dark:text-white">Срок выполнения:</strong>
                                <span class="ml-2 text-gray-700 dark:text-gray-300">${data.deadline || 'Не указан'}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Классификация -->
                ${data.classification && (data.classification.primary_type || data.classification.secondary_type || data.classification.business_context) ? `
                <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                    <h4 class="font-semibold text-gray-900 dark:text-white mb-3">🏷️ Классификация письма</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        ${data.classification.primary_type ? `
                        <div>
                            <strong class="text-gray-900 dark:text-white">Основной тип:</strong>
                            <div class="mt-1">
                                <span class="inline-block bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 px-2 py-1 text-sm rounded font-medium">
                                    ${translatePrimaryType(data.classification.primary_type)}
                                </span>
                            </div>
                        </div>
                        ` : ''}
                        ${data.classification.secondary_type ? `
                        <div>
                            <strong class="text-gray-900 dark:text-white">Вторичный тип:</strong>
                            <div class="mt-1">
                                <span class="inline-block bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 px-2 py-1 text-sm rounded font-medium">
                                    ${translateSecondaryType(data.classification.secondary_type)}
                                </span>
                            </div>
                        </div>
                        ` : ''}
                        ${data.classification.business_context ? `
                        <div>
                            <strong class="text-gray-900 dark:text-white">Бизнес-контекст:</strong>
                            <div class="mt-1">
                                <span class="inline-block bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 px-2 py-1 text-sm rounded font-medium">
                                    ${translateBusinessContext(data.classification.business_context)}
                                </span>
                            </div>
                        </div>
                        ` : ''}
                    </div>
                </div>
                ` : ''}

                <!-- Параметры обработки -->
                ${data.processing_requirements && (data.processing_requirements.sla_deadline || data.processing_requirements.response_formality_level || (data.processing_requirements.approval_departments && data.processing_requirements.approval_departments.length > 0)) ? `
                <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                    <h4 class="font-semibold text-gray-900 dark:text-white mb-3">⚙️ Параметры обработки</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        ${data.processing_requirements.sla_deadline ? `
                        <div>
                            <strong class="text-gray-900 dark:text-white">SLA дедлайн:</strong>
                            <span class="ml-2 text-gray-700 dark:text-gray-300 font-medium">${formatDate(data.processing_requirements.sla_deadline)}</span>
                        </div>
                        ` : ''}
                        ${data.processing_requirements.response_formality_level ? `
                        <div>
                            <strong class="text-gray-900 dark:text-white">Уровень формальности:</strong>
                            <span class="ml-2 text-gray-700 dark:text-gray-300 font-medium">${translateFormalityLevel(data.processing_requirements.response_formality_level)}</span>
                        </div>
                        ` : ''}
                        ${data.processing_requirements.approval_departments && data.processing_requirements.approval_departments.length > 0 ? `
                        <div>
                            <strong class="text-gray-900 dark:text-white">Необходимые согласования:</strong>
                            <div class="mt-1">${formatList(data.processing_requirements.approval_departments)}</div>
                        </div>
                        ` : ''}
                        ${typeof data.processing_requirements.escalation_required === 'boolean' ? `
                        <div>
                            <strong class="text-gray-900 dark:text-white">Эскалация:</strong>
                            <span class="ml-2 text-gray-700 dark:text-gray-300">${data.processing_requirements.escalation_required ? 'Требуется' : 'Не требуется'}</span>
                        </div>
                        ` : ''}
                    </div>

                    ${data.processing_requirements.legal_risks && data.processing_requirements.legal_risks.risk_level && data.processing_requirements.legal_risks.risk_level !== 'none' ? `
                    <div class="mt-4 p-3 border border-gray-200 dark:border-gray-700 rounded">
                        <strong class="text-gray-900 dark:text-white">Юридические риски:</strong>
                        <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-2">
                            <div>
                                <span class="text-gray-700 dark:text-gray-300">Уровень риска:</span>
                                <div class="mt-1">${formatRiskLevel(data.processing_requirements.legal_risks.risk_level)}</div>
                            </div>
                            ${data.processing_requirements.legal_risks.risk_factors && data.processing_requirements.legal_risks.risk_factors.length > 0 ? `
                            <div>
                                <span class="text-gray-700 dark:text-gray-300">Факторы риска:</span>
                                <div class="mt-1">${formatList(data.processing_requirements.legal_risks.risk_factors)}</div>
                            </div>
                            ` : ''}
                        </div>
                        ${data.processing_requirements.legal_risks.recommended_actions && data.processing_requirements.legal_risks.recommended_actions.length > 0 ? `
                        <div class="mt-2">
                            <span class="text-gray-700 dark:text-gray-300">Рекомендуемые действия:</span>
                            <div class="mt-1">${formatList(data.processing_requirements.legal_risks.recommended_actions)}</div>
                        </div>
                        ` : ''}
                    </div>
                    ` : ''}
                </div>
                ` : ''}

                <!-- Контактная информация и ссылки -->
                ${data.content_analysis && (data.content_analysis.core_request ||
                    (data.content_analysis.contact_information && data.content_analysis.contact_information.sender_details && (
                        data.content_analysis.contact_information.sender_details.name ||
                        data.content_analysis.contact_information.sender_details.position ||
                        data.content_analysis.contact_information.sender_details.organization ||
                        data.content_analysis.contact_information.sender_details.phone
                    )) ||
                    (data.content_analysis.regulatory_references && (
                        (data.content_analysis.regulatory_references.laws_and_regulations && data.content_analysis.regulatory_references.laws_and_regulations.length > 0) ||
                        (data.content_analysis.regulatory_references.contract_references && data.content_analysis.regulatory_references.contract_references.length > 0)
                    )) ||
                    (data.content_analysis.requirements_and_expectations && (
                        (data.content_analysis.requirements_and_expectations.explicit_requirements && data.content_analysis.requirements_and_expectations.explicit_requirements.length > 0) ||
                        (data.content_analysis.requirements_and_expectations.implicit_expectations && data.content_analysis.requirements_and_expectations.implicit_expectations.length > 0) ||
                        data.content_analysis.requirements_and_expectations.preferred_outcome
                    ))
                ) ? `
                <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                    <h4 class="font-semibold text-gray-900 dark:text-white mb-3">📞 Контактная информация</h4>
                    <div class="space-y-3">
                        ${data.content_analysis.core_request ? `
                        <div>
                            <strong class="text-gray-900 dark:text-white">Суть запроса:</strong>
                            <p class="text-gray-800 dark:text-gray-200 mt-1">${data.content_analysis.core_request}</p>
                        </div>
                        ` : ''}

                        ${data.content_analysis.contact_information && data.content_analysis.contact_information.sender_details && (
                            data.content_analysis.contact_information.sender_details.name ||
                            data.content_analysis.contact_information.sender_details.position ||
                            data.content_analysis.contact_information.sender_details.organization ||
                            data.content_analysis.contact_information.sender_details.phone
                        ) ? `
                        <div>
                            <strong class="text-gray-900 dark:text-white">Отправитель:</strong>
                            <div class="mt-1 text-gray-800 dark:text-gray-200">
                                ${data.content_analysis.contact_information.sender_details.name ? `<div>👤 ${data.content_analysis.contact_information.sender_details.name}</div>` : ''}
                                ${data.content_analysis.contact_information.sender_details.position ? `<div>🏢 ${data.content_analysis.contact_information.sender_details.position}</div>` : ''}
                                ${data.content_analysis.contact_information.sender_details.organization ? `<div>🏛️ ${data.content_analysis.contact_information.sender_details.organization}</div>` : ''}
                                ${data.content_analysis.contact_information.sender_details.phone ? `<div>📞 ${data.content_analysis.contact_information.sender_details.phone}</div>` : ''}
                            </div>
                        </div>
                        ` : ''}

                        ${data.content_analysis.regulatory_references && (
                            (data.content_analysis.regulatory_references.laws_and_regulations && data.content_analysis.regulatory_references.laws_and_regulations.length > 0) ||
                            (data.content_analysis.regulatory_references.contract_references && data.content_analysis.regulatory_references.contract_references.length > 0)
                        ) ? `
                        <div>
                            <strong class="text-gray-900 dark:text-white">Нормативные ссылки:</strong>
                            <div class="mt-1">
                                ${data.content_analysis.regulatory_references.laws_and_regulations && data.content_analysis.regulatory_references.laws_and_regulations.length > 0 ?
                                    `<div class="mb-2"><strong class="text-sm text-gray-900 dark:text-white">Законы и нормативные акты:</strong></div>${formatList(data.content_analysis.regulatory_references.laws_and_regulations)}` : ''}
                                ${data.content_analysis.regulatory_references.contract_references && data.content_analysis.regulatory_references.contract_references.length > 0 ?
                                    `<div class="mb-2 mt-2"><strong class="text-sm text-gray-900 dark:text-white">Договорные ссылки:</strong></div>${formatList(data.content_analysis.regulatory_references.contract_references)}` : ''}
                            </div>
                        </div>
                        ` : ''}

                        ${data.content_analysis.requirements_and_expectations && (
                            (data.content_analysis.requirements_and_expectations.explicit_requirements && data.content_analysis.requirements_and_expectations.explicit_requirements.length > 0) ||
                            (data.content_analysis.requirements_and_expectations.implicit_expectations && data.content_analysis.requirements_and_expectations.implicit_expectations.length > 0) ||
                            data.content_analysis.requirements_and_expectations.preferred_outcome
                        ) ? `
                        <div>
                            <strong class="text-gray-900 dark:text-white">Требования и ожидания:</strong>
                            <div class="mt-1 space-y-2">
                                ${data.content_analysis.requirements_and_expectations.explicit_requirements && data.content_analysis.requirements_and_expectations.explicit_requirements.length > 0 ?
                                    `<div><strong class="text-sm text-gray-900 dark:text-white">Явные требования:</strong></div>${formatList(data.content_analysis.requirements_and_expectations.explicit_requirements)}` : ''}
                                ${data.content_analysis.requirements_and_expectations.implicit_expectations && data.content_analysis.requirements_and_expectations.implicit_expectations.length > 0 ?
                                    `<div class="mt-2"><strong class="text-sm text-gray-900 dark:text-white">Неявные ожидания:</strong></div>${formatList(data.content_analysis.requirements_and_expectations.implicit_expectations)}` : ''}
                                ${data.content_analysis.requirements_and_expectations.preferred_outcome ?
                                    `<div class="mt-2"><strong class="text-sm text-gray-900 dark:text-white">Желаемый результат:</strong> <span class="text-gray-800 dark:text-gray-200">${data.content_analysis.requirements_and_expectations.preferred_outcome}</span></div>` : ''}
                            </div>
                        </div>
                        ` : ''}
                    </div>
                </div>
                ` : ''}

                <!-- Запросы документов -->
                ${data.metadata_analysis && data.metadata_analysis.document_requests && (
                    (data.metadata_analysis.document_requests.document_types && data.metadata_analysis.document_requests.document_types.length > 0) ||
                    (data.metadata_analysis.document_requests.urgency_level && data.metadata_analysis.document_requests.urgency_level !== 'none') ||
                    (data.metadata_analysis.document_requests.format_requirements && data.metadata_analysis.document_requests.format_requirements.length > 0)
                ) ? `
                <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                    <h4 class="font-semibold text-gray-900 dark:text-white mb-3">📄 Запросы документов</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        ${data.metadata_analysis.document_requests.document_types && data.metadata_analysis.document_requests.document_types.length > 0 ? `
                        <div>
                            <strong class="text-gray-900 dark:text-white">Типы документов:</strong>
                            <div class="mt-1">${formatList(data.metadata_analysis.document_requests.document_types)}</div>
                        </div>
                        ` : ''}
                        ${data.metadata_analysis.document_requests.urgency_level && data.metadata_analysis.document_requests.urgency_level !== 'none' ? `
                        <div>
                            <strong class="text-gray-900 dark:text-white">Срочность:</strong>
                            <span class="ml-2 text-gray-700 dark:text-gray-300">${data.metadata_analysis.document_requests.urgency_level}</span>
                        </div>
                        ` : ''}
                        ${data.metadata_analysis.document_requests.format_requirements && data.metadata_analysis.document_requests.format_requirements.length > 0 ? `
                        <div>
                            <strong class="text-gray-900 dark:text-white">Формат:</strong>
                            <div class="mt-1">${formatList(data.metadata_analysis.document_requests.format_requirements)}</div>
                        </div>
                        ` : ''}
                    </div>
                </div>
                ` : ''}

                <!-- Рекомендации по действиям -->
                ${data.action_recommendations ? `
                <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                    <h4 class="font-semibold text-gray-900 dark:text-white mb-3">🎯 Рекомендации по действиям</h4>
                    <div class="space-y-3">
                        ${data.action_recommendations.immediate_actions && data.action_recommendations.immediate_actions.length > 0 ? `
                        <div>
                            <strong class="text-gray-900 dark:text-white">Немедленные действия:</strong>
                            <div class="mt-1">${formatList(data.action_recommendations.immediate_actions)}</div>
                        </div>
                        ` : ''}
                        ${data.action_recommendations.follow_up_actions && data.action_recommendations.follow_up_actions.length > 0 ? `
                        <div>
                            <strong class="text-gray-900 dark:text-white">Последующие действия:</strong>
                            <div class="mt-1">${formatList(data.action_recommendations.follow_up_actions)}</div>
                        </div>
                        ` : ''}
                        ${data.action_recommendations.preventive_measures && data.action_recommendations.preventive_measures.length > 0 ? `
                        <div>
                            <strong class="text-gray-900 dark:text-white">Профилактические меры:</strong>
                            <div class="mt-1">${formatList(data.action_recommendations.preventive_measures)}</div>
                        </div>
                        ` : ''}
                    </div>
                </div>
                ` : ''}

                <!-- Предлагаемый ответ -->
                ${data.suggested_response ? `
                <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                    <h4 class="font-semibold text-gray-900 dark:text-white mb-3">💬 Кратко что нужно сделать</h4>
                    <div class="border border-gray-200 dark:border-gray-700 rounded p-3">
                        <p class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">${data.suggested_response}</p>
                    </div>
                </div>
                ` : ''}

                <!-- Техническая информация -->
                <div class="text-xs text-gray-500 dark:text-gray-400 border-t border-gray-200 dark:border-gray-600 pt-2">
                    Модель: ${data.model || 'N/A'} | Токены: ${data.tokens || 'N/A'} | Время: ${data.processing_time || 'N/A'}s
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

