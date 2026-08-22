#!/bin/sh
# Railway 注入 $PORT；兜底 8080。显式打印便于排障（Railway 会捕获 stdout）。
PORT="${PORT:-8080}"
echo "[audit-api] starting PHP built-in server on 0.0.0.0:${PORT} (PID $$)"
exec php -S "0.0.0.0:${PORT}" -t /app
