# syntax=docker/dockerfile:1
FROM php:8.3-cli-bookworm
RUN --mount=type=secret,id=proxy_ca \
    if [ -f /run/secrets/proxy_ca ]; then export CURL_CA_BUNDLE=/run/secrets/proxy_ca; fi; \
    apt-get -o Acquire::https::CaInfo="${CURL_CA_BUNDLE:-/etc/ssl/certs/ca-certificates.crt}" update \
    && apt-get -o Acquire::https::CaInfo="${CURL_CA_BUNDLE:-/etc/ssl/certs/ca-certificates.crt}" install -y --no-install-recommends libcurl4-openssl-dev libonig-dev \
    && docker-php-ext-install curl mbstring \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY . .
RUN find /app -type d -exec chmod 755 {} \; \
    && find /app -type f -exec chmod 644 {} \; \
    && mkdir -p /app/storage && chown www-data:www-data /app/storage && chmod 700 /app/storage
USER www-data
EXPOSE 8000
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "public/router.php"]
