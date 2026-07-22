import { useState, useEffect } from "react";
import { Link } from "react-router-dom";
import { getWalletBalance, deleteWallet, renameWallet } from "../services/api";
import { NETWORKS } from "../config/networks";
import PriceChangeBadge from "./PriceChangeBadge";

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
    <li className="rounded-lg border border-slate-800 bg-slate-950 p-4">
      <div className="mb-2 flex items-start justify-between gap-2">
        <div className="min-w-0 flex-1">
          {editingName ? (
            <div className="flex flex-wrap items-center gap-1.5">
              <input
                type="text"
                value={nameDraft}
                onChange={(event) => setNameDraft(event.target.value)}
                placeholder="Nome da carteira"
                autoComplete="off"
                disabled={savingName}
                className="min-w-0 flex-1 rounded-md border border-slate-700 bg-slate-900 px-2 py-1 text-sm text-slate-50 placeholder:text-slate-500 focus:border-slate-500 focus:outline-none disabled:opacity-60"
              />
              <button
                onClick={handleSaveName}
                disabled={savingName}
                className="rounded-md bg-indigo-600 px-2 py-1 text-xs text-white hover:bg-indigo-500 disabled:opacity-60"
              >
                {savingName ? "Salvando..." : "Salvar"}
              </button>
              <button
                onClick={() => setEditingName(false)}
                disabled={savingName}
                className="rounded-md border border-slate-700 px-2 py-1 text-xs text-slate-300 hover:bg-slate-800 disabled:opacity-60"
              >
                Cancelar
              </button>
            </div>
          ) : (
            <div className="flex items-center gap-1.5">
              {wallet.name ? (
                <div className="min-w-0">
                  <div className="truncate font-medium text-slate-50">
                    {wallet.name}
                  </div>
                  <div className="truncate text-xs text-slate-500">
                    {wallet.address}
                  </div>
                </div>
              ) : (
                <div className="break-all font-medium text-slate-50">
                  {wallet.address}
                </div>
              )}

              <button
                onClick={handleStartEditingName}
                title="Editar nome"
                className="shrink-0 text-xs text-slate-500 hover:text-slate-300"
              >
                ✎
              </button>
            </div>
          )}
        </div>

        {confirmingDelete ? (
          <div className="flex shrink-0 items-center gap-1.5 text-xs">
            <span className="text-slate-400">Remover?</span>
            <button
              onClick={handleConfirmDelete}
              disabled={deleting}
              className="rounded-md bg-red-500/15 px-2 py-1 text-red-400 hover:bg-red-500/25 disabled:opacity-60"
            >
              {deleting ? "Removendo..." : "Sim"}
            </button>
            <button
              onClick={() => setConfirmingDelete(false)}
              disabled={deleting}
              className="rounded-md border border-slate-700 px-2 py-1 text-slate-300 hover:bg-slate-800 disabled:opacity-60"
            >
              Cancelar
            </button>
          </div>
        ) : (
          <button
            onClick={() => setConfirmingDelete(true)}
            title="Remover carteira"
            className="shrink-0 rounded-md border border-slate-700 px-2 py-1 text-xs text-red-400 hover:bg-red-500/10"
          >
            Remover
          </button>
        )}
      </div>

      <div className="mb-3">
        <span
          className="rounded-full px-2.5 py-0.5 text-xs font-medium text-white"
          style={{ backgroundColor: networkConfig.color }}
        >
          {networkConfig.label}
        </span>
      </div>

      {loading && balance === null && (
        <div className="mb-3 text-sm text-slate-400">
          Consultando saldo...
        </div>
      )}

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

      <div className="flex gap-2">
        <button
          onClick={loadBalance}
          disabled={loading}
          className="rounded-md border border-slate-700 px-3 py-1.5 text-sm text-slate-200 hover:bg-slate-800 disabled:opacity-60"
        >
          {loading ? "Atualizando..." : "Atualizar saldo"}
        </button>

        <Link
          to={`/wallets/${wallet.id}/history`}
          className="rounded-md border border-slate-700 px-3 py-1.5 text-sm text-slate-200 hover:bg-slate-800"
        >
          Ver histórico
        </Link>
      </div>
    </li>
  );
}

export default WalletItem;
