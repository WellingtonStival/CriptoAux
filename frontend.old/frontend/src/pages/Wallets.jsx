import { useEffect, useState } from "react";
import WalletList from "../components/WalletList";
import { getWallets } from "../services/api";

function Wallets() {
  const [wallets, setWallets] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    async function loadWallets() {
      try {
        setLoading(true);
        const response = await getWallets();
        setWallets(response.data.data ?? []);
      } catch (err) {
        setError("Erro ao carregar suas carteiras");
      } finally {
        setLoading(false);
      }
    }

    loadWallets();
  }, []);

  return (
    <div style={{ padding: 20 }}>
      <h1>Minhas Wallets</h1>

      {loading && <p>Carregando carteiras...</p>}

      {error && <p style={{ color: "red" }}>{error}</p>}

      {!loading && !error && wallets.length === 0 && (
        <p>Nenhuma carteira cadastrada.</p>
      )}

      {!loading && !error && wallets.length > 0 && (
        <WalletList wallets={wallets} />
      )}
    </div>
  );
}

export default Wallets;
