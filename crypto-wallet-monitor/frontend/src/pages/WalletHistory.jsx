import { useEffect, useState } from "react";
import { useParams, Link } from "react-router-dom";
import {
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
} from "recharts";
import {
  ArrowLeft,
  Wallet,
  DollarSign,
  TrendingUp,
  TrendingDown,
  Gauge,
  AlertCircle,
  LineChart as LineChartIcon,
  BarChart3,
  Receipt,
  CandlestickChart,
} from "lucide-react";
import Layout from "../components/Layout";
import PriceChangeBadge from "../components/PriceChangeBadge";
import StatCard from "../components/StatCard";
import TradingViewChart from "../components/TradingViewChart";
import TransactionList from "../components/TransactionList";
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from "../components/ui/card";
import { Badge } from "../components/ui/badge";
import { Alert, AlertDescription } from "../components/ui/alert";
import { Skeleton } from "../components/ui/skeleton";
import { Tabs, TabsList, TabsTrigger } from "../components/ui/tabs";
import { InfoTooltip } from "../components/ui/info-tooltip";
import { getWalletHistory, getPrices } from "../services/api";
import { formatUsd, formatCompactUsd, formatDateTime } from "../utils/format";
import { NETWORKS } from "../config/networks";

const PERIODS = [
  { value: "24h", label: "24h" },
  { value: "7d", label: "7 dias" },
  { value: "30d", label: "30 dias" },
  { value: "all", label: "Tudo" },
];

