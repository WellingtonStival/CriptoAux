import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
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
  Wallet,
  DollarSign,
  TrendingUp,
  TrendingDown,
  ArrowDownToLine,
  ArrowUpToLine,
  AlertCircle,
  Inbox,
  PieChart,
  Layers,
} from "lucide-react";
import Layout from "../components/Layout";
import PriceChangeBadge from "../components/PriceChangeBadge";
import PricesPanel from "../components/PricesPanel";
import StatCard from "../components/StatCard";
import { Button } from "../components/ui/button";
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from "../components/ui/card";
import { Badge } from "../components/ui/badge";
import { Alert, AlertDescription } from "../components/ui/alert";
import { Skeleton } from "../components/ui/skeleton";
import { Tabs, TabsList, TabsTrigger } from "../components/ui/tabs";
import { getPortfolioHistory, getPrices } from "../services/api";
import { formatUsd, formatDateTime } from "../utils/format";
import { NETWORKS } from "../config/networks";

const PERIODS = [
  { value: "24h", label: "24h" },
  { value: "7d", label: "7 dias" },
  { value: "30d", label: "30 dias" },
  { value: "all", label: "Tudo" },
];

const CONCENTRATION_LEVELS = {
  diversificado: { label: "Diversificado", variant: "success" },
  moderado: { label: "Moderado", variant: "warning" },
  concentrado: { label: "Concentrado", variant: "destructive" },
  indefinido: { label: "Sem dados", variant: "outline" },
};

