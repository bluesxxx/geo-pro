# JetSocio 审计 API

极简无状态体检服务，供 `https://jetsocio.com` 静态 HUB 首屏调用。

- **入口**：`POST /audit`，body `{"url":"https://example.com"}`，返回 JSON 报告。
- **CORS**：仅放行 `https://jetsocio.com` / `https://www.jetsocio.com`。
- **限流**：固定窗口，每 IP 每分钟 10 次（临时目录计数，无 DB）。
- **SSRF 防护**：`CurlWebPageFetcher` 内置（仅 http/https、拒绝内网/保留 IP）。
- **审计逻辑**：`../../packages/audit-engine` —— 与 GEO PRO 本地版共用同一份代码，杜绝双写。

## 本地运行

```bash
php -S 0.0.0.0:8080 -t .      # 需 php 8.1+ 与 curl/dom 扩展
curl -X POST http://localhost:8080/audit \
  -H 'Content-Type: application/json' \
  -H 'Origin: https://jetsocio.com' \
  -d '{"url":"https://example.com"}'
```

## Railway 部署

1. New Project → Deploy from GitHub（`bluesxxx/geo-pro`，子目录构建 `services/audit-api`）。
2. 构建：Dockerfile 使用 `services/audit-api/Dockerfile`（根目录构建上下文），镜像为 `php:8.4-cli`，监听 `8080`。
3. Railway 自动映射公网 URL（如 `audit-api.up.railway.app`），无需持久卷、无环境变量。
4. 在 Cloudflare DNS 为 `audit-api.jetsocio.com` 添加 CNAME → Railway 域名；或将 `https://audit-api.jetsocio.com` 配置到 HUB 的 `AUDIT_API_URL`。

## 契约

```jsonc
// 200
{ "success": true, "data": {
    "url": "https://example.com",
    "score": 85,                       // 0-100
    "missing_faq": false,
    "missing_schema": false,
    "suggestions": ["...", "..."],
    "raw_features": { "has_h1": true, "has_schema": true, "has_faq_schema": true,
                      "title": "...", "meta_description": "...",
                      "text_length": 1234, "buzzword_count": 0 }
} }
// 422 输入不合法 / 目标不可达；429 限流；403 Origin 不允许；405 非 POST
```
