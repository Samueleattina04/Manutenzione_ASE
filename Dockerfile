FROM node:22-slim

# better-sqlite3 ships prebuilt binaries; build tools are a fallback.
WORKDIR /app

COPY package*.json ./
RUN npm install --omit=dev

COPY . .

# Persistent data (database + uploaded photos) lives here.
ENV DATA_DIR=/data
VOLUME ["/data"]

ENV NODE_ENV=production
ENV PORT=3000
EXPOSE 3000

CMD ["node", "src/server.js"]
