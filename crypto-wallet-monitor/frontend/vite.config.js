import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const dirname = path.dirname(fileURLToPath(import.meta.url))

// https://vite.dev/config/
export default defineConfig({
  plugins: [react(), tailwindcss()],
  resolve: {
    alias: {
      '@': path.resolve(dirname, './src'),
    },
  },
  server: {
    // O Vite bloqueia por padrao requisicoes com um Host desconhecido
    // (protecao contra DNS rebinding). O tunel do cloudflared troca de
    // URL a cada execucao, entao nao da pra travar um host fixo aqui -
    // ok desligar essa checagem pra uma janela curta de teste exposta
    // de proposito, mas nao deixe isso como configuracao permanente de
    // producao.
    allowedHosts: true,
    watch: {
      // Bind mounts do Docker no Windows não emitem eventos de
      // filesystem nativos; sem isso o Vite não detecta alterações.
      usePolling: true,
    },
    proxy: {
      // Repassa /api pro backend (nome do serviço no docker-compose, nao
      // localhost - o proxy roda dentro do container do frontend). Assim
      // o navegador so precisa falar com a origem do Vite, o que permite
      // expor o app inteiro atras de um unico tunel/URL publica.
      '/api': {
        target: 'http://app:8000',
        changeOrigin: true,
      },
    },
  },
})
