import { useState, useEffect } from "react";
import { getWalletBalance } from "../services/api";
import { NETWORKS } from "../config/networks";
import PriceChangeBadge from "./PriceChangeBadge";

function WalletItem({ wallet, prices }) {
  const networkConfig = NETWORKS[wallet.network] ?? {
    label: wallet.network,
    symbol: "",
    color: "#999",
  };
  const [loading, setLoading] = useState(false);
  const [balance, setBalance] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    const cached = localStorage.getItem(`wallet_balance_${wallet.id}`);
    if (cached) {
      setBalance(JSON.parse(cached));
    }
  }, [wallet.id]);

  async function handleLoadBalance() {
    setLoading(true);
    setError(null);

    try {
      const response = await getWalletBalance(wallet.id);

      const value = response.data.balance;

      setBalance(value);

      localStorage.setItem(
        `wallet_balance_${wallet.id}`,
        JSON.stringify(value)
      );
    } catch {
      setError("Erro ao buscar saldo");
    } finally {
      setLoading(false);
    }
  }

  const price = prices?.[wallet.network];
  const valueUsd = balance !== null && price ? balance * price.usd : null;

  return (
    <li className="rounded-lg border border-slate-800 bg-slate-950 p-4">
      <div className="mb-2 break-all font-medium text-slate-50">
        {wallet.address}
      </div>

      <div className="mb-3">
        <span
          className="rounded-full px-2.5 py-0.5 text-xs font-medium text-white"
          style={{ backgroundColor: networkConfig.color }}
        >
          {networkConfig.label}
        </span>
      </div>

      {balance !== null && (
        <div className="mb-1 text-slate-200">
          Saldo: <strong>{balance}</strong> {networkConfig.symbol}
        </div>
      )}

      {valueUsd !== null && (
        <div className="mb-3 flex items-center gap-2 text-sm text-slate-400">
          <span>
            ≈ $
            {valueUsd.toLocaleString("en-US", { maximumFractionDigits: 2 })}{" "}
            USD
          </span>
          <PriceChangeBadge change={price.change_24h} />
        </div>
      )}

      {error && <div className="mb-3 text-sm text-red-400">{error}</div>}

      <button
        onClick={handleLoadBalance}
        disabled={loading}
        className="rounded-md border border-slate-700 px-3 py-1.5 text-sm text-slate-200 hover:bg-slate-800 disabled:opacity-60"
      >
        {loading
          ? "Consultando..."
          : balance === null
          ? "Ver saldo"
          : "Atualizar saldo"}
      </button>
    </li>
  );
}

export default WalletItem;
