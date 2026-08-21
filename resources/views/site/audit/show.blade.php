@extends('site.layout')

@section('content')
    @php
        $isCompleted = $auditRun->status === \App\Models\AuditRun::STATUS_COMPLETED;
        $isFailed = $auditRun->status === \App\Models\AuditRun::STATUS_FAILED;
        $score = (int) ($auditRun->score ?? 0);
        $scoreTone = $score >= 80 ? ['text' => 'text-emerald-600', 'ring' => '#10b981', 'label' => '优秀'] : ($score >= 50 ? ['text' => 'text-amber-600', 'ring' => '#f59e0b', 'label' => '待提升'] : ['text' => 'text-red-600', 'ring' => '#ef4444', 'label' => '需改进']);
        $circumference = 2 * M_PI * 52;
        $dashOffset = $circumference * (1 - $score / 100);
    @endphp

    <div class="bg-gradient-to-b from-blue-50/60 to-white min-h-[70vh]">
        <div class="site-container px-4 sm:px-6 lg:px-8 py-12 lg:py-16">
            <div class="max-w-3xl mx-auto">
                <a href="{{ route('site.audit.form') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-blue-700 hover:text-blue-800">
                    <i data-lucide="arrow-left" class="w-4 h-4" aria-hidden="true"></i>
                    重新体检
                </a>

                @if ($isFailed)
                    <div class="mt-6 rounded-2xl bg-white p-8 shadow-sm ring-1 ring-gray-100 text-center">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-50 text-red-600">
                            <i data-lucide="alert-triangle" class="h-7 w-7" aria-hidden="true"></i>
                        </div>
                        <h1 class="mt-5 text-xl font-semibold text-gray-900">未能完成体检</h1>
                        <p class="mt-2 text-sm text-gray-500 break-all">{{ $auditRun->url }}</p>
                        <p class="mt-4 text-sm text-red-600">{{ $auditRun->error_message }}</p>
                        <a href="{{ route('site.audit.form') }}" class="mt-6 inline-flex h-11 items-center justify-center rounded-lg bg-blue-700 px-6 text-sm font-semibold text-white hover:bg-blue-800">
                            重试
                        </a>
                    </div>
                @elseif ($isCompleted)
                    <div class="mt-6 rounded-2xl bg-white p-8 shadow-sm ring-1 ring-gray-100">
                        <div class="flex flex-col sm:flex-row items-center gap-8">
                            <div class="relative h-32 w-32 shrink-0">
                                <svg viewBox="0 0 120 120" class="h-32 w-32 -rotate-90">
                                    <circle cx="60" cy="60" r="52" fill="none" stroke="#f1f5f9" stroke-width="10" />
                                    <circle cx="60" cy="60" r="52" fill="none" stroke="{{ $scoreTone['ring'] }}" stroke-width="10" stroke-linecap="round"
                                            stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $dashOffset }}" />
                                </svg>
                                <div class="absolute inset-0 flex flex-col items-center justify-center">
                                    <span class="text-3xl font-bold text-gray-900 tabular-nums">{{ $score }}</span>
                                    <span class="text-xs font-medium {{ $scoreTone['text'] }}">{{ $scoreTone['label'] }}</span>
                                </div>
                            </div>
                            <div class="flex-1 text-center sm:text-left">
                                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">体检目标</p>
                                <h1 class="mt-1 text-lg font-semibold text-gray-900 break-all">{{ $auditRun->url }}</h1>
                                <p class="mt-2 text-sm text-gray-500">
                                    GEO 引用友好度评分：分数越高，AI 在回答用户问题时越可能引用你的内容。
                                </p>
                                <a href="{{ route('site.audit.form') }}" class="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-blue-700 hover:text-blue-800">
                                    <i data-lucide="rotate-cw" class="w-4 h-4" aria-hidden="true"></i>
                                    再测一个网址
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-4 sm:grid-cols-2">
                        @php
                            $signalChecks = [
                                ['label' => 'H1 主标题', 'ok' => !empty($auditRun->raw_features['has_h1']), 'icon' => 'heading-1'],
                                ['label' => 'JSON-LD 结构化数据', 'ok' => !$auditRun->missing_schema, 'icon' => 'braces'],
                                ['label' => 'FAQPage 结构化数据', 'ok' => !$auditRun->missing_faq, 'icon' => 'message-circle-question'],
                                ['label' => '正文内容量', 'ok' => (int) ($auditRun->raw_features['text_length'] ?? 0) >= 400, 'icon' => 'file-text'],
                            ];
                        @endphp
                        @foreach ($signalChecks as $signal)
                            <div class="flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $signal['ok'] ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-600' }}">
                                    <i data-lucide="{{ $signal['ok'] ? 'check' : 'x' }}" class="h-4.5 w-4.5" aria-hidden="true"></i>
                                </span>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-900">{{ $signal['label'] }}</p>
                                    <p class="text-xs text-gray-500">{{ $signal['ok'] ? '已具备' : '缺失' }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
                        <h2 class="flex items-center gap-2 font-semibold text-gray-900">
                            <i data-lucide="lightbulb" class="w-5 h-5 text-amber-500" aria-hidden="true"></i>
                            优化建议
                        </h2>
                        <ul class="mt-4 space-y-3">
                            @forelse ($auditRun->suggestions as $suggestion)
                                <li class="flex gap-3 text-sm text-gray-700">
                                    <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-blue-600"></span>
                                    <span>{{ $suggestion }}</span>
                                </li>
                            @empty
                                <li class="text-sm text-gray-500">暂无建议，页面结构良好。</li>
                            @endforelse
                        </ul>
                    </div>

                    <div class="mt-6 rounded-2xl bg-blue-700 p-6 sm:p-8 text-white">
                        <h2 class="text-lg font-semibold">想要完整的 GEO 内容工作流？</h2>
                        <p class="mt-2 text-sm text-blue-100 leading-relaxed">
                            GEO PRO 是一套自托管的 GEO 内容平台：AI 内容生成、知识库、AI 可见性观测、多渠道分发，全部数据留在你自己的服务器。
                        </p>
                        <div class="mt-5 flex flex-wrap gap-3">
                            <a href="{{ route('site.about') }}" class="inline-flex h-11 items-center justify-center rounded-lg bg-white px-6 text-sm font-semibold text-blue-700 hover:bg-blue-50">
                                了解 GEO PRO
                            </a>
                            <a href="{{ route('site.audit.form') }}" class="inline-flex h-11 items-center justify-center rounded-lg border border-blue-300/60 px-6 text-sm font-semibold text-white hover:bg-blue-800">
                                继续体检
                            </a>
                        </div>
                    </div>
                @else
                    <div class="mt-6 rounded-2xl bg-white p-10 shadow-sm ring-1 ring-gray-100 text-center">
                        <i data-lucide="loader-circle" class="mx-auto h-8 w-8 animate-spin text-blue-600" aria-hidden="true"></i>
                        <p class="mt-4 text-sm text-gray-500">体检进行中，请稍候刷新…</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
