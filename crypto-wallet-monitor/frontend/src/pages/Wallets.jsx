import { useEffect, useState } from "react";
import { Inbox } from "lucide-react";
import WalletForm from "../components/WalletForm";
import WalletList from "../components/WalletList";
import Layout from "../components/Layout";
import { Card, CardContent } from "../components/ui/card";
import { Alert, AlertDescription } from "../components/ui/alert";
import { Skeleton } from "../components/ui/skeleton";
import { getWallets, getPrices } from "../services/api";

const PRICES_REFRESH_INTERVAL_MS = 60_000;

function Wallets() {
  const [wallets, setWallets] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [prices, setPrices] = useState(null);

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
  }

  function handleWalletRenamed(walletId, name) {
    setWallets((currentWallets) =>
      currentWallets.map((wallet) =>
        wallet.id === walletId ? { ...wallet, name } : wallet
      )
    );
  }

  return (
    <Layout>
      <h1 className="mb-6 text-2xl font-bold text-foreground">
        Minhas Wallets
      </h1>

      <WalletForm onCreated={handleWalletCreated} />

      {loading && (
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
          {Array.from({ length: 3 }).map((_, index) => (
            <Skeleton key={index} className="h-40 w-full" />
          ))}
        </div>
      )}

      {error && (
        <Alert variant="destructive">
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      {!loading && !error && wallets.length === 0 && (
        <Card>
          <CardContent className="flex flex-col items-start gap-2 pt-4">
            <Inbox className="size-6 text-muted-foreground" />
            <p className="text-muted-foreground">Nenhuma carteira cadastrada.</p>
          </CardContent>
        </Card>
      )}

      {!loading && !error && wallets.length > 0 && (
        <WalletList
          wallets={wallets}
          prices={prices}
          onDeleted={handleWalletDeleted}
          onRenamed={handleWalletRenamed}
        />
      )}
    </Layout>
  );
}

export default Wallets;
