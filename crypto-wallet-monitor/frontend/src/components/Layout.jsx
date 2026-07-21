import { useNavigate } from "react-router-dom";
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
        <span className="text-lg font-semibold text-slate-50">
          Crypto Wallet Monitor
        </span>

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
