// JetSocio 审计 API 反向代理（Cloudflare Worker）
//
// 背景：Railway 后端在国内无法直接访问。前端部署在 Cloudflare Pages（国内可访问），
// 通过本 Worker（Cloudflare 边缘，可访问 Railway）转发 /audit 请求，实现“国内可访问的反代”。
//
// 链路：浏览器 → Cloudflare Pages 前端 → 本 Worker（同 Cloudflare）→ Railway 审计后端
//
// 说明：
// - 浏览器跨域由本 Worker 设置 CORS（*）放行；Worker→Railway 为服务端 fetch，不需要 CORS。
// - Railway 审计后端已放开“无 Origin 的服务端调用”，因此本 Worker 直接转发即可。
// - 域名到位后（如 api.jetsocio.com），再将该域名绑定到本 Worker；前端 AUDIT_API_URL 同步改为该域名。

const UPSTREAM = 'https://audit-api.up.railway.app';

addEventListener('fetch', (event) => {
  event.respondWith(handle(event.request));
});

async function handle(request) {
  const url = new URL(request.url);

  // CORS 预检
  if (request.method === 'OPTIONS') {
    return new Response(null, {
      status: 204,
      headers: {
        'Access-Control-Allow-Origin': '*',
        'Access-Control-Allow-Methods': 'POST, OPTIONS',
        'Access-Control-Allow-Headers': 'Content-Type',
        'Access-Control-Max-Age': '86400',
      },
    });
  }

  // 仅代理 /audit（POST）
  if (request.method === 'POST' && url.pathname === '/audit') {
    try {
      const upstreamResp = await fetch(UPSTREAM + '/audit', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: request.body,
      });

      const headers = new Headers(upstreamResp.headers);
      headers.set('Access-Control-Allow-Origin', '*');
      headers.set('Access-Control-Allow-Methods', 'POST, OPTIONS');
      headers.set('Access-Control-Allow-Headers', 'Content-Type');
      headers.delete('access-control-allow-origin'); // 避免与上游冲突

      return new Response(upstreamResp.body, {
        status: upstreamResp.status,
        headers,
      });
    } catch (e) {
      return new Response(
        JSON.stringify({ error: 'upstream_unreachable', detail: String(e) }),
        {
          status: 502,
          headers: {
            'Content-Type': 'application/json; charset=utf-8',
            'Access-Control-Allow-Origin': '*',
          },
        }
      );
    }
  }

  return new Response('JetSocio audit proxy. POST /audit', {
    status: 200,
    headers: {
      'Content-Type': 'text/plain; charset=utf-8',
      'Access-Control-Allow-Origin': '*',
    },
  });
}
