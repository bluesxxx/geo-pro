<?php

/**
 * JetSocio 审计 API — 轻量无状态服务（Railway 部署）。
 *
 * POST /audit { "url": "https://..." }
 *  -> CORS 仅放行 JetSocio Hub 域名
 *  -> 简单固定窗口限流（每 IP 每分钟 10 次）
 *  -> CurlWebPageFetcher（自带 SSRF 防护）+ AuditEngine（与 GEO PRO 共用同一份代码）
 */

/* ---------- 健康检查（必须在加载引擎之前：确保 Railway 健康检查不会因引擎依赖加载失败而拿不到 200） ---------- */
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (($requestUri === '/' || $requestUri === '/health') && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'service' => 'jetsocio-audit-api', 'time' => date('c')]);
    exit;
}

require __DIR__.'/vendor/audit-engine/src/autoload.php';

use GeoPro\AuditEngine\AuditEngine;
use GeoPro\AuditEngine\CurlWebPageFetcher;

const ALLOWED_ORIGINS = [
    'https://jetsocio.com',
    'https://www.jetsocio.com',
    'https://jetsocio.pages.dev',
];

const RATE_LIMIT_PER_MINUTE = 10;

/* ---------- CORS 预检 ---------- */

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($origin, ALLOWED_ORIGINS, true)) {
        header('Access-Control-Allow-Origin: '.$origin);
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Max-Age: 86400');
        http_response_code(204);
        exit;
    }
    http_response_code(403);
    exit;
}

/* ---------- 方法约束 ---------- */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['error' => 'Method Not Allowed']);
}

/* ---------- CORS 实际请求 ----------
 * 浏览器跨域调用：仅放行 ALLOWED_ORIGINS。
 * 反向代理（Cloudflare Worker）走服务端 fetch，不带 Origin，直接放行（靠限流 + SSRF 防护兜底）。 */

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && ! in_array($origin, ALLOWED_ORIGINS, true)) {
    respond(403, ['error' => 'Origin not allowed']);
}
if ($origin !== '') {
    header('Access-Control-Allow-Origin: '.$origin);
}

/* ---------- 限流（无 DB 固定窗口，临时目录计数） ---------- */

if (! rateLimitPasses($_SERVER['REMOTE_ADDR'] ?? 'unknown')) {
    respond(429, ['error' => 'Too Many Requests']);
}

/* ---------- 输入 ---------- */

$input = json_decode((string) file_get_contents('php://input'), true);
$url = is_array($input) ? trim((string) ($input['url'] ?? '')) : '';
if ($url === '' || strlen($url) > 2048) {
    respond(422, ['error' => 'url is required']);
}

/* ---------- 执行审计 ---------- */

$engine = new AuditEngine(new CurlWebPageFetcher());
$result = $engine->run($url);

if ($result->error !== null) {
    respond(422, ['error' => $result->error]);
}

respond(200, ['success' => true, 'data' => $result->toArray()]);

/* ---------- helpers ---------- */

/** @param array<string, mixed> $payload */
function respond(int $status, array $payload): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function rateLimitPasses(string $ip): bool
{
    $dir = sys_get_temp_dir().'/audit-api';
    if (! is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    $window = intdiv(time(), 60);
    $key = md5($ip);
    $file = $dir.'/'.$key.'.'.$window;

    $count = is_file($file) ? ((int) file_get_contents($file)) + 1 : 1;
    file_put_contents($file, (string) $count, LOCK_EX);

    @unlink($dir.'/'.$key.'.'.($window - 1));

    return $count <= RATE_LIMIT_PER_MINUTE;
}
