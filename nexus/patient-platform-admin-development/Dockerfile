# Build stage
FROM node:22-bookworm AS builder
WORKDIR /app

COPY ../package*.json ./
RUN npm ci

COPY ../* .
RUN npm run build
RUN npm prune --omit=dev

# Runtime stage
FROM node:22-bookworm-slim
WORKDIR /app

ENV NODE_ENV=production

COPY --from=builder /app/package*.json ./
COPY --from=builder /app/node_modules ./node_modules
COPY --from=builder /app/dist ./dist

USER node
CMD ["node", "dist/index.js"]