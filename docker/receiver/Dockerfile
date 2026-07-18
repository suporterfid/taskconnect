FROM node:22-bookworm-slim

WORKDIR /app

COPY docker/receiver/server.js ./server.js

ENV PORT=8080

EXPOSE 8080

CMD ["node", "server.js"]
