/**
 * JetSocio Hub — GEO 体检报告渲染
 * 调审计 API（经 Cloudflare Worker 反代 Railway 后端），内联渲染报告。
 * 零依赖（原生 JS + 内联 SVG）；样式走 index.html 的 .report-* 类，与全站暗色主题一致。
 * 注意：不要使用 Tailwind 类名——本 Hub 是纯内联 CSS，无 Tailwind 构建。
 */
(function () {
  "use strict";

  // 审计 API 入口：同源 Pages Function /audit（Cloudflare 边缘函数服务端反代 Railway 后端）。
  // 浏览器只与本站同源通信，规避独立 *.workers.dev 在国内的不可达问题。
  // 绑定自定义域名（如 api.jetsocio.com）后，本函数随之在该域名下生效，无需改此处。
  const AUDIT_API_URL = "/audit";

  const CHECK_ICON = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>';
  const X_ICON = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>';
  const WARN_ICON = '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>';

  function tt(key, fb) {
    return window.JS_I18N ? window.JS_I18N.t(key, fb) : (fb != null ? fb : key);
  }

  const form = document.getElementById("audit-form");
  const input = document.getElementById("audit-url");
  const submit = document.getElementById("audit-submit");
  const reportBox = document.getElementById("audit-report");
  const loadingBox = document.getElementById("audit-loading");

  if (!form || !input || !submit || !reportBox || !loadingBox) return;

  let lastReportData = null;

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
    submit.textContent = tt("r_loading", "体检中…");

    try {
      const res = await fetch(AUDIT_API_URL, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ url: normalizeUrl(url) }),
      });
      const json = await res.json().catch(() => null);

      if (!res.ok || !json || json.success !== true || !json.data) {
        throw new Error(json && json.error ? json.error : tt("r_err_default", "服务暂时不可用，请稍后再试"));
      }
      renderReport(json.data, true);
    } catch (err) {
      renderError(err.message || tt("r_fetch_err", "体检失败，请稍后再试"));
    } finally {
      loadingBox.classList.add("hidden");
      submit.disabled = false;
      submit.textContent = tt("r_submit", "免费体检");
    }
  });

  function normalizeUrl(url) {
    if (!/^[a-z][a-z0-9+.-]*:\/\//i.test(url)) return "https://" + url;
    return url;
  }

  function renderReport(data, scroll) {
    lastReportData = data;
    const score = Math.max(0, Math.min(100, data.score | 0));
    const tone = score >= 80
      ? { color: "#34d399", label: tt("r_score_excellent", "优秀") }
      : score >= 50
        ? { color: "#f59e0b", label: tt("r_score_warn", "待提升") }
        : { color: "#ef4444", label: tt("r_score_low", "需改进") };

    // Deep audit categories (meta / structured / ai_ready / content)
    const cats = Array.isArray(data.categories) ? data.categories : [];
    const catHtml = cats.map(function (cat) {
      const total = cat.total | 0;
      const passed = cat.passed | 0;
      const pct = total > 0 ? Math.round((passed / total) * 100) : 100;
      const color = pct >= 80 ? "#34d399" : pct >= 50 ? "#f59e0b" : "#ef4444";
      const issues = Array.isArray(cat.issues) ? cat.issues : [];

      const issueItems = issues.length === 0
        ? '<p class="ri-all">' + escapeHtml(tt("r_cat_allpass", "本类全部通过")) + "</p>"
        : issues.map(function (iss) {
            const sev = String(iss.severity || "info");
            const title = tt("i_" + iss.code + "_t", iss.code);
            const detail = tt("i_" + iss.code + "_d", "");
            const rec = tt("i_" + iss.code + "_r", "");

            return '<details class="ri">'
              + "<summary>"
              + '<span class="sev sev-' + escapeHtml(sev) + '">' + escapeHtml(tt("r_sev_" + sev, sev)) + "</span>"
              + '<span class="rt">' + escapeHtml(title) + "</span>"
              + "</summary>"
              + '<div class="rb">'
              + (detail ? '<p class="rd">' + escapeHtml(detail) + "</p>" : "")
              + (rec ? '<p class="rf"><b>' + escapeHtml(tt("r_howto", "如何修复：")) + "</b> " + escapeHtml(rec) + "</p>" : "")
              + (iss.evidence
                  ? '<details class="rev"><summary>' + escapeHtml(tt("r_evidence", "证据")) + "</summary><pre>"
                    + escapeHtml(String(iss.evidence)) + "</pre></details>"
                  : "")
              + "</div>"
              + "</details>";
          }).join("");

      return '<div class="rcat">'
        + '<div class="rch">'
        + '<span class="rc-dot" style="background:' + color + '"></span>'
        + "<h3>" + escapeHtml(tt("r_cat_" + cat.id, cat.id)) + "</h3>"
        + '<span class="rchip" style="color:' + color + ";border-color:" + color + '">' + passed + "/" + total + "</span>"
        + "</div>"
        + issueItems
        + "</div>";
    }).join("");

    // Legacy suggestion list; fall back to the top issue recommendations.
    let suggestions = Array.isArray(data.suggestions) && data.suggestions.length
      ? data.suggestions
      : null;
    if (!suggestions) {
      const topIssues = Array.isArray(data.issues) ? data.issues.slice(0, 5) : [];
      suggestions = topIssues
        .map(function (iss) { return tt("i_" + iss.code + "_r", ""); })
        .filter(Boolean);
    }
    if (!suggestions || !suggestions.length) {
      suggestions = [tt("r_default_suggest", "页面结构良好，可进一步补充原创数据与权威引用。")];
    }

    const R = 52;
    const C = 2 * Math.PI * R;
    const offset = C * (1 - score / 100);

    reportBox.innerHTML = `
      <div class="report">
        <div class="report-card">
          <div class="report-flex">
            <div class="report-scorewrap">
              <svg viewBox="0 0 120 120" width="128" height="128" style="transform:rotate(-90deg)">
                <circle cx="60" cy="60" r="${R}" fill="none" stroke="rgba(255,255,255,0.10)" stroke-width="10"/>
                <circle cx="60" cy="60" r="${R}" fill="none" stroke="${tone.color}" stroke-width="10" stroke-linecap="round" stroke-dasharray="${C}" stroke-dashoffset="${offset}"/>
              </svg>
              <div class="report-score-num">
                <span class="n">${score}</span>
                <span class="l" style="color:${tone.color}">${tone.label}</span>
              </div>
            </div>
            <div class="report-meta">
              <p class="t">${tt("r_target", "体检目标")}</p>
              <p class="u">${escapeHtml(data.url || "")}</p>
              <p class="h">${tt("r_score_hint2", tt("r_score_hint", "GEO 就绪度评分：分数越高，AI 在回答用户问题时越可能引用你的内容。"))}</p>
              ${data.passed_checks != null && data.total_checks != null
                  ? `<p class="h"><b style="color:#fff">${data.passed_checks}/${data.total_checks}</b> ${tt("r_checks_passed", "项检测通过")}</p>`
                  : ""}
              <a href="products/geo-pro.html" class="report-cta-link">${tt("r_cta_btn2", "了解 Geo Pro 完整版")}</a>
            </div>
          </div>
        </div>

        <div class="report-cats">
          ${catHtml}
        </div>

        <div class="report-suggest">
          <h3>${tt("r_suggest_title", "优化建议")}</h3>
          <ul>
            ${suggestions.map(function (s) {
              return `<li><span class="dot"></span><span>${escapeHtml(s)}</span></li>`;
            }).join("")}
          </ul>
        </div>

        <div class="report-cta">
          <h3>${tt("r_cta_title", "想要完整的 GEO 内容工作流？")}</h3>
          <p>${tt("r_cta_desc", "Geo Pro 自托管平台：AI 内容生成、知识库、AI 可见性观测、多渠道分发，数据完全自持。")}</p>
        </div>
      </div>`;

    reportBox.classList.remove("hidden");
    if (scroll) reportBox.scrollIntoView({ behavior: "smooth", block: "start" });
  }

  function renderError(message) {
    lastReportData = null;
    reportBox.innerHTML = `
      <div class="report">
        <div class="report-error">
          <div class="ic">${WARN_ICON}</div>
          <h3>${tt("r_err_title", "未能完成体检")}</h3>
          <p class="target">${escapeHtml(input.value || "")}</p>
          <p class="msg">${escapeHtml(message)}</p>
        </div>
      </div>`;
    reportBox.classList.remove("hidden");
    reportBox.scrollIntoView({ behavior: "smooth", block: "start" });
  }

  // 切语言时，若报告已展示则就地重渲（不滚动）
  document.addEventListener("langchange", function () {
    if (lastReportData && !reportBox.classList.contains("hidden")) {
      renderReport(lastReportData, false);
    }
  });

  function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }
})();
