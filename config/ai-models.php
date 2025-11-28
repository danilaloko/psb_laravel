<?php

return [
    'yandex' => [
        'gpt-pro' => [
            'name' => 'YandexGPT Pro',
            'model' => 'yandexgpt',
            'version' => 'latest',
            'temperature' => 0.7,
            'max_tokens' => 2000,
            'endpoint' => 'https://llm.api.cloud.yandex.net/foundationModels/v1/completion',
        ],
        'gpt-lite' => [
            'name' => 'YandexGPT Lite',
            'model' => 'yandexgpt-lite',
            'version' => 'latest',
            'temperature' => 0.5,
            'max_tokens' => 1000,
            'endpoint' => 'https://llm.api.cloud.yandex.net/foundationModels/v1/completion',
        ],
        'foundation-pro' => [
            'name' => 'Foundation Models Pro',
            'model' => 'general',
            'version' => 'latest',
            'temperature' => 0.3,
            'max_tokens' => 1500,
            'endpoint' => 'https://llm.api.cloud.yandex.net/foundationModels/v1/completion',
        ]
    ],

    'default_model' => 'gpt-pro',

    'prompts' => [
        'email_analysis' => [
            'system' => 'Ты - помощник для анализа входящих писем в корпоративной системе. Анализируй письмо и предоставь структурированный ответ.',
            'user_template' => "Проанализируй следующее письмо и предоставь ответ в формате JSON:\n\nПисьмо: {email_content}\n\nТребуемый формат ответа: {response_format}"
        ]
    ]
];
