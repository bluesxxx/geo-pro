FROM php:8.4-cli

WORKDIR /app

# 审计引擎（与 GEO PRO 本地共用同一份代码，不重复安装）
COPY packages/audit-engine /app/vendor/audit-engine
COPY services/audit-api/index.php /app/index.php
COPY services/audit-api/start.sh /app/start.sh
RUN chmod +x /app/start.sh

EXPOSE 8080

# 监听 Railway 注入的 $PORT（默认 8080）。start.sh 显式读取并打印，避免 shell 展开差异导致端口不匹配。
CMD ["/app/start.sh"]
