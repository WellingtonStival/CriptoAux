import { useState, useEffect } from "react";
import { Link } from "react-router-dom";
import { Pencil, Trash2, RefreshCw, History, Check, X } from "lucide-react";
import { getWalletBalance, deleteWallet, renameWallet } from "../services/api";
import { NETWORKS } from "../config/networks";
import PriceChangeBadge from "./PriceChangeBadge";
import { Card, CardContent } from "./ui/card";
import { Badge } from "./ui/badge";
import { Button } from "./ui/button";
import { Input } from "./ui/input";

const REFRESH_INTERVAL_MS = 60_000;

function WalletItem({ wallet, prices, onDeleted, onBalanceLoaded, onRenamed }) {
  const networkConfig = NETWORKS[wallet.network] ?? {
    label: wallet.network,
    symbol: "",
    color: "#999",
  };
  const [loading, setLoading] = useState(false);
  const [balance, setBalance] = useState(null);
  const [error, setError] = useState(null);
  const [deleting, setDeleting] = useState(false);
  const [confirmingDelete, setConfirmingDelete] = useState(false);
  const [editingName, setEditingName] = useState(false);
  const [nameDraft, setNameDraft] = useState(wallet.name ?? "");
  const [savingName, setSavingName] = useState(false);

  useEffect(() => {
    const cached = localStorage.getItem(`wallet_balance_${wallet.id}`);
    if (cached) {
      setBalance(JSON.parse(cached));
    }

    loadBalance();

    const interval = setInterval(loadBalance, REFRESH_INTERVAL_MS);
    return () => clearInterval(interval);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [wallet.id]);

  async function loadBalance() {
    setLoading(true);
    setError(null);

    try {
      const response = await getWalletBalance(wallet.id);
      const value = response.data.balance;

      setBalance(value);
      onBalanceLoaded?.(wallet.id, value);
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

  async function handleConfirmDelete() {
    setDeleting(true);
    setError(null);

    try {
      await deleteWallet(wallet.id);
      localStorage.removeItem(`wallet_balance_${wallet.id}`);
      onDeleted(wallet.id);
    } catch {
      setError("Não foi possível remover a carteira.");
      setDeleting(false);
      setConfirmingDelete(false);
    }
  }

  function handleStartEditingName() {
    setNameDraft(wallet.name ?? "");
    setEditingName(true);
  }

  async function handleSaveName() {
    setSavingName(true);
    setError(null);

    try {
      const response = await renameWallet(wallet.id, nameDraft.trim());
      onRenamed?.(wallet.id, response.data.name);
      setEditingName(false);
    } catch {
      setError("Não foi possível salvar o nome.");
    } finally {
      setSavingName(false);
    }
  }

  const price = prices?.[wallet.network];
  const valueUsd = balance !== null && price ? balance * price.usd : null;

  return (
    <Card>
      <CardContent className="pt-4">
        <div className="mb-2 flex items-start justify-between gap-2">
          <div className="min-w-0 flex-1">
            {editingName ? (
              <div className="flex flex-wrap items-center gap-1.5">
                <Input
                  type="text"
                  value={nameDraft}
                  onChange={(event) => setNameDraft(event.target.value)}
                  placeholder="Nome da carteira"
                  autoComplete="off"
                  disabled={savingName}
                  className="h-8 min-w-0 flex-1 text-sm"
                />
                <Button
                  size="icon"
                  onClick={handleSaveName}
                  disabled={savingName}
                  className="size-8"
                >
                  <Check className="size-4" />
                </Button>
                <Button
                  variant="outline"
                  size="icon"
                  onClick={() => setEditingName(false)}
                  disabled={savingName}
                  className="size-8"
                >
                  <X className="size-4" />
                </Button>
              </div>
            ) : (
              <div className="flex items-center gap-1.5">
                {wallet.name ? (
                  <div className="min-w-0">
                    <div className="truncate font-medium text-foreground">
                      {wallet.name}
                    </div>
                    <div className="truncate text-xs text-muted-foreground">
                      {wallet.address}
                    </div>
                  </div>
                ) : (
                  <div className="break-all font-medium text-foreground">
                    {wallet.address}
                  </div>
                )}

                <button
                  onClick={handleStartEditingName}
                  title="Editar nome"
                  className="shrink-0 text-muted-foreground hover:text-foreground"
                >
                  <Pencil className="size-3.5" />
                </button>
              </div>
            )}
          </div>

          {confirmingDelete ? (
            <div className="flex shrink-0 items-center gap-1.5 text-xs">
              <span className="text-muted-foreground">Remover?</span>
              <Button
                variant="destructive"
                size="sm"
                onClick={handleConfirmDelete}
                disabled={deleting}
                className="h-7 px-2"
              >
                {deleting ? "Removendo..." : "Sim"}
              </Button>
              <Button
                variant="outline"
                size="sm"
                onClick={() => setConfirmingDelete(false)}
                disabled={deleting}
                className="h-7 px-2"
              >
                Cancelar
              </Button>
            </div>
          ) : (
            <Button
              variant="outline"
              size="icon"
              onClick={() => setConfirmingDelete(true)}
              title="Remover carteira"
              className="size-8 shrink-0 text-destructive hover:bg-destructive-muted"
            >
              <Trash2 className="size-4" />
            </Button>
          )}
        </div>

        <div className="mb-3">
          <Badge
            className="text-white"
            style={{ backgroundColor: networkConfig.color }}
          >
            {networkConfig.label}
          </Badge>
        </div>

        {loading && balance === null && (
          <div className="mb-3 text-sm text-muted-foreground">
            Consultando saldo...
          </div>
        )}

        {balance !== null && (
          <div className="mb-1 text-foreground">
            Saldo: <strong>{balance}</strong> {networkConfig.symbol}
          </div>
        )}

        {valueUsd !== null && (
          <div className="mb-3 flex items-center gap-2 text-sm text-muted-foreground">
            <span>
              ≈ $
              {valueUsd.toLocaleString("en-US", { maximumFractionDigits: 2 })}{" "}
              USD
            </span>
            <PriceChangeBadge change={price.change_24h} />
          </div>
        )}

        {error && <div className="mb-3 text-sm text-destructive">{error}</div>}

        <div className="flex gap-2">
          <Button variant="outline" size="sm" onClick={loadBalance} disabled={loading}>
            <RefreshCw className={loading ? "size-3.5 animate-spin" : "size-3.5"} />
            {loading ? "Atualizando..." : "Atualizar saldo"}
          </Button>

          <Button variant="outline" size="sm" asChild>
            <Link to={`/wallets/${wallet.id}/history`}>
              <History className="size-3.5" />
              Ver histórico
            </Link>
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}

export default WalletItem;
