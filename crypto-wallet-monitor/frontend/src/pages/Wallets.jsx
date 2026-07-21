import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import WalletForm from "../components/WalletForm";
import WalletList from "../components/WalletList";
import { getWallets } from "../services/api";
import { useAuth } from "../context/AuthContext";

function Wallets() {
  const [wallets, setWallets] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const { logout } = useAuth();
  const navigate = useNavigate();

  function handleLogout() {
    logout();
    navigate("/login");
  }

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
    <div style={{ padding: 20 }}>
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
        <h1>Minhas Wallets</h1>
        <button onClick={handleLogout}>Sair</button>
      </div>

      <WalletForm onCreated={handleWalletCreated} />

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
