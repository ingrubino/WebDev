import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'

// In sviluppo (npm run dev sul Mac) le chiamate /api vengono inoltrate allo stack Docker
// avviato da ../../docker (nginx su localhost:8080), così come /mqtt (WebSocket verso il broker MQTT).
// Cambiare con VITE_API_TARGET=http://host:porta.
export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
      '@config': fileURLToPath(new URL('../config', import.meta.url)),
    },
  },
  server: {
    host: true,
    fs: { allow: ['..'] },
    proxy: {
      '/api': process.env.VITE_API_TARGET || 'http://localhost:8080',
      '/mqtt': { target: process.env.VITE_API_TARGET || 'http://localhost:8080', ws: true },
    },
  },
})
