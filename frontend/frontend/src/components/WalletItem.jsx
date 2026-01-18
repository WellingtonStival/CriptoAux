import { useState, useEffect } from "react";
import { getWalletBalance } from "../services/walletService";
import { NETWORKS } from "../config/networks";

function WalletItem({ wallet }) {
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
    } catch (err) {
      setError("Erro ao buscar saldo");
    } finally {
      setLoading(false);
    }
  }

  return (
    <li
      style={{
        marginBottom: 16,
        padding: 12,
        border: "1px solid #333",
        borderRadius: 8,
      }}
    >
      <div style={{ marginBottom: 6 }}>
        <strong>{wallet.address}</strong>
      </div>

      <div style={{ marginBottom: 8 }}>
        <span
          style={{
            background: networkConfig.color,
            color: "#fff",
            padding: "2px 8px",
            borderRadius: 12,
            fontSize: 12,
          }}
        >
          {networkConfig.label}
        </span>
      </div>

      {balance !== null && (
        <div style={{ marginBottom: 6 }}>
          Saldo: <strong>{balance}</strong> {networkConfig.symbol}
        </div>
      )}

      {error && (
        <div style={{ color: "red", marginBottom: 6 }}>{error}</div>
      )}

      <button onClick={handleLoadBalance} disabled={loading}>
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