# syntax=docker/dockerfile:1
# Immagine "web": build dell'interfaccia Vue + nginx che la serve e inoltra /api al backend PHP.
# Contesto di build: la cartella WebDev (radice del progetto).

ARG NODE_VERSION=22
ARG NGINX_VERSION=1.28

# --- Stage 1: build Vue ---------------------------------------------------
# Gira sempre sull'architettura della macchina che compila ($BUILDPLATFORM):
# l'output (HTML/JS/CSS) e' identico per ogni CPU, quindi niente emulazione lenta.
FROM --platform=$BUILDPLATFORM node:${NODE_VERSION}-alpine AS build
WORKDIR /app
COPY vue/frontend/package.json vue/frontend/package-lock.json* ./
RUN --mount=type=cache,target=/root/.npm \
    if [ -f package-lock.json ]; then npm ci; else npm install; fi
COPY vue/config /config
COPY vue/frontend/ ./
RUN npm run build

# --- Stage 2: runtime nginx -----------------------------------------------
FROM nginx:${NGINX_VERSION}-alpine
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html
EXPOSE 80
HEALTHCHECK --interval=30s --timeout=3s --retries=3 \
  CMD wget -qO- http://127.0.0.1/healthz || exit 1