function Dashboard() {
  const [period, setPeriod] = useState("7d");
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [prices, setPrices] = useState(null);

  useEffect(() => {
    async function loadHistory() {
      try {
        setLoading(true);
        setError(null);
        const response = await getPortfolioHistory(period);
        setData(response.data);
      } catch {
        setError("Erro ao carregar seu patrimônio.");
      } finally {
        setLoading(false);
      }
    }

    loadHistory();
  }, [period]);

  useEffect(() => {
    async function loadPrices() {
      try {
        const response = await getPrices();
        setPrices(response.data);
      } catch {
        // cotações são um extra: se falhar, o resto da tela continua
      }
    }

    loadPrices();
  }, []);

  const chartData = (data?.points ?? []).map((point) => ({
    ...point,
    label: formatDateTime(point.captured_at),
  }));

  const isPositiveChange = (data?.summary.change_value_usd ?? 0) >= 0;

  return (
    <Layout>
      <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-2xl font-bold text-foreground">Meu Patrimônio</h1>
        <Button variant="outline" size="sm" asChild>
          <Link to="/wallets">
            <Wallet className="size-4" />
            Gerenciar wallets
          </Link>
        </Button>
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
        <div className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
          {Array.from({ length: 4 }).map((_, index) => (
            <Skeleton key={index} className="h-20 w-full" />
          ))}
        </div>
      )}

      {error && (
        <Alert variant="destructive" className="mb-6">
          <AlertCircle />
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      {!loading && !error && data && (
        <>
          {data.points.length === 0 ? (
            <Card className="mb-6">
              <CardContent className="flex flex-col items-start gap-3 pt-4">
                <Inbox className="size-6 text-muted-foreground" />
                <p className="text-muted-foreground">
                  Nenhum dado de patrimônio ainda. Cadastre uma wallet e
                  consulte o saldo dela pra começar a acumular histórico.
                </p>
                <Button variant="link" className="h-auto p-0" asChild>
                  <Link to="/wallets">Ir para Minhas Wallets →</Link>
                </Button>
              </CardContent>
            </Card>
          ) : (
            <>
              <div className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <StatCard
                  icon={DollarSign}
                  label="Valor atual"
                  value={formatUsd(data.summary.current_value_usd)}
                />

                <StatCard
                  icon={isPositiveChange ? TrendingUp : TrendingDown}
                  label="Variação no período"
                  value={formatUsd(data.summary.change_value_usd)}
                  extra={<PriceChangeBadge change={data.summary.change_percent} />}
                />

                <StatCard
                  icon={ArrowDownToLine}
                  label="Mínimo"
                  value={formatUsd(data.summary.min_value_usd)}
                />

                <StatCard
                  icon={ArrowUpToLine}
                  label="Máximo"
                  value={formatUsd(data.summary.max_value_usd)}
                />
              </div>

              <Card className="mb-6">
                <CardHeader>
                  <CardTitle>Evolução do patrimônio</CardTitle>
                  <CardDescription>
                    Soma do valor de todas as suas wallets em cada snapshot
                    registrado, no período selecionado acima.
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  {chartData.length < 2 ? (
                    <p className="text-muted-foreground">
                      Ainda não há dados suficientes nesse período para
                      desenhar um gráfico.
                    </p>
                  ) : (
                    <ResponsiveContainer width="100%" height={300}>
                      <LineChart data={chartData}>
                        <CartesianGrid
                          strokeDasharray="3 3"
                          stroke="var(--color-border)"
                        />
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

              {data.allocation.length > 0 && (
                <Card className="mb-6">
                  <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                      <PieChart className="size-4" />
                      Distribuição por moeda
                    </CardTitle>
                  </CardHeader>
                  <CardContent className="flex flex-col gap-3">
                    {data.allocation.map((item) => {
                      const config = NETWORKS[item.network] ?? {
                        label: item.network,
                        color: "#999",
                      };

                      return (
                        <div key={item.network}>
                          <div className="mb-1 flex items-center justify-between text-sm">
                            <span className="flex items-center gap-2 text-foreground">
                              <span
                                className="h-2 w-2 rounded-full"
                                style={{ backgroundColor: config.color }}
                              />
                              {config.label}
                            </span>
                            <span className="text-muted-foreground">
                              {formatUsd(item.value_usd)} ·{" "}
                              {item.percent.toFixed(1)}%
                            </span>
                          </div>
                          <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
                            <div
                              className="h-full rounded-full"
                              style={{
                                width: `${Math.min(item.percent, 100)}%`,
                                backgroundColor: config.color,
                              }}
                            />
                          </div>
                        </div>
                      );
                    })}
                  </CardContent>
                </Card>
              )}

              {data.concentration && (
                <Card className="mb-6">
                  <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                      <Layers className="size-4" />
                      Concentração
                    </CardTitle>
                    <CardDescription>
                      O quanto seu patrimônio está espalhado entre moedas e
                      wallets diferentes — não é uma recomendação, é só um
                      fato sobre a distribuição.
                    </CardDescription>
                  </CardHeader>
                  <CardContent className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <ConcentrationCard
                      title="Por moeda"
                      concentration={data.concentration.by_network}
                      topLabel={
                        data.concentration.by_network.top_network
                          ? NETWORKS[data.concentration.by_network.top_network]
                              ?.label ?? data.concentration.by_network.top_network
                          : null
                      }
                    />

                    <ConcentrationCard
                      title="Por wallet"
                      concentration={data.concentration.by_wallet}
                      topLabel={data.concentration.by_wallet.top_wallet_label}
                    />
                  </CardContent>
                </Card>
              )}
            </>
          )}
        </>
      )}

      <PricesPanel prices={prices} />
    </Layout>
  );
}

function ConcentrationCard({ title, concentration, topLabel }) {
  const level = CONCENTRATION_LEVELS[concentration.level] ?? CONCENTRATION_LEVELS.indefinido;

  return (
    <Card className="bg-background">
      <CardContent className="pt-4">
        <div className="mb-2 flex items-center justify-between">
          <span className="text-sm text-foreground">{title}</span>
          <Badge variant={level.variant}>{level.label}</Badge>
        </div>

        {topLabel ? (
          <p className="text-sm text-muted-foreground">
            Maior posição:{" "}
            <span className="text-foreground">{topLabel}</span> ·{" "}
            {concentration.top_percent.toFixed(1)}% do total
          </p>
        ) : (
          <p className="text-sm text-muted-foreground">Sem dados suficientes ainda.</p>
        )}
      </CardContent>
    </Card>
  );
}

export default Dashboard;
