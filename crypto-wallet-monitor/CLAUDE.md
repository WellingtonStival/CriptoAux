# Nexfolio — Contexto do Projeto

> Nome do sistema até 2026-07-22: "Crypto Wallet Monitor" (renomeado para
> Nexfolio — se encontrar esse nome antigo em commits/telas antigas, é o
> mesmo projeto).

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

**Escopo explícito (Wellington, 2026-07-22): é um monitor, não uma
exchange.** Sem compra/venda, sem book de ofertas, nada de trading. O
objetivo é acompanhar valorização/desvalorização das moedas que a pessoa
já tem, calculada a partir de quando a wallet foi cadastrada no sistema —
**não** é necessário rastrear o preço de aquisição/custo de compra
retroativo (ideia de "aporte" foi levantada e descartada por ora).

## Direção estratégica e como atuar neste projeto (Wellington, 2026-07-22)

A ambição do produto: evoluir de "monitor" para uma **plataforma
profissional de gestão de patrimônio em cripto (SaaS)**, pensando em
milhares de usuários. Isso **não contradiz** o "não é exchange" acima —
produtos como CoinStats/Delta/Zerion são exatamente isso: monitoramento e
analytics sofisticados, sem trading/custódia, e ainda assim servem
milhões de usuários. O que muda é o nível de exigência em arquitetura,
segurança, performance e UX — não o escopo funcional (continua sem
compra/venda).

**Qualquer agente (Claude, Codex, etc.) trabalhando neste repo deve atuar
como CTO + Arquiteto de Software + PM + Engenheiro Sênior**, com estas
regras de condução:

- Priorizar sempre nesta ordem: **confiabilidade → performance →
  arquitetura → UX → novas funcionalidades**. Não empilhar feature nova
  em cima de base frágil.
- **Não concordar automaticamente** com as ideias do Wellington. Fazer
  análise crítica, propor alternativas quando fizer mais sentido, e
  explicar o impacto técnico *e* de negócio antes de implementar.
- Quando ele pedir uma implementação: **primeiro** apresentar a solução
  de arquitetura + plano de execução + impactos/riscos, **só depois**
  partir pro código. (Isso não substitui o hábito já existente de
  explicar antes de mudança estrutural — reforça e formaliza.)
- Organizar sugestões de roadmap por prioridade e impacto, não por ordem
  de chegada.

Áreas de funcionalidade futura que ele quer ver avaliadas (não
implementar direto — discutir prioridade/abordagem antes): dashboard
consolidado de patrimônio, evolução patrimonial histórica, análise de
risco/concentração de carteira, alertas inteligentes (movimentação,
saldo, preço), integração com múltiplas blockchains/exchanges (read-only),
suporte a tokens e NFTs, notificações (email/Telegram/Discord/push),
insights com IA, análise de security approvals (aprovações de contrato
arriscadas), métricas de performance, dashboards financeiros, e recursos
premium para um futuro modelo de cobrança SaaS.

## Stack

**Backend**: Laravel 12, PHP 8.4, PostgreSQL, Laravel Sanctum (auth via
Bearer token), `resend/resend-php` (recuperação de senha por email).
Consulta blockchain via RPC (Ethereum, Solana) ou API REST de indexador
(Bitcoin, via Blockstream).
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
│   ├── app/Http/Controllers/   AuthController, PasswordResetController,
│   │                             WalletController, WalletBalanceController,
│   │                             WalletHistoryController, WalletTransactionController,
│   │                             PriceController, PortfolioController
│   ├── app/Models/              User, Wallet, WalletBalanceHistory
│   ├── app/Notifications/       ResetPasswordNotification (linka pro frontend)
│   ├── app/Services/Blockchain/ BlockchainServiceInterface, TransactionHistoryProvider,
│   │                             EthereumService, SolanaService, BitcoinService,
│   │                             BlockchainResolver
│   ├── app/Services/Market/     PriceService (cotações via CoinGecko)
│   ├── app/Services/Wallet/     BalanceHistoryRecorder (salva snapshot)
│   ├── app/Console/Commands/    CaptureWalletBalances (agendado de hora em hora)
│   ├── tests/Feature/           AuthTest, PasswordResetTest, WalletTest,
│   │                             WalletBalanceTest, WalletHistoryControllerTest,
│   │                             WalletTransactionControllerTest, EthereumServiceTest,
│   │                             SolanaServiceTest, BitcoinServiceTest, PriceServiceTest,
│   │                             PriceControllerTest, CaptureWalletBalancesCommandTest,
│   │                             BalanceHistoryRecorderTest, PortfolioControllerTest
│   └── routes/api.php, routes/console.php (agendamento)
└── frontend/             # React + Vite + Tailwind
    └── src/
        ├── context/AuthContext.jsx
        ├── components/  Layout (com navegação Dashboard/Wallets), PrivateRoute,
        │                 WalletForm, WalletList, WalletItem, PricesPanel,
        │                 PriceChangeBadge, TradingViewChart, TransactionList
        ├── pages/       Login, Register, ForgotPassword, ResetPassword,
        │                 Dashboard (tela inicial "/"), Wallets ("/wallets"),
        │                 WalletHistory
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

