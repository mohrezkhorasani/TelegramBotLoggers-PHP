FROM php:8.3-cli-alpine

# Install curl and openssl extensions (bundled by default)
RUN apk add --no-cache curl ca-certificates

WORKDIR /app

# Copy application files
COPY . /app

# Ensure storage directory exists and has write permissions
RUN mkdir -p /app/storage && chmod -R 777 /app/storage

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
