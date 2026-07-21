import { useEffect, useState } from "react";
import WalletForm from "../components/WalletForm";
import WalletList from "../components/WalletList";
import Layout from "../components/Layout";
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
      } catch {
        setError("Erro ao carregar suas carteiras");
      } finally {
        setLoading(false);
      }
    }

    loadWallets();
  }, []);

  function handleWalletCreated(wallet) {
    setWallets((currentWallets) => [wallet, ...currentWallets]);
  }

  return (
    <Layout>
      <h1 className="mb-6 text-2xl font-bold text-slate-50">
        Minhas Wallets
      </h1>

      <WalletForm onCreated={handleWalletCreated} />

      {loading && <p className="text-slate-400">Carregando carteiras...</p>}

      {error && <p className="text-red-400">{error}</p>}

      {!loading && !error && wallets.length === 0 && (
        <p className="text-slate-400">Nenhuma carteira cadastrada.</p>
      )}

      {!loading && !error && wallets.length > 0 && (
        <WalletList wallets={wallets} />
      )}
    </Layout>
  );
}

export default Wallets;
