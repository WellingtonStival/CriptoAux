import { useEffect, useState } from "react";
import { getWalletTransactions } from "../services/api";
import { formatDateTime } from "../utils/format";
import { NETWORKS } from "../config/networks";

function TransactionList({ walletId, symbol }) {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [data, setData] = useState(null);

  useEffect(() => {
    let active = true;

    async function load() {
      try {
        setLoading(true);
        setError(null);
        const response = await getWalletTransactions(walletId);
        if (active) setData(response.data);
      } catch {
        if (active) setError("Erro ao carregar transações.");
      } finally {
        if (active) setLoading(false);
      }
    }

    load();

    return () => {
      active = false;
    };
  }, [walletId]);

  if (loading) {
    return <p className="text-slate-400">Carregando transações...</p>;
  }

  if (error) {
    return <p className="text-red-400">{error}</p>;
  }

  if (!data?.supported) {
    return (
      <p className="text-slate-400">
        Histórico de transações ainda não disponível para{" "}
        {NETWORKS[data?.network]?.label ?? data?.network}.
      </p>
    );
  }

  if (data.transactions.length === 0) {
    return <p className="text-slate-400">Nenhuma transação encontrada.</p>;
  }

  return (
    <ul className="flex flex-col gap-2">
      {data.transactions.map((tx) => (
        <li
          key={tx.hash}
          className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-slate-800 px-3 py-2 text-sm"
        >
          <span
            className={
              tx.direction === "in"
                ? "font-medium text-emerald-400"
                : "font-medium text-red-400"
            }
          >
            {tx.direction === "in" ? "↓ Recebido" : "↑ Enviado"}
          </span>

          <span className="text-slate-200">
            {tx.amount} {symbol}
          </span>

          <span className="text-slate-500">{formatDateTime(tx.timestamp)}</span>

          <a
            href={tx.explorer_url}
            target="_blank"
            rel="noreferrer"
            className="text-indigo-400 hover:underline"
          >
            Ver
          </a>
        </li>
      ))}
    </ul>
  );
}

export default TransactionList;
