FROM php:8.3-cli

WORKDIR /app
COPY index.php /app/index.php
RUN mkdir -p /app/data

# چند Worker تا یک درخواست طولانی (مثلاً ساخت عکس) بقیه را معطل نکند
ENV PHP_CLI_SERVER_WORKERS=4

CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-10000} index.php"]
