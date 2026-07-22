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
Bearer token). Consulta blockchain via RPC (Ethereum, Solana) ou API REST
de indexador (Bitcoin, via Blockstream).
**Frontend**: React 19, Vite, React Router 7, Axios, Tailwind CSS.
**Infra**: Docker Compose (4 serviços: `app`, `scheduler`, `db`, `frontend`).
`scheduler` roda `php artisan schedule:work` — necessário para os
snapshots automáticos de histórico funcionarem (ver abaixo).

## Estrutura do repositório

```
crypto-wallet-monitor/
├── docker-compose.yml
├── docker/php/Dockerfile
├── src/                 # Laravel (backend)
│   ├── app/Http/Controllers/   AuthController, WalletController,
│   │                             WalletBalanceController, WalletHistoryController,
│   │                             PriceController
│   ├── app/Models/              User, Wallet, WalletBalanceHistory
│   ├── app/Services/Blockchain/ BlockchainServiceInterface, EthereumService,
│   │                             SolanaService, BitcoinService, BlockchainResolver
│   ├── app/Services/Market/     PriceService (cotações via CoinGecko)
│   ├── app/Services/Wallet/     BalanceHistoryRecorder (salva snapshot)
│   ├── app/Console/Commands/    CaptureWalletBalances (agendado de hora em hora)
│   ├── tests/Feature/           AuthTest, WalletTest, WalletBalanceTest,
│   │                             WalletHistoryControllerTest, EthereumServiceTest,
│   │                             SolanaServiceTest, BitcoinServiceTest, PriceServiceTest,
│   │                             PriceControllerTest, CaptureWalletBalancesCommandTest
│   └── routes/api.php, routes/console.php (agendamento)
└── frontend/             # React + Vite + Tailwind
    └── src/
        ├── context/AuthContext.jsx
        ├── components/  Layout, PrivateRoute, WalletForm, WalletList,
        │                 WalletItem, PricesPanel, PriceChangeBadge
        ├── pages/       Login, Register, Wallets, WalletHistory
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

### Backend — funcional e testado ponta a ponta (52 testes, `php artisan test`)
- `POST /api/register` (senha: mínimo 8 caracteres, letras e números via
  `Illuminate\Validation\Rules\Password`), `POST /api/login` (Sanctum,
  token Bearer)
- Rotas protegidas: `GET/POST /api/wallets`, `DELETE /api/wallets/{id}`,
  `GET /api/wallets/{id}/balance`
- **Multi-blockchain implementado**: Ethereum, Solana e Bitcoin, cada um
  com sua classe em `app/Services/Blockchain/` implementando
  `BlockchainServiceInterface` (`getBalance()`, `symbol()`,
  `addressPattern()`). `BlockchainResolver::resolve($network)` decide qual
  usar; `BlockchainResolver::supportedNetworks()` é a lista de redes
  aceitas. `WalletController` valida o endereço dinamicamente via
  `addressPattern()` da rede escolhida — **adicionar uma blockchain nova
  não exige mais alterar o `WalletController`**, só criar o Service e
  registrar no `BlockchainResolver`.
  - `EthereumService`: RPC (`eth_getBalance`), Wei→ETH via GMP/BCMath
  - `SolanaService`: RPC (`getBalance`), lamports→SOL via BCMath
  - `BitcoinService`: **não usa RPC** — usa a API REST do Blockstream
    (`blockstream.info/api/address/{address}`), porque um nó Bitcoin não
    expõe "saldo de um endereço" sem indexar todos os UTXOs
  - Todos cacheiam por 60s
- **Histórico de saldo implementado**: a tabela `wallet_balance_histories`
  agora tem coluna `price_usd` além de `balance`. `BalanceHistoryRecorder`
  salva um snapshot (`App\Services\Wallet\BalanceHistoryRecorder::capture()`)
  toda vez que `GET /api/wallets/{id}/balance` é chamado, **e** também
  automaticamente de hora em hora via o comando agendado
  `wallets:capture-balances` (registrado em `routes/console.php`,
  precisa do serviço `scheduler` do docker-compose rodando). O comando
  captura RPC/preço por wallet dentro de um try/catch — uma falha numa
  wallet não impede as outras.
  `GET /api/wallets/{id}/history?period=24h|7d|30d|all` retorna os pontos
  do período + um resumo (`current_value_usd`, `change_value_usd`,
  `change_percent`, `min_value_usd`, `max_value_usd`).
  **Debounce**: `BalanceHistoryRecorder` só grava um snapshot novo se o
  último da wallet tiver mais de 5 minutos — necessário porque o frontend
  agora atualiza o saldo sozinho a cada ~60s (ver abaixo), e sem esse
  limite cada wallet geraria um ponto de histórico por minuto só de
  alguém deixar a aba aberta.
- **Cotações**: `App\Services\Market\PriceService` consulta a API pública
  da CoinGecko (`/coins/markets`) e retorna, por rede suportada: preço USD,
  variação 24h/7d/30d, market cap, volume 24h e máxima/mínima 24h
  (reaproveita `BlockchainResolver::supportedNetworks()` como IDs da
  CoinGecko — eles coincidem hoje). Exposto em `GET /api/prices`, cacheado
  por 60s.

### Frontend — funcional e testado no navegador
- Tailwind CSS instalado (via `@tailwindcss/vite`); todas as telas já
  usam classes utilitárias, não inline styles
- `Layout.jsx` — cabeçalho + logout, envolve as páginas autenticadas
- Login e Register usam `AuthContext.login()` + `useNavigate()` (SPA, sem
  reload); logout funcional
- Interceptor de 401 ativo — sessão expirada desloga automaticamente
- `WalletForm` tem seletor de blockchain (Ethereum/Solana/Bitcoin), com
  placeholder e regex de validação client-side por rede, espelhando as
  regras do backend
- `PricesPanel.jsx` — painel com preço USD + variação de 24h das 3 moedas
  suportadas, sempre visível (independe de ter wallet cadastrada)
- Cada `WalletItem` mostra o valor da wallet em USD (saldo × preço) e a
  variação de 24h da moeda, assim que o saldo é consultado. Preços são
  buscados uma vez em `Wallets.jsx` e passados via props (evita chamadas
  duplicadas)
- **Auto-atualização**: saldo de cada wallet carrega sozinho ao abrir a
  tela (sem precisar clicar) e continua se atualizando a cada 60s
  (`setInterval` com cleanup no `useEffect` de `WalletItem`). O painel de
  cotações (`PricesPanel`) também se atualiza sozinho a cada 60s a partir
  de `Wallets.jsx`. O botão "Atualizar saldo" continua existindo para
  forçar uma atualização imediata.
- **Excluir wallet**: botão "Remover" em cada `WalletItem`, com
  confirmação **inline** no próprio card (não usa `window.confirm()` —
  esse diálogo nativo trava a aba e é inconsistente com o tema escuro;
  também trava ferramentas de automação de browser).
- **Tela de histórico** (`/wallets/:id/history`, `WalletHistory.jsx`):
  acessível pelo botão "Ver histórico" em cada `WalletItem`. Seletor de
  período (24h/7d/30d/tudo), cards de indicadores (saldo atual, valor
  atual, variação no período, mín/máx) e gráfico de linha (Recharts) do
  valor em USD ao longo do tempo. Mostra mensagem de "dados insuficientes"
  quando há menos de 2 pontos no período. Também mostra uma seção "Dados
  de mercado" (market cap, volume, máx/mín 24h, variação 24h/7d/30d) e o
  **widget oficial do TradingView** (`TradingViewChart.jsx`, carrega
  `s3.tradingview.com/tv.js` dinamicamente) com o gráfico de candles real
  da moeda (símbolo mapeado pra par da Binance, ex: `BINANCE:ETHUSDT`).
- **Valor total do portfólio** (`PortfolioSummary.jsx`): soma
  saldo × preço de todas as wallets, calculado no frontend a partir dos
  saldos que cada `WalletItem` já busca (reportados ao componente pai via
  `onBalanceLoaded`) e do `PricesPanel`. Aparece no topo de `Wallets.jsx`.

### Débitos técnicos conhecidos
- `frontend/src/config/networks.js` define cor/label de badge para
  `ethereum`, `bitcoin`, `solana` — todos já suportados pelo backend hoje.
  Ao adicionar uma rede nova (ex: Kaspa), é preciso atualizar esse arquivo
  **e** `WalletForm.jsx` (`ADDRESS_PATTERNS`/`NETWORK_OPTIONS`) além do
  backend.
- Setup Docker é só para desenvolvimento: backend roda `php artisan serve`
  (não é servidor de produção) e frontend roda `vite dev` (não é build
  estático). Precisa de imagem de produção antes do deploy (Fase 2).
- A chave de RPC paga (Ankr) que estava no `.env` local foi exposta em uma
  conversa — **rotacionar antes de ir para produção**. `.env` não está no
  git (só `.env.example`), então não há vazamento no repositório.
- `AuthContext.jsx` tem um aviso de lint (`react-refresh/only-export-components`)
  por exportar hook + componente no mesmo arquivo — pré-existente, não
  bloqueia nada, mas pode ser resolvido separando o hook em outro arquivo.
- `TradingViewChart.jsx` carrega um script de terceiro (`s3.tradingview.com/tv.js`)
  e mostra a marca "TradingView" no widget — é o widget oficial gratuito,
  não é branding nosso. Se algum dia quisermos um gráfico 100% no nosso
  tema/sem dependência externa, a alternativa é `lightweight-charts` (lib
  open-source do próprio TradingView) + dados de candle da CoinGecko.

## Roadmap (por fases, prioridade nessa ordem)

**Fase 1 — Endurecer o que existe** ✅ concluída (falta só rotacionar a
chave de RPC exposta, que é ação fora do código)

**Fase 1.5 — Multi-blockchain** ✅ concluída: Ethereum, Solana, Bitcoin
implementados e testados ponta a ponta contra as redes reais

**Fase 1.6 — Cotações (valorização/desvalorização)** ✅ concluída: preço
USD + variação 24h por moeda, painel geral + valor em USD por wallet

**Fase 1.7 — Histórico de saldo/valorização por período** ✅ concluída:
snapshot automático (agendado, de hora em hora) + manual (a cada consulta
de saldo), tela separada com indicadores e gráfico por período

**Decisão explícita do Wellington (2026-07-21)**: não seguir para infra de
produção/deploy agora. Prioridade é **deixar o sistema "redondo"
funcionalmente primeiro** — a Fase 2 (infra) abaixo foi empurrada pra
depois da Fase 3.

**Fase 1.8 — Auto-atualização + excluir wallet pela UI** ✅ concluída:
saldo/preço atualizam sozinhos (60s), botão de remover com confirmação
inline

**Fase 1.9 — Indicadores de portfólio + gráfico de mercado** ✅ concluída:
valor total do portfólio, dados de mercado (market cap/volume/máx-mín/
variação 7d/30d) e gráfico de candles real via widget do TradingView

**Fase 2 — Completar funcionalidades** (atual)
Ideias já levantadas: Kaspa (blockchain adicional) → alertas de
variação/movimentação. Perguntar ao Wellington a prioridade dentro desta
lista antes de escolher a próxima.

**Fase 3 — Infra de produção** (só quando ele pedir para ir a produção)
Docker de produção (nginx+php-fpm no backend, build estático no frontend)
→ HTTPS → segredos fora do repo → CI/CD → backups do Postgres → escolher
hospedagem

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