### Backend — funcional e testado ponta a ponta (89 testes, `php artisan test`)
- `POST /api/register` (senha: mínimo 8 caracteres, letras e números via
  `Illuminate\Validation\Rules\Password`), `POST /api/login` (Sanctum,
  token Bearer)
- **Recuperação de senha por email**: `POST /api/forgot-password` (sempre
  retorna a mesma mensagem, exista ou não o email, pra não vazar quais
  emails estão cadastrados) e `POST /api/reset-password` (token + email +
  nova senha). `User::sendPasswordResetNotification()` foi sobrescrito
  para linkar pro frontend (`/redefinir-senha?token=...&email=...`) em vez
  da rota web padrão do Laravel, que não existe nesta API.
  **Envio real configurado e testado** — `MAIL_MAILER=resend` com
  `RESEND_API_KEY` no `.env` local (não commitado). `MAIL_FROM_ADDRESS` é
  `onboarding@resend.dev` (remetente de teste da Resend, funciona sem
  verificar domínio, mas só entrega pro email cadastrado na conta Resend
  até um domínio próprio ser verificado — modo sandbox deles). Testado
  ponta a ponta em 2026-07-22: email chegou de verdade na caixa de entrada
  do Wellington e a senha foi redefinida com sucesso.
- Rotas protegidas: `GET/POST /api/wallets`, `PATCH /api/wallets/{id}`
  (renomear), `DELETE /api/wallets/{id}`, `GET /api/wallets/{id}/balance`
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
- **Histórico de transações (Solana e Bitcoin apenas)**: interface
  `TransactionHistoryProvider` (separada de `BlockchainServiceInterface`,
  só `SolanaService` e `BitcoinService` implementam — Ethereum ainda não,
  porque a RPC pública usada para saldo não lista transações; exigiria uma
  API indexadora tipo Etherscan, que pede chave de API — decisão adiada).
  `GET /api/wallets/{id}/transactions` retorna `supported: false` e lista
  vazia para redes sem suporte (hoje só Ethereum), ou a lista de
  transações (hash, direção in/out, valor, timestamp, link pro explorer)
  para Solana/Bitcoin. Solana faz `getSignaturesForAddress` + uma chamada
  `getTransaction` por assinatura (calcula a variação de saldo da própria
  conta); Bitcoin usa `/address/{address}/txs` do Blockstream (soma
  vout/vin do endereço, cobrindo troco corretamente).
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
- **Nome da wallet**: coluna `name` (nullable) em `wallets`. Opcional no
  `POST /api/wallets` e editável via `PATCH /api/wallets/{id}` (só o nome
  — endereço/rede não são editáveis, trocar isso seria cadastrar outra
  wallet).
- **Cotações**: `App\Services\Market\PriceService` consulta a API pública
  da CoinGecko (`/coins/markets`) e retorna, por rede suportada: preço USD,
  variação 24h/7d/30d, market cap, volume 24h e máxima/mínima 24h
  (reaproveita `BlockchainResolver::supportedNetworks()` como IDs da
  CoinGecko — eles coincidem hoje). Exposto em `GET /api/prices`, cacheado
  por 60s.
- **Patrimônio consolidado**: `GET /api/portfolio/history?period=...`
  (`PortfolioController`) agrega `wallet_balance_histories` de **todas as
  wallets do usuário** num único valor por período, mais a distribuição
  atual por rede (`allocation`). Como wallets não são capturadas no exato
  mesmo instante, os snapshots são agrupados em "baldes" de tempo (hora
  pra 24h/7d, dia pra 30d) e, dentro de cada balde, só o snapshot mais
  recente de cada wallet conta (evita contar duas vezes). Alocação usa o
  último snapshot de cada wallet, independente do período do gráfico.
  Feito em PHP/Collections (não SQL bruto) para ficar simples de
  ler/testar — se o volume de dados crescer muito (milhares de usuários),
  isso é candidato a virar uma agregação no banco ou cache.
  Também retorna `concentration` (por rede e por wallet): índice
  Herfindahl-Hirschman (HHI, 0-10000) + nível (`diversificado` <1500,
  `moderado` 1500-2500, `concentrado` >2500) + maior posição. Chamado de
  "concentração", não "risco" — é um fato objetivo sobre a distribuição
  do patrimônio, não uma recomendação de investimento (o produto não dá
  conselho financeiro).
- **Notícias**: `App\Services\News\NewsService` agrega **RSS** de 3 fontes
  conhecidas (CoinDesk, Cointelegraph, Decrypt) — **não** usa a API da
  CryptoPanic (o plano gratuito deles foi descontinuado em 2026-07-22,
  antes de chegarmos a configurar; o mais barato ficou em US$50/semana,
  desproporcional pro estágio atual do projeto). RSS não precisa de chave
  nem conta — padrão web estável, sem risco de "free tier" ser cortado.
  Como RSS não tem marcação nativa por moeda, cada notícia é marcada por
  uma heurística simples (contém "bitcoin"/"btc" no título+resumo? etc.)
  — não é 100% precisa, uma notícia pode ficar sem marcação ou com mais
  de uma. `GET /api/news?network=...` (network opcional; 422 se a rede
  não for suportada). Uma fonte RSS fora do ar não derruba as outras (só
  é ignorada silenciosamente).
  **Cache global** (`crypto_news:all`, 10 min, sempre busca as 3 fontes
  juntas e filtra em memória por rede) — não é por-usuário, porque
  notícia é o mesmo conteúdo pra todo mundo. Isso desacopla o número de
  requisições aos feeds da quantidade de usuários do sistema — ponto
  chave pra "escalável". Cada item já traz `summary` (resumo/descrição
  do RSS, sem tags HTML).
- **Tradução de notícias**: `App\Services\Translation\TranslationService`
  traduz título + resumo de todas as notícias pra PT-BR **numa única
  chamada** à API da DeepL (não uma por notícia), disparada dentro do
  mesmo ciclo de cache do `NewsService` (a cada 10 min, não por
  requisição). Corpo da requisição é montado manualmente com `text=`
  repetido (em vez de deixar o cliente HTTP serializar o array como
  `text[0]=`), porque não há garantia de que a DeepL reconheça a notação
  com colchetes do PHP. **Sem `DEEPL_API_KEY` configurada, ou se a
  chamada falhar por qualquer motivo, retorna os textos originais em
  inglês sem quebrar a tela** — degrada graciosamente.
  **Gotcha da DeepL**: autenticação via `auth_key` no corpo da requisição
  foi descontinuada em nov/2025 — a API responde 403 "Legacy
  authentication method 'form body' is no longer supported". É
  obrigatório enviar a chave no header `Authorization: DeepL-Auth-Key
  {chave}` (já implementado). ✅ **Verificado funcionando de ponta a
  ponta em 2026-07-22**: `DEEPL_API_KEY` configurada no `.env` local,
  `/api/news` retorna título e resumo já traduzidos pra PT-BR, conferido
  também na tela `/noticias` no navegador.

### Frontend — funcional e testado no navegador
- Tailwind CSS instalado (via `@tailwindcss/vite`); todas as telas já
  usam classes utilitárias, não inline styles
- **Fundação de UI (2026-07-22)**: `frontend/src/components/ui/` tem
  componentes no estilo shadcn/ui — Button, Card, Badge, Alert, Skeleton,
  Tabs — copiados como código-fonte pro repo (não é dependência de pacote
  opaca; dá pra ler/editar cada um), construídos sobre Tailwind v4 +
  Radix UI (`@radix-ui/react-tabs`, `@radix-ui/react-slot`) +
  `class-variance-authority` (variantes) + `clsx`/`tailwind-merge`
  (helper `cn()` em `frontend/src/lib/utils.js`). Ícones via
  `lucide-react`. Paleta/tokens de cor centralizados em
  `frontend/src/index.css` (bloco `@theme` do Tailwind v4 — `--color-*`
  vira variável CSS de verdade em `:root`, por isso dá pra usar
  `var(--color-primary)` etc. diretamente no Recharts). Alias `@/` pro
  `src/` configurado em `vite.config.js` (ainda não usado nos imports
  atuais, que são relativos — disponível pra próximos componentes).
  ✅ **Rollout completo (2026-07-22)**: todas as telas migraram —
  Dashboard, `Wallets.jsx` (+ `WalletForm`, `WalletItem`, `WalletList`
  em grid responsivo 1/2/3 colunas), `WalletHistory.jsx` (+
  `TransactionList`, `TradingViewChart`), `News.jsx`, e as 4 telas
  públicas de auth (`Login`, `Register`, `ForgotPassword`,
  `ResetPassword`) via um `AuthLayout.jsx` novo (card centralizado com
  a marca Nexfolio, evita repetir o mesmo markup 4x). `StatCard.jsx`
  virou componente compartilhado (usado no Dashboard e no
  WalletHistory). Novos componentes de formulário em `ui/`: `Input`,
  `Label`, `Select` (wrapper estilizado do `<select>` nativo — não usa
  Radix Select, simplicidade > customização nesse caso). Testado no
  navegador tela por tela, lint limpo.
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
- **Dashboard consolidado** (`Dashboard.jsx`, `/` — **tela inicial** do
  app): indicadores (valor atual, variação no período, mín/máx), gráfico
  de evolução do patrimônio total (soma de todas as wallets) e
  distribuição por moeda (barras coloridas por rede), tudo a partir de
  `GET /api/portfolio/history`. Também mostra o `PricesPanel` (cotações
  gerais). `Wallets.jsx` deixou de ser a tela inicial — virou `/wallets`,
  tela de **gerenciamento** (cadastrar/renomear/remover), sem os
  indicadores agregados (esses ficaram só no Dashboard). Também mostra o
  card "Concentração" (por moeda e por wallet, com nível colorido).
  `Layout.jsx` ganhou navegação entre as telas ("Dashboard" / "Minhas
  Wallets" / "Notícias").
- **Notícias** (`News.jsx`, `/noticias`): filtro por moeda (Todas/
  Ethereum/Solana/Bitcoin), lista de notícias com resumo (o que o próprio
  RSS já fornece, nunca o artigo completo) e link pra fonte original.
  Badge de moeda usa `item.currencies` (chaves de rede: `ethereum`,
  `bitcoin`, `solana` — não tickers como `ETH`/`BTC`) pra buscar cor/
  símbolo em `NETWORKS`. Funcional com dados reais (RSS, sem chave
  necessária) — testado ponta a ponta em 2026-07-22. Título/resumo já
  aparecem traduzidos em PT-BR (DeepL configurada, ver backend acima).
- **Lista de transações** (`TransactionList.jsx`, dentro da tela de
  histórico): mostra as últimas transações da wallet (direção, valor,
  data, link pro explorer). Para Ethereum, mostra aviso de "ainda não
  disponível" em vez de lista vazia. Formatadores de data/moeda
  compartilhados em `frontend/src/utils/format.js`.
- **Nome da wallet**: campo opcional "Nome" no `WalletForm` ao cadastrar;
  edição inline (ícone de lápis) em cada `WalletItem`, mesmo padrão visual
  da confirmação de exclusão (sem `window.prompt()`/modal nativo).
- **Recuperação de senha**: link "Esqueci minha senha" no Login →
  `ForgotPassword.jsx` (`/esqueci-senha`) → `ResetPassword.jsx`
  (`/redefinir-senha`, lê `token`/`email` da URL via `useSearchParams`).

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

**Fase 2.0 — Feed de transações (Solana e Bitcoin)** ✅ concluída: últimas
transações por wallet, com direção/valor/data/link pro explorer. Ethereum
fica pendente (precisa decidir sobre usar a API do Etherscan, que exige
chave)

**Fase 2.1 — Nome da wallet + recuperação de senha** ✅ concluída, incluindo
envio real de email via Resend (testado ponta a ponta)

**Fase 2.2 — Dashboard consolidado de patrimônio** ✅ concluída: virou a
tela inicial, com evolução agregada de todas as wallets e distribuição
por moeda. `Wallets.jsx` passou a ser a tela de gerenciamento (`/wallets`)

**Fase 2.3 — Concentração de patrimônio** ✅ concluída: índice HHI por
rede e por wallet, com nível (diversificado/moderado/concentrado) e
maior posição, no Dashboard

**Fase 2.4 — Notícias por moeda + resumo + tradução** ✅ concluída:
agregação de RSS (CoinDesk, Cointelegraph, Decrypt), sem chave de API
(CryptoPanic descontinuou o plano gratuito antes de configurarmos —
pivotamos pra RSS), resumo de cada notícia, tradução pra PT-BR via DeepL
(numa única chamada por ciclo de cache, autenticação por header). Testado
ponta a ponta com chave real em 2026-07-22.

**Fase 2.5 — Redesign visual (fundação + rollout completo)** ✅
concluída: componentes shadcn/ui-style, tokens de tema, ícones
lucide-react, e todas as telas (autenticadas e públicas) já usam o novo
visual. Ver detalhes na seção Frontend acima.

**Fase 2 — Completar funcionalidades** (atual)
Ideias já levantadas: feed de transações do Ethereum (via Etherscan, pede
chave de API) → Kaspa (blockchain adicional) → alertas de
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
