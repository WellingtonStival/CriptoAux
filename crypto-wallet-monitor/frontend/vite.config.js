import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    watch: {
      // Bind mounts do Docker no Windows não emitem eventos de
      // filesystem nativos; sem isso o Vite não detecta alterações.
      usePolling: true,
    },
  },
})
