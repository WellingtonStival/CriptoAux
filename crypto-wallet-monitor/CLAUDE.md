# Crypto Wallet Monitor — Contexto do Projeto

> Este arquivo existe para que qualquer agente de IA (Claude, Codex, etc.) ou
> desenvolvedor retome o projeto sem precisar reler todo o histórico de
> conversas. Mantenha-o atualizado conforme o projeto evolui — ele deve
> refletir o estado **real** do código, não um plano aspiracional.

## Objetivo do projeto

Sistema web para monitoramento de carteiras de criptomoedas. Não é um CRUD
simples — a meta é uma aplicação profissional, com:

- cadastro de carteiras públicas (somente leitura, nunca chave privada)
- consulta de saldo direto na blockchain
- suporte a múltiplas blockchains no futuro
- monitoramento de movimentações e alertas
- arquitetura escalável (SOLID, Services, baixo acoplamento)

Objetivo de negócio: levar o sistema para operação real, com usuários de
verdade — não é só um projeto de estudo, embora o desenvolvimento também
tenha propósito didático (veja "Como colaborar" abaixo).

## Stack

**Backend**: Laravel 12, PHP 8.4, PostgreSQL, Laravel Sanctum (auth via
Bearer token), JSON-RPC Ethereum.
**Frontend**: React 19, Vite, React Router 7, Axios.
**Infra**: Docker Compose (3 serviços: `app`, `db`, `frontend`).

## Estrutura do repositório

```
crypto-wallet-monitor/
├── docker-compose.yml
├── docker/php/Dockerfile
├── src/                 # Laravel (backend)
│   ├── app/Http/Controllers/   AuthController, WalletController, WalletBalanceController
│   ├── app/Models/              User, Wallet, WalletBalanceHistory
│   ├── app/Services/Blockchain/ BlockchainServiceInterface, EthereumService, BlockchainResolver
│   └── routes/api.php
└── frontend/             # React + Vite
    └── src/
        ├── context/AuthContext.jsx
        ├── components/  PrivateRoute, WalletForm, WalletList, WalletItem
        ├── pages/       Login, Wallets
        └── services/    api.js (axios + interceptors)
```

Só existe **um** frontend — o antigo (`C:\Projetos\frontend\frontend`) foi
removido e consolidado aqui no commit `7776fa9`. Não recrie essa duplicação.

## Como rodar localmente

```
docker compose up
```
Sobe backend em `localhost:8000`, frontend em `localhost:5173`, Postgres em
`localhost:5432`. O comando do container `app` já roda `composer install`
automaticamente.

**Cuidado (Windows + Docker)**: bind mounts no Windows não disparam eventos
nativos de filesystem, então o Vite não detecta mudanças em arquivo por
padrão. Isso já foi corrigido em `frontend/vite.config.js` com
`server.watch.usePolling: true` — não remova essa config.

## Estado atual (verificado, não assumido)

### Backend — funcional e testado ponta a ponta
- `POST /api/register`, `POST /api/login` (Sanctum, token Bearer)
- Rotas protegidas: `GET/POST /api/wallets`, `DELETE /api/wallets/{id}`,
  `GET /api/wallets/{id}/balance`
- `WalletController` valida endereço Ethereum via regex e restringe
  `network` a `ethereum` (único valor suportado hoje)
- `EthereumService` consulta RPC (`eth_getBalance`), converte Wei→ETH com
  GMP/BCMath (alta precisão), cacheia por 60s
- `WalletBalanceController` usa `BlockchainResolver` (não instancia
  `EthereumService` direto) — é assim que se adiciona uma nova blockchain
  sem alterar o controller: implementar `BlockchainServiceInterface` e
  registrar no `match()` do `BlockchainResolver`
- Tabela `wallet_balance_histories` existe (migration + model) mas
  **nada a popula ainda** — é trabalho futuro (Fase 3 do roadmap)

### Frontend — funcional e testado no navegador
- Login usa `AuthContext.login()` + `useNavigate()` (SPA, sem reload)
- Logout implementado (botão "Sair" em `Wallets.jsx`)
- Interceptor de 401 ativo — sessão expirada desloga automaticamente
- `WalletForm` cadastra carteira com validação client-side + tratamento de
  erro de validação do backend
- **Não existe tela de registro** — só login. Criar conta hoje exige
  chamar `POST /api/register` diretamente.

### Débitos técnicos conhecidos
- `src/app/Services/Blockchain/BlockchainFactory.php` é código morto,
  duplica `BlockchainResolver` (que é o realmente usado). Pode ser
  removido.
- `frontend/src/config/networks.js` lista `bitcoin` e `solana`, mas o
  backend só aceita `ethereum` — a UI promete o que a API rejeita.
- Sem testes automatizados reais (só o `ExampleTest` padrão do Laravel).
- Setup Docker é só para desenvolvimento: backend roda `php artisan serve`
  (não é servidor de produção) e frontend roda `vite dev` (não é build
  estático). Precisa de imagem de produção antes do deploy (Fase 2).
- A chave de RPC paga (Ankr) que estava no `.env` local foi exposta em uma
  conversa — **rotacionar antes de ir para produção**. `.env` não está no
  git (só `.env.example`), então não há vazamento no repositório.

## Roadmap (por fases, prioridade nessa ordem)

**Fase 1 — Endurecer o que existe**
tela de registro → política de senha → testes automatizados dos
controllers/services → rotacionar chave de RPC exposta

**Fase 2 — Infra de produção**
Docker de produção (nginx+php-fpm no backend, build estático no frontend)
→ HTTPS → segredos fora do repo → CI/CD → backups do Postgres → escolher
hospedagem

**Fase 3 — Completar o produto**
multi-blockchain (Bitcoin/Solana/Kaspa, via `BlockchainServiceInterface`)
→ job de histórico de saldo → atualização automática → alertas

**Fase 4 — Lançamento**
domínio, monitoramento de erro (ex: Sentry), teste com usuários reais

## Como colaborar neste projeto

- Wellington é **iniciante em React**, mas tem conhecimento **intermediário
  em Laravel**. Explicações no frontend devem ser mais didáticas (o porquê
  de hooks, contexto, etc.); no backend pode ser mais direto.
- **Antes de sugerir qualquer mudança estrutural** (arquitetura, Docker,
  autenticação, roteamento), leia o estado real dos arquivos envolvidos.
  Não assuma estrutura de tutorial — várias vezes o projeto já resolveu
  algo que parecia pendente (ex.: a duplicação de frontend já foi corrigida
  antes de virar tarefa).
- Não repita trabalho já feito nem peça para refazer algo que já está
  pronto — confira este arquivo e o `git log` primeiro.
- Prefira sessões focadas em um item do roadmap por vez, em vez de
  revisões amplas do zero — mantenha este arquivo atualizado para que isso
  seja possível.
- **Não faça varredura completa do código por padrão.** Antes de reler o
  projeto inteiro, verifique se este arquivo já responde a pergunta; se
  sim, prefira `git status`/`git diff` e leitura pontual dos arquivos
  relevantes à tarefa. Reserve revisão completa para quando for pedida
  explicitamente ou quando este arquivo estiver claramente desatualizado.
- **Sempre peça permissão antes de `git commit`/`git push`**, mesmo que
  uma ação anterior tenha sido aprovada na mesma sessão — cada commit/push
  precisa do próprio aval.
