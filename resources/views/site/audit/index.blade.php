@extends('site.layout')

@section('content')
    <div class="min-h-[70vh] bg-gradient-to-b from-blue-50/60 to-white">
        <div class="site-container px-4 sm:px-6 lg:px-8 py-14 lg:py-20">
            <div class="max-w-3xl mx-auto text-center">
                <p class="inline-flex items-center rounded-full bg-blue-100 px-4 py-1.5 text-xs font-semibold text-blue-700">
                    <i data-lucide="sparkles" class="w-3.5 h-3.5 mr-1.5" aria-hidden="true"></i>
                    免费 · 免登录 · 秒出报告
                </p>
                <h1 class="mt-5 text-3xl sm:text-4xl font-bold text-gray-900 leading-tight">
                    你的网站，AI 会引用吗？
                </h1>
                <p class="mt-4 text-base sm:text-lg text-gray-600 leading-relaxed">
                    输入网址，立即检查影响「生成式引擎优化（GEO）」的三大关键信号：
                    <span class="whitespace-nowrap">H1 主标题</span> ·
                    <span class="whitespace-nowrap">JSON-LD 结构化数据</span> ·
                    <span class="whitespace-nowrap">FAQPage</span>
                </p>

                <form method="POST" action="{{ route('site.audit.run') }}" class="mt-8" novalidate>
                    @csrf
                    <div class="flex flex-col sm:flex-row gap-3 max-w-xl mx-auto">
                        <div class="flex-1">
                            <input
                                type="url"
                                name="url"
                                value="{{ old('url') }}"
                                required
                                placeholder="https://example.com"
                                class="w-full h-14 rounded-xl border-0 bg-white px-5 text-base text-gray-900 shadow-lg shadow-blue-900/5 ring-1 ring-inset ring-gray-200 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 outline-none"
                            >
                            @error('url')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <button
                            type="submit"
                            class="inline-flex h-14 items-center justify-center gap-2 rounded-xl bg-blue-700 px-7 text-base font-semibold text-white shadow-lg shadow-blue-700/25 hover:bg-blue-800 transition-colors"
                        >
                            <i data-lucide="activity" class="w-5 h-5" aria-hidden="true"></i>
                            免费体检
                        </button>
                    </div>
                </form>

                <p class="mt-4 text-xs text-gray-400">
                    体检由 GEO PRO 审计引擎完成 · 仅分析页面结构，不存储你的提交内容
                </p>
            </div>

            <div class="mt-14 grid gap-4 sm:grid-cols-3 max-w-3xl mx-auto">
                @php
                    $checks = [
                        ['icon' => 'heading-1', 'title' => 'H1 主标题', 'desc' => '让 AI 快速理解页面主题'],
                        ['icon' => 'braces', 'title' => 'JSON-LD 结构化数据', 'desc' => 'Article / Organization / FAQPage'],
                        ['icon' => 'message-circle-question', 'title' => 'FAQ 覆盖', 'desc' => '直接命中用户常见提问'],
                    ];
                @endphp
                @foreach ($checks as $check)
                    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-blue-700">
                            <i data-lucide="{{ $check['icon'] }}" class="h-5 w-5" aria-hidden="true"></i>
                        </div>
                        <h2 class="mt-4 font-semibold text-gray-900">{{ $check['title'] }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ $check['desc'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection
