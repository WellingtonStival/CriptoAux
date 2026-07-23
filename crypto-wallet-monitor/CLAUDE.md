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
│   │                             AbstractEvmChainService (logica EVM
│   │                             compartilhada), EthereumService,
│   │                             PolygonService, BnbService, SolanaService,
│   │                             BitcoinService, BlockchainResolver
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
`localhost:5432`, Redis em `localhost:6379` (container `redis`, sem porta
exposta pro host — só a rede interna do Compose usa). O comando do
container `app` já roda `composer install` automaticamente. Serviços
`scheduler` (captura de saldo agendada) e `queue` (worker da fila —
processa `RefreshWalletBalance`) precisam estar rodando pro sistema
funcionar por completo; `docker compose up` já sobe os dois.

**Cuidado (Windows + Docker)**: bind mounts no Windows não disparam eventos
nativos de filesystem, então o Vite não detecta mudanças em arquivo por
padrão. Isso já foi corrigido em `frontend/vite.config.js` com
`server.watch.usePolling: true` — não remova essa config.

## Estado atual (verificado, não assumido)

### Backend — funcional e testado ponta a ponta (147 testes, `php artisan test`)
- **Suporte a tokens/ativos (2026-07-23)**: além do saldo nativo, o
  sistema agora rastreia tokens dentro de uma wallet (ERC-20 no
  Ethereum, SPL na Solana — Bitcoin não suporta). Duas tabelas novas:
  `wallet_tokens` (quais tokens cada wallet rastreia) e
  `wallet_token_balance_histories` (snapshot de saldo/preço por token,
  coluna `balance` em `decimal(50,18)`). Nova interface
  `TokenDiscoveryProvider` (`discoverTokens()` + `getTokenBalance()`),
  implementada por `EthereumService` e `SolanaService`.
  **Ethereum** usa a Alchemy (Token API, `alchemy_getTokenBalances` em
  modo `erc20` pra descoberta automática + `alchemy_getTokenMetadata`
  por token pra símbolo/nome/decimais) — precisa de `ALCHEMY_API_KEY`
  no `.env`, senão a descoberta simplesmente não acha nada (degrada
  sem quebrar). ⚠️ **Decisão verificada, não presumida**: a princípio
  cogitei a Etherscan pra isso, mas o endpoint que lista todos os
  tokens de um endereço (`addresstokenbalance`) é **PRO, não está no
  plano gratuito** — descobri isso lendo a documentação deles antes de
  implementar, e troquei pra Alchemy (Token API disponível no plano
  gratuito, 30M unidades/mês, essa chamada custa 20). Atualizar saldo
  de um token já conhecido (`getTokenBalance`) usa `eth_call` na RPC
  normal, não a Alchemy — só a descoberta inicial consome a cota dela.
  ✅ **Testado ao vivo em 2026-07-23** com `ALCHEMY_API_KEY` real
  configurada: descobriu 53 tokens legítimos numa wallet Ethereum real
  (o resto foi filtrado como spam, ver abaixo).
  **Solana** não precisa de fornecedor terceiro pra descobrir *quais*
  tokens a wallet tem: a própria RPC (`getTokenAccountsByOwner`, mesmo
  endpoint já usado pra saldo) lista todos nativamente, incluindo
  decimais. Pra *nome/símbolo*, `SolanaService::fetchTokenMetadata()`
  faz uma chamada em lote (até 100 mints por request, por isso o
  `array_chunk`) pra API pública e **gratuita, sem chave**,
  `lite-api.jup.ag/tokens/v2/search` da Jupiter — diferente da
  `api.jup.ag/tokens/v2/search` (que passou a exigir `x-api-key`), a
  variante `lite-api.jup.ag` continua aberta. ⚠️ **Verificado ao vivo
  antes de implementar** (`curl` direto, sem chave, HTTP 200) — mesmo
  hábito de checar fornecedor antes de codar. Se a chamada falhar,
  symbol/name ficam `null` e o frontend cai pro endereço truncado
  (nunca quebra a listagem de saldos, que não depende dela). Testado
  ao vivo: **107 de 148 tokens** de uma wallet Solana real ganharam
  nome/símbolo reconhecido pela Jupiter (o resto é token não listado
  lá — normalmente é spam mesmo).
  **Filtro anti-spam (`WalletTokenController::SPAM_BALANCE_THRESHOLD`,
  1e15)**: tokens de spam/scam mintam saldos nominais próximos do
  limite do `uint256` (ex: `1.15e59`) só pra aparecer com destaque em
  exploradores — isso não só polui a lista como **estourava a coluna
  `balance`** mesmo depois de alargada pra `decimal(50,18)` (bug real,
  pego em produção local: `SQLSTATE[22003]: numeric field overflow`).
  A correção definitiva não foi alargar a coluna de novo (perseguir
  esse número é uma corrida perdida — um contrato malicioso pode
  devolver qualquer valor até `~1.15e77`), foi filtrar no
  `WalletTokenController::sync()` qualquer token com saldo nominal
  acima do limiar **antes de persistir**, logando um aviso. Também
  resolve parte do débito técnico de poluição por spam citado antes.
  Preço dos tokens vem da CoinGecko (`/simple/token_price/{platform}`,
  mesmo fornecedor já usado pra preço das moedas nativas — sem vendor
  novo pra isso). Endpoints: `POST /wallets/{id}/tokens/sync`
  (descoberta ao vivo, ação mais pesada — só roda quando o usuário
  clica "Buscar tokens", não a cada carregamento de tela),
  `GET /wallets/{id}/tokens` (lê o que já foi sincronizado, sem tocar
  a blockchain), `DELETE /wallets/{id}/tokens/{tokenId}` (parar de
  rastrear), `GET /assets` (visão consolidada — soma o mesmo token em
  todas as wallets do usuário, agrupado por rede+contrato).
  Cada token também guarda `logo_url` (vem do campo `logo` da
  `alchemy_getTokenMetadata` no Ethereum/Polygon/BNB, ou do `icon` da
  Jupiter na Solana) — usado pra mostrar o ícone real na tela Ativos.
  ⚠️ **Bug real pego ao testar**: `WalletToken::$fillable` não incluía
  `logo_url` — o `updateOrCreate()` descartava o campo silenciosamente
  (mass assignment protection do Eloquent), então nenhum logo era
  salvo mesmo a API devolvendo o dado certo. Só apareceu porque testei
  ao vivo e conferi a coluna no banco, não só a resposta da API.
- **Preço de token limitado sem chave de API da CoinGecko (2026-07-23)**:
  descoberto ao vivo (não hipótese) que `/simple/token_price/{platform}`
  **sem** chave de API aceita só **1 endereço de contrato por
  requisição** — a partir do segundo, responde 400 "Number of contract
  addresses in the request exceeds the allowed limit of 1". Como
  `WalletTokenController::sync()` manda todos os contratos descobertos
  de uma vez, isso zerava o preço de praticamente todo mundo (confirmado:
  373 tokens sincronizados, 0 com preço). `PriceService` ganhou uma
  chave opcional e gratuita (`COINGECKO_API_KEY`, plano "Demo" da
  CoinGecko, sem cartão — header `x-cg-demo-api-key`), que sobe o limite
  documentado pra até 515 endereços por chamada; `tokenPrices()` agora
  agrupa em lotes de 100 quando a chave existe, ou 1 por vez (mais
  lento, sujeito a rate limit) sem ela — nunca quebra, só degrada.
  ✅ **Chave configurada e testada ao vivo em 2026-07-23** (plano Demo
  gratuito): re-sincronizando as wallets de teste (Ethereum, Polygon,
  BNB), o número de tokens com preço saltou de 0 pra 25 de 373, valor
  total consolidado de $4.182,01 exibido corretamente na tela Ativos,
  ordenado por valor, com logos reais (ex: CRV, WMATIC).
- **Filtro anti-spam v2 / débito técnico revisitado**: o filtro de saldo
  implausível (acima) resolve o caso extremo (overflow), mas não resolve
  "poeira" comum nem tokens genuinamente sem preço — isso passou a ser
  tratado na apresentação (ver Frontend/Ativos abaixo) em vez de ocultar
  dado no backend, pra não esconder informação que pode ficar relevante.
  ⚠️ **Observação, não bug**: preço listado na CoinGecko não garante
  liquidez real — um token pode ter uma cotação técnica alta mas pouco
  volume/profundidade de mercado pra vender pelo preço mostrado (comum
  em tokens de baixíssima capitalização). A tela mostra o preço que a
  CoinGecko informa, sem validar liquidez — mesma limitação que
  qualquer app do tipo (CoinStats, Zerion, etc.) tem.
  ⚠️ `PortfolioControllerTest` tem 2 casos sensíveis a horário de
  execução (bucket por hora) que falham dependendo de quando os testes
  rodam — pré-existente, não relacionado a nenhuma mudança recente,
  ainda não investigado a fundo.
- **Confiabilidade (Fase A, 2026-07-22)**: todas as chamadas HTTP externas
  (RPC Ethereum/Solana, API Bitcoin da Blockstream, CoinGecko) usam
  `Http::timeout(5)->retry(2, 200, throw: false)` — 3 tentativas com
  200ms de intervalo antes de desistir. `throw: false` é necessário
  porque o `retry()` do Laravel por padrão lança `RequestException`
  quando esgota as tentativas, o que pulava o tratamento `abort(502)`
  já existente (gotcha descoberto rodando os testes). Falhas geram
  `Log::warning()` com contexto (endereço, status) antes do abort.
  `WalletBalanceController::show()` agora tenta o saldo ao vivo e, se
  falhar, cai pro último saldo salvo em `wallet_balance_histories` em
  vez de retornar 502 — resposta inclui `stale: true/false` (frontend
  mostra um aviso "Desatualizado" em `WalletItem` quando `true`). Só
  retorna 502 se não houver nenhum saldo salvo pra cair de volta.
- **Redis + fila + captura assíncrona (Fase B, 2026-07-22)**: `CACHE_STORE`
  e `QUEUE_CONNECTION` agora são `redis` (antes `database`).
  `REDIS_CLIENT=predis` (pacote Composer puro PHP, não a extensão
  `phpredis` — evita mexer no `Dockerfile`/rebuild de imagem). Serviços
  novos no `docker-compose.yml`: `redis` (imagem `redis:7-alpine`, com
  volume) e `queue` (mesma imagem do `app`, rodando `php artisan
  queue:work --tries=3 --backoff=5,15,30`).
  `GET /wallets/{id}/balance` **nunca mais trava esperando a
  blockchain**, exceto na primeiríssima consulta de uma wallet sem
  histórico algum: `BlockchainServiceInterface` ganhou
  `getCachedBalance()` (só lê o cache, nunca chama a rede); o controller
  usa ele primeiro — cache quente responde na hora, cache frio com
  histórico existente despacha `App\Jobs\RefreshWalletBalance` (fila,
  retry com backoff) e responde na hora com `stale: true` usando o
  último saldo salvo (mesmo mecanismo de fallback da Fase A, reaproveitado
  como caminho padrão em vez de só uma rede de segurança). O botão manual
  "Atualizar saldo" manda `?force=true`, que ignora o cache e busca ao
  vivo de propósito — `getBalance(address, forceRefresh: true)` agora
  aceita esse parâmetro e dá `Cache::forget()` antes do `remember()`
  (sem isso, o `Cache::remember` simplesmente devolvia o valor
  cacheado antigo mesmo com "force" — bug pego pelos testes antes de ir
  pro ar). `wallets:capture-balances` (comando agendado de hora em hora)
  não faz mais o trabalho pesado inline — só despacha um job por wallet,
  processado em paralelo pelo worker com retry isolado por wallet.
  ⚠️ **Gotcha de debug** (não é bug, só anotação): o Redis do Laravel usa
  o banco lógico **1** pra cache por padrão (banco 0 é usado por fila/
  sessão) — `redis-cli` sem `-n 1` não mostra as chaves de cache.
- `POST /api/register` (senha: mínimo 8 caracteres, letras e números via
  `Illuminate\Validation\Rules\Password`), `POST /api/login` (Sanctum,
  token Bearer)
- **Confirmação de email obrigatória (2026-07-22)**: cadastro valida o
  email com `email:rfc,dns` — rejeita domínio inventado (ex:
  `usuario@bol`) checando se o domínio tem registro DNS de verdade.
  Configurável via `config('registration.validate_email_dns')`
  (`VALIDATE_EMAIL_DNS` no `.env`), desligado nos testes
  (`phpunit.xml`) porque domínios de teste como `example.com` têm um
  registro **Null MX** de propósito (RFC 7505 — sinaliza "não aceito
  email"), o que faria a checagem falhar mesmo sendo um domínio real.
  Conta é criada mas **não loga automaticamente** — fica pendente até
  confirmar. Token próprio (coluna `email_verification_token` em
  `users`, hash salvo, nunca o valor puro — mesmo raciocínio do
  `password_reset_tokens` nativo), porque não existe broker de
  verificação de email pronto no Laravel como existe pra reset de
  senha. `POST /api/email/verify` (token + email, confirma e já
  retorna token de sessão — login automático) e `POST /api/email/resend`
  (limitado a 3 tentativas/10min, sempre retorna a mesma mensagem
  independente do email existir, mesmo padrão do forgot-password).
  `POST /api/login` retorna 403 pra quem não confirmou. Envio do email
  é protegido por try/catch — uma falha no provedor (Resend em modo
  sandbox, por exemplo) não derruba o cadastro nem deixa o usuário
  preso sem conseguir tentar de novo.
  ⚠️ **Débito técnico de frontend**: `VerifyEmail.jsx` precisou de dois
  ajustes por causa do React 19 Strict Mode (que monta/desmonta/remonta
  componentes de propósito em desenvolvimento pra pegar efeito colateral
  mal escrito) — um guard por token evita chamar a API duas vezes
  (gastaria o token na primeira chamada), e a resposta da chamada real
  **não** pode ser descartada por um flag de "componente ainda montado"
  (React 18+ já ignora com segurança um `setState` de componente
  desmontado de verdade; um flag manual nessa checagem some antes da
  resposta chegar, no timing do Strict Mode).
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
- **Polygon e BNB Chain (2026-07-23)**: mais duas redes EVM-compatíveis
  além do Ethereum. Decisão de arquitetura (discutida com o Wellington
  como "Eixo A" — blockchains nativas novas — vs "Eixo B" — tokens dentro
  de chains já suportadas, feito antes): como Polygon/BNB usam a mesma
  RPC JSON, o mesmo padrão de endereço `0x...` e a mesma Token API da
  Alchemy que o Ethereum já usava, a lógica foi extraída pra uma classe
  base `AbstractEvmChainService` (saldo, cache, descoberta de tokens,
  `balanceOf` via `eth_call`) — `EthereumService`, `PolygonService` e
  `BnbService` viram subclasses finas que só informam a chave de rede e
  o símbolo nativo. `EthereumService` manteve o nome e o cache key
  (`eth_balance:...`) pra não quebrar nada já existente.
  ⚠️ **Verificado ao vivo antes de codar** (mesmo hábito de sempre): uma
  `ALCHEMY_API_KEY` só atende as redes **habilitadas manualmente no
  dashboard da Alchemy** — por padrão só Ethereum vem ligado; tentar usar
  Polygon/BNB sem habilitar dá erro `"network not enabled for this app"`
  (não é falta de suporte, só falta de configuração). O Wellington
  habilitou as duas antes do teste ao vivo confirmar que funcionam.
  RPC de saldo nativo usa endpoint público **separado** da Alchemy
  (`polygon-bor-rpc.publicnode.com`, `bsc-dataseed.binance.org`) —
  mesmo racional já usado no Ethereum: não gastar cota da Alchemy com
  consultas de saldo que se repetem a cada 60s por wallet (só a
  descoberta de tokens usa a Alchemy).
  **Gotcha real, achado ao testar**: os IDs da CoinGecko **não** batem
  com as chaves de rede internas pra essas duas moedas (só bate por
  coincidência em ethereum/solana/bitcoin) — o coin id de POL é
  `polygon-ecosystem-token` (não "polygon", por causa da migração
  MATIC→POL) e o de BNB é `binancecoin` (não "bnb"); o "asset platform
  id" usado em `/simple/token_price/{platform}` também é outro valor
  (`polygon-pos`, `binance-smart-chain`). `PriceService` ganhou um mapa
  explícito (`COINGECKO_COIN_IDS`, `COINGECKO_PLATFORM_IDS`) em vez de
  assumir que a chave de rede sempre serve como ID da CoinGecko.
  **Outro bug pego ao testar ao vivo**: o endereço de uma wallet tinha
  restrição de unicidade **global** (`wallets.address unique`) — como um
  endereço `0x...` é o mesmo em qualquer rede EVM, isso impedia
  cadastrar a mesma carteira em Ethereum e Polygon ao mesmo tempo.
  Corrigido pra unicidade composta em `(address, network)` (migration
  `make_wallet_address_unique_per_network`).
  ⚠️ **Gotcha de infra**: o container `queue` (worker da fila) é um
  processo Laravel de longa duração que só carrega o código uma vez no
  boot — depois de qualquer mudança em código usado por jobs (ex:
  `BlockchainResolver`), é preciso `docker compose restart queue` (e
  `scheduler`, mesmo motivo) pra pegar a mudança. Sem isso, o worker
  continua rodando a versão antiga em memória mesmo com o bind mount
  atualizado, e jobs relacionados falham silenciosamente (só aparece no
  log). Pego ao vivo: token nativo funcionava (via `php artisan serve`,
  que não tem esse problema), mas a atualização automática de saldo em
  background falhava até reiniciar o worker.
  Testado ao vivo com o mesmo endereço (`vitalik.eth`) nas duas redes
  novas: Polygon achou 76 tokens, BNB Chain achou 96 — volume de spam
  bem maior que no Ethereum (gas mais barato viabiliza mais spam), mas
  o filtro anti-spam (`SPAM_BALANCE_THRESHOLD`) e a descoberta via
  Alchemy funcionaram sem erro nas duas.
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
  Wallets" / "Ativos" / "Notícias").
- **Ativos** (`Assets.jsx`, `/ativos`) — redesenhada em 2026-07-23: visão
  consolidada de todos os tokens de todas as wallets, somando o mesmo
  token quando aparece em mais de uma (`GET /api/assets`). Cards de
  resumo no topo (valor total, quantidade com preço, sem preço, total
  rastreado), filtro por rede (abas, mesmo padrão do `News.jsx`), busca
  por nome/símbolo/endereço, ordenação (valor/saldo/nome). A lista é
  dividida em duas seções: **ativos com preço** (tabela com logo, saldo,
  preço, valor e % do portfólio, ordenada por valor) e **sem preço/não
  verificados** (colapsada por padrão, mostra só a contagem até o
  usuário expandir, com aviso explícito de que o nome exibido pode ser
  spam/phishing — vários tokens descobertos em testes reais tinham nomes
  tipo "Visit https://get-usdc.com to claim rewards", nunca renderizados
  como link). Motivo da divisão: antes da correção de preço da CoinGecko
  (ver Backend acima), **0 de 373 tokens reais** tinham preço — uma
  lista única ordenada "por valor" ficava com ordem essencialmente
  aleatória. `TokenLogo` (componente local em `Assets.jsx`) mostra o
  `logo_url` do token com fallback pra um círculo colorido com a
  inicial do símbolo se a imagem não existir ou falhar ao carregar.
  Cada card da wallet (`WalletItem.jsx`) ganhou um botão "Buscar tokens"
  (redes que suportam: Ethereum, Polygon, BNB Chain, Solana) e uma lista
  expansível dos tokens já rastreados, com botão pra parar de rastrear
  um token específico. Símbolo ausente (tokens não listados na Jupiter/
  Alchemy) cai pro endereço do contrato truncado como rótulo.
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
- **Confirmação de email**: `Register.jsx` não loga mais direto após
  cadastrar — mostra uma tela "confirme seu email". `Login.jsx` trata
  o 403 de conta não confirmada com um botão de reenviar ali mesmo.
  `VerifyEmail.jsx` (`/verificar-email`) lê `token`/`email` da URL,
  confirma, loga automaticamente e redireciona pro Dashboard.

### Débitos técnicos conhecidos
- `frontend/src/config/networks.js` define cor/label de badge para
  `ethereum`, `polygon`, `bnb`, `bitcoin`, `solana` — todos já suportados
  pelo backend hoje. Ao adicionar uma rede nova (ex: Cardano, Tron —
  blockchains independentes, não EVM), é preciso atualizar esse arquivo
  **e** `WalletForm.jsx` (`ADDRESS_PATTERNS`/`NETWORK_OPTIONS`) **e**
  `TradingViewChart.jsx` (`SYMBOLS`, par da Binance) além do backend. Pra
  uma rede EVM nova (ex: Arbitrum, Base), o esforço no backend cai bastante
  — só uma subclasse fina de `AbstractEvmChainService` + entradas em
  `config/blockchain.php`/`config/alchemy.php` + mapeamento na
  `PriceService` (ver Polygon/BNB acima), sem lógica nova pra escrever.
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
- Desde a Fase B de confiabilidade (2026-07-22), o sistema depende de
  mais dois containers sempre rodando: `redis` e `queue` (worker da
  fila). Se o `queue` cair, saldos com cache frio continuam respondendo
  (fallback pro histórico), mas nunca mais se atualizam sozinhos — vale
  ficar de olho nisso quando for pensar em observabilidade (Fase C).

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

**Atualização da decisão acima (Wellington, 2026-07-22)**: prioridade
invertida — antes de ganhar novas funcionalidades, o sistema precisa ser
extremamente confiável (o usuário precisa confiar no sistema). Isso **não**
é a mesma coisa que a "Fase 3 — Infra de produção" abaixo (aquela é
deploy/produção: nginx, HTTPS, CI/CD, hospedagem). É confiabilidade do
código que já existe: cache/filas, retry automático, tratamento de
indisponibilidade de API, logs estruturados, observabilidade. Combinado
faseamento (não fazer tudo de uma vez, pré-lançamento com 1 usuário real
seria over-engineering):
- **Fase A** (baixo esforço): retry+timeout nas chamadas HTTP externas,
  fallback pro último saldo salvo em vez de erro quando a blockchain
  falha, logs estruturados nos pontos de falha. ✅ concluída (ver Fase
  2.6 abaixo).
- **Fase B** (esforço médio): Redis (cache + fila), mover consulta de
  saldo pra job assíncrono em vez de síncrona na requisição. ✅ concluída
  (ver Fase 2.7 abaixo).
- **Fase C** (perto do lançamento): observabilidade completa (Sentry já
  previsto na Fase 4), rate limit interno por usuário. Ainda não
  iniciada, intencionalmente adiada.

Combinado também: "insights" (transformar dado em frases tipo "sua
carteira está 74% concentrada em Ethereum") pode rodar em paralelo à
Fase B, já que boa parte do dado necessário já existe (concentração,
variação percentual). E monetização (planos Free/Pro/Business): desenhar
schema leve agora (ex: coluna `plan` em `users`), sem construir cobrança
de verdade ainda.

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

**Fase 2.6 — Confiabilidade, Fase A (retry/fallback/logs)** ✅ concluída:
ver detalhes na seção Backend acima.

**Fase 2.7 — Confiabilidade, Fase B (Redis + fila + captura assíncrona)**
✅ concluída: ver detalhes na seção Backend acima. Fase C (observabilidade
completa, rate limit) ainda não iniciada — ver nota de prioridade de
2026-07-22 mais acima neste arquivo.

**Fase 2.8 — Suporte a tokens/ativos (ERC-20 + SPL)** ✅ concluída: ver
detalhes na seção Backend/Frontend acima. Decisão de arquitetura:
priorizado suporte a token dentro das chains já suportadas (Ethereum,
Solana) em vez de somar mais blockchains nativas — mesmo esforço de
integração destrava muito mais moedas (qualquer token ERC-20/SPL, não
só 1 moeda por integração).

**Fase 2.9 — Mais blockchains EVM (Polygon, BNB Chain)** ✅ concluída: ver
detalhes na seção Backend acima ("Polygon e BNB Chain"). Decisão de
arquitetura: priorizado sobre o Insights v1 e o feed de transações do
Ethereum a pedido explícito do Wellington (2026-07-23) — inverteu a
ordem combinada anteriormente. Dentro do Eixo A (blockchains nativas
novas), começou pelas EVM-compatíveis (esforço baixo, reaproveitam quase
tudo do Ethereum) em vez de blockchains independentes (Cardano, Tron,
Hyperliquid, Kaspa — cada uma exigiria integração e pesquisa de
fornecedor do zero, esforço bem maior).

**Fase 2 — Completar funcionalidades** (atual)
Fases A, B de confiabilidade, suporte a tokens e mais blockchains EVM
concluídos (2026-07-23). Próximos candidatos, combinados com o
Wellington: Insights v1 (frases a partir de métricas que já existem, ex:
concentração/variação) → feed de transações do Ethereum (via Etherscan,
pede chave de API) → blockchains independentes (Cardano, Tron,
Hyperliquid, Kaspa) → alertas de variação/movimentação. Perguntar ao
Wellington a prioridade antes de escolher a próxima.

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
