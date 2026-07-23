import { useEffect, useState } from "react";
import {
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
} from "recharts";
import { Globe2, Gauge, TrendingUp, AlertCircle } from "lucide-react";
import Layout from "../components/Layout";
import FearGreedGauge from "../components/FearGreedGauge";
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from "../components/ui/card";
import { Alert, AlertDescription } from "../components/ui/alert";
import { Skeleton } from "../components/ui/skeleton";
import { Tabs, TabsList, TabsTrigger } from "../components/ui/tabs";
import { InfoTooltip } from "../components/ui/info-tooltip";
import { getMarketOverview, getFearGreedHistory } from "../services/api";
import { formatCompactUsd, formatDate } from "../utils/format";

const CHART_PERIODS = [
  { value: "30d", label: "30 dias" },
  { value: "1y", label: "1 ano" },
  { value: "all", label: "Tudo" },
];

function IndicatorCard({ icon, title, description, tooltip, children }) {
  const Icon = icon;

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <Icon className="size-4" />
          {title}
          {tooltip && <InfoTooltip>{tooltip}</InfoTooltip>}
        </CardTitle>
        {description && <CardDescription>{description}</CardDescription>}
      </CardHeader>
      <CardContent>{children}</CardContent>
    </Card>
  );
}

function Market() {
  const [overview, setOverview] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const [chartPeriod, setChartPeriod] = useState("30d");
  const [chartPoints, setChartPoints] = useState([]);
  const [chartLoading, setChartLoading] = useState(true);

  useEffect(() => {
    async function load() {
      try {
        setLoading(true);
        setError(null);
        const response = await getMarketOverview();
        setOverview(response.data);
      } catch {
        setError("Erro ao carregar indicadores de mercado.");
      } finally {
        setLoading(false);
      }
    }

    load();
  }, []);

  useEffect(() => {
    async function loadChart() {
      try {
        setChartLoading(true);
        const response = await getFearGreedHistory(chartPeriod);
        setChartPoints(response.data.points ?? []);
      } catch {
        // grafico e um extra - se falhar, os cards de indicador continuam
      } finally {
        setChartLoading(false);
      }
    }

    loadChart();
  }, [chartPeriod]);

  const chartData = chartPoints.map((point) => ({
    ...point,
    label: formatDate(point.date),
  }));

  return (
    <Layout>
      <h1 className="mb-2 text-2xl font-bold text-foreground">Mercado</h1>
      <p className="mb-6 text-sm text-muted-foreground">
        Indicadores gerais do mercado cripto — não são sobre o seu
        patrimônio específico, são o contexto do mercado como um todo.
      </p>

      {loading && (
        <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
          {Array.from({ length: 3 }).map((_, index) => (
            <Skeleton key={index} className="h-48 w-full" />
          ))}
        </div>
      )}

      {error && (
        <Alert variant="destructive" className="mb-6">
          <AlertCircle />
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      {!loading && !error && overview && (
        <>
          <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <IndicatorCard
              icon={Gauge}
              title="Fear & Greed Index"
              description="Sentimento geral do mercado cripto, 0 a 100."
              tooltip={
                <>
                  Combina vários sinais do mercado (volatilidade, volume,
                  redes sociais, dominância do Bitcoin, tendência de busca)
                  numa escala única: <strong>0</strong> é Medo Extremo
                  (pânico, gente vendendo com medo) e <strong>100</strong> é
                  Ganância Extrema (euforia, gente comprando sem pensar
                  muito). Calculado pela Alternative.me, não por nós — não
                  é recomendação de compra ou venda.
                </>
              }
            >
              {overview.fear_greed ? (
                <FearGreedGauge
                  value={overview.fear_greed.value}
                  classification={overview.fear_greed.classification}
                />
              ) : (
                <p className="text-sm text-muted-foreground">Indisponível no momento.</p>
              )}
            </IndicatorCard>

            <IndicatorCard
              icon={Globe2}
              title="Dominância do Bitcoin"
              description="Fatia do Bitcoin sobre o valor de mercado total de cripto."
              tooltip="Qual % de todo o dinheiro em criptomoedas está no Bitcoin especificamente. Quando essa % sobe, geralmente o dinheiro está migrando de altcoins pro Bitcoin (ou o Bitcoin subindo mais que o resto); quando cai, o oposto — mais dinheiro fluindo pra outras moedas."
            >
              {overview.global ? (
                <div>
                  <div className="text-3xl font-bold text-foreground">
                    {overview.global.btc_dominance}%
                  </div>
                  <p className="mt-1 text-sm text-muted-foreground">
                    Market cap total: {formatCompactUsd(overview.global.total_market_cap_usd)}
                  </p>
                  <p className="text-sm text-muted-foreground">
                    Ethereum: {overview.global.eth_dominance}%
                  </p>
                </div>
              ) : (
                <p className="text-sm text-muted-foreground">Indisponível no momento.</p>
              )}
            </IndicatorCard>

            <IndicatorCard
              icon={TrendingUp}
              title="Altcoin Season Index"
              description="Aproximação própria — % de altcoins que bateram o Bitcoin em 30 dias."
              tooltip="Mede se o mercado favorece o Bitcoin ou as altcoins no momento. Acima de 75 é considerado 'Temporada de Altcoins' (a maioria delas subindo mais que o Bitcoin); abaixo de 25 é 'Temporada de Bitcoin'; no meio é misto. Veja o texto abaixo do número pra entender a diferença da nossa versão pro índice oficial."
            >
              {overview.altcoin_season ? (
                <div>
                  <div className="text-3xl font-bold text-foreground">
                    {overview.altcoin_season.value}
                  </div>
                  <p className="mt-1 text-sm text-foreground">
                    {overview.altcoin_season.classification}
                  </p>
                  <p className="mt-2 text-xs text-muted-foreground">
                    {overview.altcoin_season.methodology}
                  </p>
                </div>
              ) : (
                <p className="text-sm text-muted-foreground">Indisponível no momento.</p>
              )}
            </IndicatorCard>
          </div>

          <Card>
            <CardHeader>
              <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                  <CardTitle>Histórico do Fear & Greed Index</CardTitle>
                  <CardDescription>Valor diário ao longo do tempo.</CardDescription>
                </div>

                <Tabs value={chartPeriod} onValueChange={setChartPeriod}>
                  <TabsList>
                    {CHART_PERIODS.map((option) => (
                      <TabsTrigger key={option.value} value={option.value}>
                        {option.label}
                      </TabsTrigger>
                    ))}
                  </TabsList>
                </Tabs>
              </div>
            </CardHeader>
            <CardContent>
              {chartLoading ? (
                <Skeleton className="h-72 w-full" />
              ) : chartData.length < 2 ? (
                <p className="text-muted-foreground">
                  Ainda não há dados suficientes nesse período.
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
                      domain={[0, 100]}
                      stroke="var(--color-border)"
                      fontSize={12}
                      tick={{ fill: "var(--color-muted-foreground)" }}
                    />
                    <Tooltip
                      contentStyle={{
                        backgroundColor: "var(--color-card)",
                        border: "1px solid var(--color-border)",
                        borderRadius: 8,
                      }}
                      labelStyle={{ color: "var(--color-foreground)" }}
                      formatter={(value, _name, props) => [
                        `${value} (${props.payload.classification})`,
                        "Índice",
                      ]}
                    />
                    <Line
                      type="monotone"
                      dataKey="value"
                      stroke="var(--color-primary)"
                      strokeWidth={2}
                      dot={false}
                    />
                  </LineChart>
                </ResponsiveContainer>
              )}
            </CardContent>
          </Card>
        </>
      )}
    </Layout>
  );
}

export default Market;
