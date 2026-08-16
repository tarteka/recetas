import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],

  server: {
    host: '0.0.0.0',

    // En Docker Desktop (Windows) los eventos de fs del bind mount no llegan
    // de forma fiable al inotify del contenedor; sin polling, Vite no detecta
    // los cambios guardados desde el host.
    watch: {
      usePolling: true,
      interval: 300,
    },

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
