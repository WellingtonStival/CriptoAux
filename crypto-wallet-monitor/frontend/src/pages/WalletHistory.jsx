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
import Layout from "../components/Layout";
import PriceChangeBadge from "../components/PriceChangeBadge";
import TradingViewChart from "../components/TradingViewChart";
import TransactionList from "../components/TransactionList";
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

  return (
    <Layout>
      <div className="mb-6 flex items-center justify-between">
        <div>
          <Link to="/" className="text-sm text-indigo-400 hover:underline">
            ← Voltar
          </Link>
          <h1 className="mt-1 text-2xl font-bold text-slate-50">
            Histórico da carteira
          </h1>
        </div>

        {networkConfig && (
          <span
            className="rounded-full px-2.5 py-0.5 text-xs font-medium text-white"
            style={{ backgroundColor: networkConfig.color }}
          >
            {networkConfig.label}
          </span>
        )}
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
          <div className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div className="rounded-lg border border-slate-800 bg-slate-950 p-4">
              <div className="text-xs text-slate-400">Saldo atual</div>
              <div className="mt-1 text-lg font-semibold text-slate-50">
                {data.summary.current_balance ?? "--"} {networkConfig?.symbol}
              </div>
            </div>

            <div className="rounded-lg border border-slate-800 bg-slate-950 p-4">
              <div className="text-xs text-slate-400">Valor atual</div>
              <div className="mt-1 text-lg font-semibold text-slate-50">
                {formatUsd(data.summary.current_value_usd)}
              </div>
            </div>

            <div className="rounded-lg border border-slate-800 bg-slate-950 p-4">
              <div className="text-xs text-slate-400">Variação no período</div>
              <div className="mt-1 flex items-center gap-2 text-lg font-semibold text-slate-50">
                {formatUsd(data.summary.change_value_usd)}
                <PriceChangeBadge change={data.summary.change_percent} />
              </div>
            </div>

            <div className="rounded-lg border border-slate-800 bg-slate-950 p-4">
              <div className="text-xs text-slate-400">Mín. / Máx.</div>
              <div className="mt-1 text-sm font-semibold text-slate-50">
                {formatUsd(data.summary.min_value_usd)} /{" "}
                {formatUsd(data.summary.max_value_usd)}
              </div>
            </div>
          </div>

          <div className="rounded-lg border border-slate-800 bg-slate-950 p-4">
            <h2 className="text-sm font-medium text-slate-300">
              Valor da carteira ao longo do tempo
            </h2>
            <p className="mb-3 text-xs text-slate-500">
              Saldo × preço da moeda em cada snapshot registrado, no período
              selecionado acima.
            </p>

            {chartData.length < 2 ? (
              <p className="text-slate-400">
                Ainda não há dados suficientes nesse período para desenhar um
                gráfico. Consulte o saldo algumas vezes ao longo do tempo
                para acumular histórico (ou aguarde a captura automática por
                hora).
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

          {coinMarketData && (
            <div className="mt-6 rounded-lg border border-slate-800 bg-slate-950 p-4">
              <h2 className="mb-3 text-sm font-medium text-slate-300">
                Dados de mercado — {networkConfig?.label}
              </h2>

              <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <div>
                  <div className="text-xs text-slate-400">Market cap</div>
                  <div className="mt-1 text-slate-50">
                    {formatCompactUsd(coinMarketData.market_cap)}
                  </div>
                </div>

                <div>
                  <div className="text-xs text-slate-400">Volume 24h</div>
                  <div className="mt-1 text-slate-50">
                    {formatCompactUsd(coinMarketData.volume_24h)}
                  </div>
                </div>

                <div>
                  <div className="text-xs text-slate-400">Máx. / Mín. 24h</div>
                  <div className="mt-1 text-slate-50">
                    {formatUsd(coinMarketData.high_24h)} /{" "}
                    {formatUsd(coinMarketData.low_24h)}
                  </div>
                </div>

                <div>
                  <div className="text-xs text-slate-400">Variação 24h</div>
                  <div className="mt-1">
                    <PriceChangeBadge change={coinMarketData.change_24h} />
                  </div>
                </div>

                <div>
                  <div className="text-xs text-slate-400">Variação 7d</div>
                  <div className="mt-1">
                    <PriceChangeBadge change={coinMarketData.change_7d} />
                  </div>
                </div>

                <div>
                  <div className="text-xs text-slate-400">Variação 30d</div>
                  <div className="mt-1">
                    <PriceChangeBadge change={coinMarketData.change_30d} />
                  </div>
                </div>
              </div>
            </div>
          )}

          <div className="mt-6 rounded-lg border border-slate-800 bg-slate-950 p-4">
            <h2 className="mb-3 text-sm font-medium text-slate-300">
              Últimas transações
            </h2>
            <TransactionList walletId={data.wallet_id} symbol={networkConfig?.symbol} />
          </div>

          {networkConfig && (
            <div className="mt-6">
              <h2 className="mb-3 text-sm font-medium text-slate-300">
                Gráfico de mercado (TradingView)
              </h2>
              <TradingViewChart network={data.network} />
            </div>
          )}
        </>
      )}
    </Layout>
  );
}

export default WalletHistory;
