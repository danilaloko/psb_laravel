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
            'system' => 'Ты - эксперт по анализу корпоративной переписки. Твоя задача - глубоко проанализировать входящее письмо и классифицировать его по множеству параметров. Определи тип запроса, извлеки ключевую информацию, оцени риски и сформулируй рекомендации по обработке. Будь максимально точным и детальным в анализе контактных данных, нормативных ссылок и требований отправителя.',
            'user_template' => "Проанализируй следующее письмо комплексно и предоставь ответ в формате JSON:\n\nПисьмо: {email_content}\n\nТребуемый формат ответа: {response_format}\n\nИнструкции:\n1. Классифицируй письмо по первичному и вторичному типу\n2. Определи бизнес-контекст и уровень формальности коммуникации\n3. Автоматически рассчитай SLA дедлайн на основе типа запроса\n4. Оцени юридические риски и необходимые согласования\n5. Извлеки всю контактную информацию и регуляторные ссылки\n6. Определи явные и неявные требования отправителя\n7. Сформулируй конкретные рекомендации по действиям"
        ],
        'thread_reply' => [
            'system' => 'Ты - помощник для генерации ответов на письма в корпоративной переписке. Учитывай контекст всей переписки, предыдущие письма и ответы. Генерируй профессиональный, вежливый и релевантный ответ.',
            'user_template' => "Сгенерируй ответ на последнее письмо в данной переписке. Учитывай весь контекст переписки:\n\n{thread_context}\n\nТребуемый формат ответа: {response_format}"
        ]
    ]
];

