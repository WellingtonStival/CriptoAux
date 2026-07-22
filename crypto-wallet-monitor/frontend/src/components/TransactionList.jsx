import { useEffect, useState } from "react";
import { ArrowDownLeft, ArrowUpRight, ExternalLink } from "lucide-react";
import { getWalletTransactions } from "../services/api";
import { formatDateTime } from "../utils/format";
import { NETWORKS } from "../config/networks";
import { Skeleton } from "./ui/skeleton";
import { Alert, AlertDescription } from "./ui/alert";

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
    return (
      <div className="flex flex-col gap-2">
        {Array.from({ length: 3 }).map((_, index) => (
          <Skeleton key={index} className="h-10 w-full" />
        ))}
      </div>
    );
  }

  if (error) {
    return (
      <Alert variant="destructive">
        <AlertDescription>{error}</AlertDescription>
      </Alert>
    );
  }

  if (!data?.supported) {
    return (
      <p className="text-muted-foreground">
        Histórico de transações ainda não disponível para{" "}
        {NETWORKS[data?.network]?.label ?? data?.network}.
      </p>
    );
  }

  if (data.transactions.length === 0) {
    return <p className="text-muted-foreground">Nenhuma transação encontrada.</p>;
  }

  return (
    <ul className="flex flex-col gap-2">
      {data.transactions.map((tx) => {
        const isIncoming = tx.direction === "in";

        return (
          <li
            key={tx.hash}
            className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border px-3 py-2 text-sm"
          >
            <span
              className={
                isIncoming
                  ? "flex items-center gap-1 font-medium text-success"
                  : "flex items-center gap-1 font-medium text-destructive"
              }
            >
              {isIncoming ? (
                <ArrowDownLeft className="size-3.5" />
              ) : (
                <ArrowUpRight className="size-3.5" />
              )}
              {isIncoming ? "Recebido" : "Enviado"}
            </span>

            <span className="text-foreground">
              {tx.amount} {symbol}
            </span>

            <span className="text-muted-foreground">{formatDateTime(tx.timestamp)}</span>

            <a
              href={tx.explorer_url}
              target="_blank"
              rel="noreferrer"
              className="inline-flex items-center gap-1 text-primary hover:underline"
            >
              Ver
              <ExternalLink className="size-3.5" />
            </a>
          </li>
        );
      })}
    </ul>
  );
}

export default TransactionList;
