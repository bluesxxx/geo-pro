FROM php:8.4-cli

WORKDIR /app

# 审计引擎（与 GEO PRO 本地共用同一份代码，不重复安装）
COPY packages/audit-engine /app/vendor/audit-engine
COPY services/audit-api/index.php /app/index.php

EXPOSE 8080

# 监听 Railway 注入的 $PORT（默认 8080），避免硬编码端口导致连接/健康检查失败
CMD php -S 0.0.0.0:${PORT:-8080} -t /app
