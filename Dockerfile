FROM python:3.12-slim

ENV PYTHONDONTWRITEBYTECODE=1
ENV PYTHONUNBUFFERED=1
ENV PIP_NO_CACHE_DIR=1

WORKDIR /app

# PHP + required utilities
RUN apt-get update && \
    apt-get install -y --no-install-recommends \
        php-cli \
        php-curl \
        php-mbstring \
        php-xml \
        php-zip \
        php-json \
        unzip \
        git \
        procps \
        ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# Python dependencies
COPY requirements.txt .

RUN pip install --no-cache-dir \
    fastapi \
    "uvicorn[standard]" \
    python-multipart \
    python-dotenv \
    aiofiles \
    "python-telegram-bot==22.5"

# Copy project
COPY . .

# Storage
RUN mkdir -p \
    /app/storage/uploads \
    /app/storage/projects \
    /app/storage/logs

# Render uses PORT
EXPOSE 10000

# API starts Telegram bot automatically
CMD ["python", "api.py"]
