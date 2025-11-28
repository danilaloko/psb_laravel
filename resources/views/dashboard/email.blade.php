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
        </div>
    </div>
</div>
@endsection

