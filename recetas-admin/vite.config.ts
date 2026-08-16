import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

const apiOrigin = process.env.RECETAS_API_ORIGIN ?? 'http://recetas-api:8080';

export default defineConfig({
  base: '/admin/',
  plugins: [react()],
  build: {
    rollupOptions: {
      output: {
        manualChunks(id) {
          if (!id.includes('node_modules')) return undefined;
          if (id.includes('/react/') || id.includes('/react-dom/') || id.includes('/react-router')) {
            return 'nucleo-react';
          }
          if (id.includes('/@mui/') || id.includes('/@emotion/')) {
            return 'interfaz-material';
          }
          if (id.includes('/ra-ui-materialui/')) {
            return 'componentes-administracion';
          }
          if (id.includes('/ra-core/')) {
            return 'nucleo-administracion';
          }
          if (id.includes('/react-admin/')) {
            return 'administracion';
          }
          return 'dependencias';
        },
      },
    },
  },
  server: {
    host: '0.0.0.0',
    proxy: {
      '/api': {
        target: apiOrigin,
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/api/, ''),
      },
      '/imagenes': {
        target: apiOrigin,
        changeOrigin: true,
      },
    },
  },
});
