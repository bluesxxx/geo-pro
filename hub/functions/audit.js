// JetSocio 审计 API 反代（Cloudflare Pages Function）
//
// 背景：Railway 后端在国内无法直接访问。前端跑在 Cloudflare Pages（国内可访问，
// 同域 pages.dev），本函数与前端同源部署在 Cloudflare 边缘，由服务端去抓 Railway 后端，
// 浏览器只与本站同源通信——规避了独立 *.workers.dev 子域在国内的不可达问题。
//
// 链路：浏览器 → 同源 /audit（Pages Function）→ Railway 审计后端
//
// 说明：
// - 浏览器同源请求，无需 CORS；函数仍输出 ACAO:* 以便调试。
// - Railway 后端已放开“无 Origin 的服务端调用”，本函数直接转发即可。
// - 绑定自定义域名（如 api.jetsocio.com）后，本函数随之在该域名下生效，无需改代码。

const UPSTREAM = "https://geo-pro-production.up.railway.app";

export async function onRequestOptions() {
  return new Response(null, {
    status: 204,
    headers: {
      "Access-Control-Allow-Origin": "*",
      "Access-Control-Allow-Methods": "POST, OPTIONS",
      "Access-Control-Allow-Headers": "Content-Type",
      "Access-Control-Max-Age": "86400",
    },
  });
}

export async function onRequest({ request }) {
  // 仅代理 /audit（POST）
  if (request.method === "POST") {
    try {
      const body = await request.text();
      const upstreamResp = await fetch(UPSTREAM + "/audit", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body,
      });
      const respBody = await upstreamResp.text();
      return new Response(respBody, {
        status: upstreamResp.status,
        headers: {
          "Content-Type": "application/json; charset=utf-8",
          "Access-Control-Allow-Origin": "*",
        },
      });
    } catch (e) {
      return new Response(
        JSON.stringify({ error: "upstream_unreachable", detail: String(e) }),
        {
          status: 502,
          headers: {
            "Content-Type": "application/json; charset=utf-8",
            "Access-Control-Allow-Origin": "*",
          },
        }
      );
    }
  }

  return new Response("JetSocio audit proxy (Pages Function). POST /audit", {
    status: 200,
    headers: {
      "Content-Type": "text/plain; charset=utf-8",
      "Access-Control-Allow-Origin": "*",
    },
  });
}