function WalletHistory() {
  const { id } = useParams();
  const [period, setPeriod] = useState("7d");
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [marketData, setMarketData] = useState(null);

  useEffect(() => {
    async function loadHistory() {
      try {
        setLoading(true);
        setError(null);
        const response = await getWalletHistory(id, period);
        setData(response.data);
      } catch {
        setError("Erro ao carregar histórico.");
      } finally {
        setLoading(false);
      }
    }

    loadHistory();
  }, [id, period]);

  useEffect(() => {
    async function loadMarketData() {
      try {
        const response = await getPrices();
        setMarketData(response.data);
      } catch {
        // dados de mercado sao um extra: se falhar, o resto da tela continua
      }
    }

    loadMarketData();
  }, []);

  const networkConfig = data ? NETWORKS[data.network] : null;
  const chartData = (data?.points ?? []).map((point) => ({
    ...point,
    label: formatDateTime(point.captured_at),
  }));
  const coinMarketData = data ? marketData?.[data.network] : null;
  const isPositiveChange = (data?.summary.change_value_usd ?? 0) >= 0;

  return (
    <Layout>
      <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
          <Link
            to="/wallets"
            className="inline-flex items-center gap-1 text-sm text-primary hover:underline"
          >
            <ArrowLeft className="size-3.5" />
            Voltar
          </Link>
          <h1 className="mt-1 text-2xl font-bold text-foreground">
            Histórico da carteira
          </h1>
        </div>

        {networkConfig && (
          <Badge className="text-white" style={{ backgroundColor: networkConfig.color }}>
            {networkConfig.label}
          </Badge>
        )}
      </div>

      <Tabs value={period} onValueChange={setPeriod} className="mb-6">
        <TabsList>
          {PERIODS.map((option) => (
            <TabsTrigger key={option.value} value={option.value}>
              {option.label}
            </TabsTrigger>
          ))}
        </TabsList>
      </Tabs>

      {loading && (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
          {Array.from({ length: 4 }).map((_, index) => (
            <Skeleton key={index} className="h-20 w-full" />
          ))}
        </div>
      )}

      {error && (
        <Alert variant="destructive">
          <AlertCircle />
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      {!loading && !error && data && (
        <>
          <div className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <StatCard
              icon={Wallet}
              label="Saldo atual"
              value={`${data.summary.current_balance ?? "--"} ${networkConfig?.symbol ?? ""}`}
              tooltip="Quantidade da moeda nativa que essa wallet tem agora, consultada direto na blockchain."
            />

            <StatCard
              icon={DollarSign}
              label="Valor atual"
              value={formatUsd(data.summary.current_value_usd)}
              tooltip="Saldo atual multiplicado pela cotação de agora, em USD."
            />

            <StatCard
              icon={isPositiveChange ? TrendingUp : TrendingDown}
              label="Variação no período"
              value={formatUsd(data.summary.change_value_usd)}
              extra={<PriceChangeBadge change={data.summary.change_percent} />}
              tooltip="Quanto o valor em USD dessa wallet mudou desde o início do período selecionado nas abas acima."
            />

            <StatCard
              icon={Gauge}
              label="Mín. / Máx."
              value={`${formatUsd(data.summary.min_value_usd)} / ${formatUsd(data.summary.max_value_usd)}`}
              tooltip="O menor e o maior valor em USD que essa wallet atingiu dentro do período selecionado."
            />
          </div>

          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <LineChartIcon className="size-4" />
                Valor da carteira ao longo do tempo
              </CardTitle>
              <CardDescription>
                Saldo × preço da moeda em cada snapshot registrado, no período
                selecionado acima.
              </CardDescription>
            </CardHeader>
            <CardContent>
              {chartData.length < 2 ? (
                <p className="text-muted-foreground">
                  Ainda não há dados suficientes nesse período para desenhar um
                  gráfico. Consulte o saldo algumas vezes ao longo do tempo
                  para acumular histórico (ou aguarde a captura automática por
                  hora).
                </p>
              ) : (
                <ResponsiveContainer width="100%" height={300}>
                  <LineChart data={chartData}>
                    <CartesianGrid strokeDasharray="3 3" stroke="var(--color-border)" />
                    <XAxis
                      dataKey="label"
                      stroke="var(--color-border)"
                      fontSize={12}
                      tick={{ fill: "var(--color-muted-foreground)" }}
                    />
                    <YAxis
                      stroke="var(--color-border)"
                      fontSize={12}
                      tick={{ fill: "var(--color-muted-foreground)" }}
                      tickFormatter={(value) => `$${value.toLocaleString()}`}
                    />
                    <Tooltip
                      contentStyle={{
                        backgroundColor: "var(--color-card)",
                        border: "1px solid var(--color-border)",
                        borderRadius: 8,
                      }}
                      labelStyle={{ color: "var(--color-foreground)" }}
                      formatter={(value) => [formatUsd(value), "Valor"]}
                    />
                    <Line
                      type="monotone"
                      dataKey="value_usd"
                      stroke="var(--color-primary)"
                      strokeWidth={2}
                      dot={false}
                    />
                  </LineChart>
                </ResponsiveContainer>
              )}
            </CardContent>
          </Card>

          {coinMarketData && (
            <Card className="mt-6">
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <BarChart3 className="size-4" />
                  Dados de mercado — {networkConfig?.label}
                  <InfoTooltip>
                    Indicadores gerais dessa moeda no mercado — não são
                    específicos dessa wallet, são os mesmos pra todo mundo
                    que tem essa moeda.
                  </InfoTooltip>
                </CardTitle>
              </CardHeader>
              <CardContent className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <div>
                  <div className="flex items-center gap-1 text-xs text-muted-foreground">
                    Market cap
                    <InfoTooltip>
                      Valor de mercado total da moeda: preço atual ×
                      quantidade em circulação. Costuma ser usado como
                      referência do "tamanho" de uma criptomoeda.
                    </InfoTooltip>
                  </div>
                  <div className="mt-1 text-foreground">
                    {formatCompactUsd(coinMarketData.market_cap)}
                  </div>
                </div>

                <div>
                  <div className="flex items-center gap-1 text-xs text-muted-foreground">
                    Volume 24h
                    <InfoTooltip>
                      Quanto dessa moeda foi negociado nas últimas 24 horas,
                      somando as exchanges monitoradas pela CoinGecko.
                    </InfoTooltip>
                  </div>
                  <div className="mt-1 text-foreground">
                    {formatCompactUsd(coinMarketData.volume_24h)}
                  </div>
                </div>

                <div>
                  <div className="text-xs text-muted-foreground">Máx. / Mín. 24h</div>
                  <div className="mt-1 text-foreground">
                    {formatUsd(coinMarketData.high_24h)} /{" "}
                    {formatUsd(coinMarketData.low_24h)}
                  </div>
                </div>

                <div>
                  <div className="text-xs text-muted-foreground">Variação 24h</div>
                  <div className="mt-1">
                    <PriceChangeBadge change={coinMarketData.change_24h} />
                  </div>
                </div>

                <div>
                  <div className="text-xs text-muted-foreground">Variação 7d</div>
                  <div className="mt-1">
                    <PriceChangeBadge change={coinMarketData.change_7d} />
                  </div>
                </div>

                <div>
                  <div className="text-xs text-muted-foreground">Variação 30d</div>
                  <div className="mt-1">
                    <PriceChangeBadge change={coinMarketData.change_30d} />
                  </div>
                </div>
              </CardContent>
            </Card>
          )}

          <Card className="mt-6">
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <Receipt className="size-4" />
                Últimas transações
                <InfoTooltip>
                  Entradas e saídas dessa wallet direto na blockchain.
                  Disponível hoje só pra Solana e Bitcoin — a RPC pública do
                  Ethereum/Polygon/BNB usada pro saldo não lista
                  transações.
                </InfoTooltip>
              </CardTitle>
            </CardHeader>
            <CardContent>
              <TransactionList walletId={data.wallet_id} symbol={networkConfig?.symbol} />
            </CardContent>
          </Card>

          {networkConfig && (
            <Card className="mt-6">
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <CandlestickChart className="size-4" />
                  Gráfico de mercado (TradingView)
                  <InfoTooltip>
                    Widget oficial e gratuito da TradingView, com o gráfico
                    de candles real da moeda (par negociado na Binance) —
                    não é um dado calculado por nós.
                  </InfoTooltip>
                </CardTitle>
              </CardHeader>
              <CardContent>
                <TradingViewChart network={data.network} />
              </CardContent>
            </Card>
          )}
        </>
      )}
    </Layout>
  );
}

export default WalletHistory;
