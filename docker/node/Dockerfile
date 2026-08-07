FROM mcr.microsoft.com/playwright:v1.61.1-noble

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0", "--port", "5173"]
