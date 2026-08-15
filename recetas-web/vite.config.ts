import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],

  server: {
    host: '0.0.0.0',

    proxy: {
      '/api': {
        target: 'http://recetas-api:8080',
        changeOrigin: true,

        // El navegador pide /api/recetas y Slim recibe /recetas.
        rewrite: (path) => path.replace(/^\/api/, ''),
      },

      '/imagenes': {
        target: 'http://recetas-api:8080',
        changeOrigin: true,
      },
    },
  },
});
