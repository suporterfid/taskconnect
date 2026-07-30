import { fileURLToPath, URL } from 'node:url'
import tailwindcss from '@tailwindcss/vite'
import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vitest/config'

const apiProxyTarget =
  process.env.VITE_API_PROXY_TARGET ??
  (process.env.DOCKER === 'true' ? 'http://app:80' : 'http://localhost:8080')

export default defineConfig({
  plugins: [vue(), tailwindcss()],
  // Production deploys this app under the fixed /tc subpath (see
  // docs/deployment/ and the shared root .htaccess routing Jotter/TaskConnect/
  // GrandpaSSOn on one domain). Without the /tc prefix, the browser requests
  // /build/* which falls through the shared routing to a different app.
  base: '/tc/build/',
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  build: {
    outDir: '../public/build',
    emptyOutDir: true,
    manifest: true,
  },
  server: {
    host: '0.0.0.0',
    port: 5173,
    proxy: {
      '/api': {
        target: apiProxyTarget,
        changeOrigin: true,
      },
      '/sanctum': {
        target: apiProxyTarget,
        changeOrigin: true,
      },
    },
  },
  test: {
    environment: 'jsdom',
    globals: true,
    exclude: [
      '**/node_modules/**',
      '**/dist/**',
      '**/e2e/**',
      '**/.{idea,git,cache,output,temp}/**',
    ],
  },
})
