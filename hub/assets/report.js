/**
 * JetSocio Hub — GEO 体检报告渲染
 * 调审计 API（Railway），内联渲染报告。零依赖（原生 JS + 内联 SVG）。
 */
(function () {
  "use strict";

  // 审计 API 地址：部署后改为你的 Railway 域名
  const AUDIT_API_URL = "https://audit-api.jetsocio.com/audit";

  const form = document.getElementById("audit-form");
  const input = document.getElementById("audit-url");
  const submit = document.getElementById("audit-submit");
  const reportBox = document.getElementById("audit-report");
  const loadingBox = document.getElementById("audit-loading");

  if (!form || !input || !submit || !reportBox || !loadingBox) return;

  form.addEventListener("submit", async function (e) {
    e.preventDefault();
    const url = input.value.trim();
    if (!url) {
      input.focus();
      return;
    }

    reportBox.classList.add("hidden");
    reportBox.innerHTML = "";
    loadingBox.classList.remove("hidden");
    submit.disabled = true;
    submit.textContent = "体检中…";

    try {
      const res = await fetch(AUDIT_API_URL, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ url: normalizeUrl(url) }),
      });
      const json = await res.json().catch(() => null);

      if (!res.ok || !json || json.success !== true || !json.data) {
        throw new Error(json && json.error ? json.error : "服务暂时不可用，请稍后再试");
      }
      renderReport(json.data);
    } catch (err) {
      renderError(err.message || "体检失败，请稍后再试");
    } finally {
      loadingBox.classList.add("hidden");
      submit.disabled = false;
      submit.textContent = "免费体检";
    }
  });

  function normalizeUrl(url) {
    if (!/^[a-z][a-z0-9+.-]*:\/\//i.test(url)) return "https://" + url;
    return url;
  }

  function renderReport(data) {
    const score = Math.max(0, Math.min(100, data.score | 0));
    const tone = score >= 80
      ? { color: "#10b981", label: "优秀", text: "text-emerald-600" }
      : score >= 50
        ? { color: "#f59e0b", label: "待提升", text: "text-amber-600" }
        : { color: "#ef4444", label: "需改进", text: "text-red-600" };

    const features = data.raw_features || {};
    const signals = [
      { label: "H1 主标题", ok: !!features.has_h1 },
      { label: "JSON-LD 结构化数据", ok: !data.missing_schema },
      { label: "FAQPage 结构化数据", ok: !data.missing_faq },
      { label: "正文内容量", ok: (features.text_length | 0) >= 400 },
    ];

    const suggestions = Array.isArray(data.suggestions) && data.suggestions.length
      ? data.suggestions
      : ["页面结构良好，可进一步补充原创数据与权威引用。"];

    const R = 52;
    const C = 2 * Math.PI * R;
    const offset = C * (1 - score / 100);

    reportBox.innerHTML = `
      <div class="rounded-2xl bg-white p-6 sm:p-8 shadow-sm ring-1 ring-gray-100">
        <div class="flex flex-col sm:flex-row items-center gap-8">
          <div class="relative h-32 w-32 shrink-0">
            <svg viewBox="0 0 120 120" class="h-32 w-32 -rotate-90">
              <circle cx="60" cy="60" r="${R}" fill="none" stroke="#f1f5f9" stroke-width="10"/>
              <circle cx="60" cy="60" r="${R}" fill="none" stroke="${tone.color}" stroke-width="10" stroke-linecap="round"
                      stroke-dasharray="${C}" stroke-dashoffset="${offset}"/>
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center">
              <span class="text-3xl font-bold text-gray-900 tabular-nums">${score}</span>
              <span class="text-xs font-medium ${tone.text}">${tone.label}</span>
            </div>
          </div>
          <div class="flex-1 text-center sm:text-left min-w-0">
            <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">体检目标</p>
            <p class="mt-1 text-lg font-semibold text-gray-900 break-all">${escapeHtml(data.url || "")}</p>
            <p class="mt-2 text-sm text-gray-500">GEO 引用友好度评分：分数越高，AI 在回答用户问题时越可能引用你的内容。</p>
            <a href="https://github.com/bluesxxx/geo-pro" target="_blank" rel="noopener noreferrer"
               class="mt-4 inline-flex h-10 items-center justify-center rounded-lg bg-brand-700 px-5 text-sm font-semibold text-white hover:bg-brand-800">
              了解 GEO PRO 完整版
            </a>
          </div>
        </div>
      </div>

      <div class="mt-4 grid gap-3 sm:grid-cols-2">
        ${signals.map(function (s) {
          return `<div class="flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full ${s.ok ? "bg-emerald-50 text-emerald-600" : "bg-red-50 text-red-600"}">
              ${s.ok ? CHECK_ICON : X_ICON}
            </span>
            <div class="min-w-0">
              <p class="text-sm font-medium text-gray-900">${escapeHtml(s.label)}</p>
              <p class="text-xs text-gray-500">${s.ok ? "已具备" : "缺失"}</p>
            </div>
          </div>`;
        }).join("")}
      </div>

      <div class="mt-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
        <h3 class="font-semibold text-gray-900">优化建议</h3>
        <ul class="mt-3 space-y-3">
          ${suggestions.map(function (s) {
            return `<li class="flex gap-3 text-sm text-gray-700">
              <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-600"></span>
              <span>${escapeHtml(s)}</span>
            </li>`;
          }).join("")}
        </ul>
      </div>

      <div class="mt-4 rounded-2xl bg-brand-700 p-6 text-white">
        <h3 class="text-base font-semibold">想要完整的 GEO 内容工作流？</h3>
        <p class="mt-1.5 text-sm text-blue-100">GEO PRO 自托管平台：AI 内容生成、知识库、AI 可见性观测、多渠道分发，数据完全自持。</p>
        <a href="https://github.com/bluesxxx/geo-pro" target="_blank" rel="noopener noreferrer"
           class="mt-4 inline-flex h-10 items-center justify-center rounded-lg bg-white px-5 text-sm font-semibold text-brand-700 hover:bg-blue-50">
          GitHub 仓库
        </a>
      </div>`;

    reportBox.classList.remove("hidden");
    reportBox.scrollIntoView({ behavior: "smooth", block: "start" });
  }

  function renderError(message) {
    reportBox.innerHTML = `
      <div class="rounded-2xl bg-white p-8 shadow-sm ring-1 ring-gray-100 text-center">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-50 text-red-600">
          <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        </div>
        <h3 class="mt-5 text-lg font-semibold text-gray-900">未能完成体检</h3>
        <p class="mt-2 text-sm text-gray-500 break-all">${escapeHtml(input.value || "")}</p>
        <p class="mt-3 text-sm text-red-600">${escapeHtml(message)}</p>
      </div>`;
    reportBox.classList.remove("hidden");
    reportBox.scrollIntoView({ behavior: "smooth", block: "start" });
  }

  const CHECK_ICON = `<svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>`;
  const X_ICON = `<svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>`;

  function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }
})();
