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
import Layout from "../components/Layout";
import PriceChangeBadge from "../components/PriceChangeBadge";
import PricesPanel from "../components/PricesPanel";
import { getPortfolioHistory, getPrices } from "../services/api";
import { formatUsd, formatDateTime } from "../utils/format";
import { NETWORKS } from "../config/networks";

const PERIODS = [
  { value: "24h", label: "24h" },
  { value: "7d", label: "7 dias" },
  { value: "30d", label: "30 dias" },
  { value: "all", label: "Tudo" },
];

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

  return (
    <Layout>
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-2xl font-bold text-slate-50">Meu Patrimônio</h1>
        <Link
          to="/wallets"
          className="rounded-md border border-slate-700 px-3 py-1.5 text-sm text-slate-200 hover:bg-slate-800"
        >
          Gerenciar wallets
        </Link>
      </div>

      <div className="mb-6 flex gap-2">
        {PERIODS.map((option) => (
          <button
            key={option.value}
            onClick={() => setPeriod(option.value)}
            className={`rounded-md px-3 py-1.5 text-sm ${
              period === option.value
                ? "bg-indigo-600 text-white"
                : "border border-slate-700 text-slate-300 hover:bg-slate-800"
            }`}
          >
            {option.label}
          </button>
        ))}
      </div>

      {loading && <p className="text-slate-400">Carregando...</p>}
      {error && <p className="text-red-400">{error}</p>}

      {!loading && !error && data && (
        <>
          {data.points.length === 0 ? (
            <div className="mb-6 rounded-lg border border-slate-800 bg-slate-950 p-4">
              <p className="text-slate-400">
                Nenhum dado de patrimônio ainda. Cadastre uma wallet e
                consulte o saldo dela pra começar a acumular histórico.
              </p>
              <Link
                to="/wallets"
                className="mt-3 inline-block text-sm text-indigo-400 hover:underline"
              >
                Ir para Minhas Wallets →
              </Link>
            </div>
          ) : (
            <>
              <div className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div className="rounded-lg border border-slate-800 bg-slate-950 p-4">
                  <div className="text-xs text-slate-400">Valor atual</div>
                  <div className="mt-1 text-lg font-semibold text-slate-50">
                    {formatUsd(data.summary.current_value_usd)}
                  </div>
                </div>

                <div className="rounded-lg border border-slate-800 bg-slate-950 p-4">
                  <div className="text-xs text-slate-400">
                    Variação no período
                  </div>
                  <div className="mt-1 flex items-center gap-2 text-lg font-semibold text-slate-50">
                    {formatUsd(data.summary.change_value_usd)}
                    <PriceChangeBadge change={data.summary.change_percent} />
                  </div>
                </div>

                <div className="rounded-lg border border-slate-800 bg-slate-950 p-4">
                  <div className="text-xs text-slate-400">Mínimo</div>
                  <div className="mt-1 text-lg font-semibold text-slate-50">
                    {formatUsd(data.summary.min_value_usd)}
                  </div>
                </div>

                <div className="rounded-lg border border-slate-800 bg-slate-950 p-4">
                  <div className="text-xs text-slate-400">Máximo</div>
                  <div className="mt-1 text-lg font-semibold text-slate-50">
                    {formatUsd(data.summary.max_value_usd)}
                  </div>
                </div>
              </div>

              <div className="mb-6 rounded-lg border border-slate-800 bg-slate-950 p-4">
                <h2 className="text-sm font-medium text-slate-300">
                  Evolução do patrimônio
                </h2>
                <p className="mb-3 text-xs text-slate-500">
                  Soma do valor de todas as suas wallets em cada snapshot
                  registrado, no período selecionado acima.
                </p>

                {chartData.length < 2 ? (
                  <p className="text-slate-400">
                    Ainda não há dados suficientes nesse período para
                    desenhar um gráfico.
                  </p>
                ) : (
                  <ResponsiveContainer width="100%" height={300}>
                    <LineChart data={chartData}>
                      <CartesianGrid strokeDasharray="3 3" stroke="#1e293b" />
                      <XAxis
                        dataKey="label"
                        stroke="#64748b"
                        fontSize={12}
                        tick={{ fill: "#94a3b8" }}
                      />
                      <YAxis
                        stroke="#64748b"
                        fontSize={12}
                        tick={{ fill: "#94a3b8" }}
                        tickFormatter={(value) => `$${value.toLocaleString()}`}
                      />
                      <Tooltip
                        contentStyle={{
                          backgroundColor: "#020617",
                          border: "1px solid #1e293b",
                          borderRadius: 8,
                        }}
                        labelStyle={{ color: "#f8fafc" }}
                        formatter={(value) => [formatUsd(value), "Valor"]}
                      />
                      <Line
                        type="monotone"
                        dataKey="value_usd"
                        stroke="#6366f1"
                        strokeWidth={2}
                        dot={false}
                      />
                    </LineChart>
                  </ResponsiveContainer>
                )}
              </div>

              {data.allocation.length > 0 && (
                <div className="mb-6 rounded-lg border border-slate-800 bg-slate-950 p-4">
                  <h2 className="mb-3 text-sm font-medium text-slate-300">
                    Distribuição por moeda
                  </h2>

                  <div className="flex flex-col gap-3">
                    {data.allocation.map((item) => {
                      const config = NETWORKS[item.network] ?? {
                        label: item.network,
                        color: "#999",
                      };

                      return (
                        <div key={item.network}>
                          <div className="mb-1 flex items-center justify-between text-sm">
                            <span className="flex items-center gap-2 text-slate-200">
                              <span
                                className="h-2 w-2 rounded-full"
                                style={{ backgroundColor: config.color }}
                              />
                              {config.label}
                            </span>
                            <span className="text-slate-400">
                              {formatUsd(item.value_usd)} ·{" "}
                              {item.percent.toFixed(1)}%
                            </span>
                          </div>
                          <div className="h-2 w-full overflow-hidden rounded-full bg-slate-800">
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
                  </div>
                </div>
              )}
            </>
          )}
        </>
      )}

      <PricesPanel prices={prices} />
    </Layout>
  );
}

export default Dashboard;
