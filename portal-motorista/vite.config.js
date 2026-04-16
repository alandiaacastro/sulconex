import { defineConfig, loadEnv } from 'vite';
import react from '@vitejs/plugin-react';

function normalizePathSegment(value, fallback = '') {
  const cleaned = String(value || fallback || '').trim();
  if (!cleaned || cleaned === '/') {
    return fallback === '/' ? '/' : '';
  }

  return `/${cleaned.replace(/^\/+|\/+$/g, '')}`;
}

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '');
  const phpOrigin = String(env.VITE_PHP_APP_ORIGIN || 'http://127.0.0.1').trim().replace(/\/+$/, '');
  const phpBasePath = normalizePathSegment(env.VITE_PHP_APP_BASE_PATH, '/sulconex81');
  const apiBase = normalizePathSegment(env.VITE_PORTAL_API_BASE, '/api/portal-motorista');

  return {
    plugins: [react()],
    server: {
      host: '127.0.0.1',
      port: 4173,
      strictPort: true,
      proxy: {
        [apiBase]: {
          target: phpOrigin,
          changeOrigin: true,
          secure: false,
          rewrite: (path) => `${phpBasePath}${path}`,
        },
      },
    },
    build: {
      outDir: 'dist',
      emptyOutDir: true,
      manifest: 'manifest.json',
      sourcemap: false,
    },
    test: {
      environment: 'node',
      include: ['src/**/*.test.js'],
    },
  };
});
