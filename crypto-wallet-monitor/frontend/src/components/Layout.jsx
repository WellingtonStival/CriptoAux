import { Link, useNavigate } from "react-router-dom";
import { useAuth } from "../context/AuthContext";

function Layout({ children }) {
  const { logout } = useAuth();
  const navigate = useNavigate();

  function handleLogout() {
    logout();
    navigate("/login");
  }

  return (
    <div className="min-h-screen bg-slate-900">
      <header className="flex items-center justify-between border-b border-slate-800 px-6 py-4">
        <div className="flex items-center gap-6">
          <span className="text-lg font-semibold text-slate-50">
            Crypto Wallet Monitor
          </span>

          <nav className="flex gap-4 text-sm">
            <Link to="/" className="text-slate-300 hover:text-slate-50">
              Dashboard
            </Link>
            <Link to="/wallets" className="text-slate-300 hover:text-slate-50">
              Minhas Wallets
            </Link>
            <Link to="/noticias" className="text-slate-300 hover:text-slate-50">
              Notícias
            </Link>
          </nav>
        </div>

        <button
          onClick={handleLogout}
          className="rounded-md border border-slate-700 px-3 py-1.5 text-sm text-slate-200 hover:bg-slate-800"
        >
          Sair
        </button>
      </header>

      <main className="mx-auto max-w-2xl px-6 py-8">{children}</main>
    </div>
  );
}

export default Layout;
