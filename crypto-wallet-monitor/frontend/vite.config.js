import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    watch: {
      // Bind mounts do Docker no Windows não emitem eventos de
      // filesystem nativos; sem isso o Vite não detecta alterações.
      usePolling: true,
    },
  },
})
