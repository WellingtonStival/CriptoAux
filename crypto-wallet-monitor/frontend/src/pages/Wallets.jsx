import { useEffect, useState } from "react";
import WalletForm from "../components/WalletForm";
import WalletList from "../components/WalletList";
import PricesPanel from "../components/PricesPanel";
import PortfolioSummary from "../components/PortfolioSummary";
import Layout from "../components/Layout";
import { getWallets, getPrices } from "../services/api";

const PRICES_REFRESH_INTERVAL_MS = 60_000;

function Wallets() {
  const [wallets, setWallets] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [prices, setPrices] = useState(null);
  const [balances, setBalances] = useState({});

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

    async function loadPrices() {
      try {
        const response = await getPrices();
        setPrices(response.data);
      } catch {
        // cotações são um extra: se falhar, a tela continua funcional sem elas
      }
    }

    loadWallets();
    loadPrices();

    const interval = setInterval(loadPrices, PRICES_REFRESH_INTERVAL_MS);
    return () => clearInterval(interval);
  }, []);

  function handleWalletCreated(wallet) {
    setWallets((currentWallets) => [wallet, ...currentWallets]);
  }

  function handleWalletDeleted(walletId) {
    setWallets((currentWallets) =>
      currentWallets.filter((wallet) => wallet.id !== walletId)
    );

    setBalances((currentBalances) => {
      const updated = { ...currentBalances };
      delete updated[walletId];
      return updated;
    });
  }

  function handleBalanceLoaded(walletId, balance) {
    setBalances((currentBalances) => ({
      ...currentBalances,
      [walletId]: balance,
    }));
  }

  return (
    <Layout>
      <h1 className="mb-6 text-2xl font-bold text-slate-50">
        Minhas Wallets
      </h1>

      {wallets.length > 0 && (
        <PortfolioSummary
          wallets={wallets}
          balances={balances}
          prices={prices}
        />
      )}

      <PricesPanel prices={prices} />

      <WalletForm onCreated={handleWalletCreated} />

      {loading && <p className="text-slate-400">Carregando carteiras...</p>}

      {error && <p className="text-red-400">{error}</p>}

      {!loading && !error && wallets.length === 0 && (
        <p className="text-slate-400">Nenhuma carteira cadastrada.</p>
      )}

      {!loading && !error && wallets.length > 0 && (
        <WalletList
          wallets={wallets}
          prices={prices}
          onDeleted={handleWalletDeleted}
          onBalanceLoaded={handleBalanceLoaded}
        />
      )}
    </Layout>
  );
}

export default Wallets;
