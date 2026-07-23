import { useState } from "react";
import { NavLink, useNavigate } from "react-router-dom";
import {
  LayoutDashboard,
  Wallet,
  Coins,
  Newspaper,
  Gauge,
  BellRing,
  ShieldAlert,
  UserRound,
  LogOut,
  Menu,
  X,
} from "lucide-react";
import { useAuth } from "../context/AuthContext";
import { cn } from "../lib/utils";
import logoIcon from "../assets/logo-icon.png";
import logoWordmark from "../assets/logo-wordmark.png";

const NAV_LINKS = [
  { to: "/", label: "Dashboard", icon: LayoutDashboard, end: true },
  { to: "/wallets", label: "Minhas Wallets", icon: Wallet },
  { to: "/ativos", label: "Ativos", icon: Coins },
  { to: "/mercado", label: "Mercado", icon: Gauge },
  { to: "/alertas", label: "Alertas", icon: BellRing },
  { to: "/seguranca", label: "Segurança", icon: ShieldAlert },
  { to: "/noticias", label: "Notícias", icon: Newspaper },
];

function Layout({ children }) {
  const { logout } = useAuth();
  const navigate = useNavigate();
  const [mobileOpen, setMobileOpen] = useState(false);

  function handleLogout() {
    logout();
    navigate("/login");
  }

  return (
    <div className="min-h-screen bg-background lg:flex">
      {/* barra fina só no mobile - a sidebar de verdade fica escondida até abrir */}
      <div className="sticky top-0 z-20 flex items-center justify-between border-b border-border bg-background/90 px-4 py-3 backdrop-blur lg:hidden">
        <span className="flex items-center gap-2">
          <img src={logoIcon} alt="" className="size-[26px]" />
          <img src={logoWordmark} alt="Nexfolio" className="h-[18px]" />
        </span>
        <button
          onClick={() => setMobileOpen(true)}
          className="flex size-8 items-center justify-center rounded-md text-muted-foreground hover:text-foreground"
          aria-label="Abrir menu"
        >
          <Menu className="size-5" />
        </button>
      </div>

      {mobileOpen && (
        <div
          className="fixed inset-0 z-30 bg-black/60 lg:hidden"
          onClick={() => setMobileOpen(false)}
        />
      )}

      <aside
        className={cn(
          "fixed inset-y-0 left-0 z-40 flex w-64 shrink-0 flex-col border-r border-border bg-card transition-transform lg:sticky lg:top-0 lg:h-screen lg:translate-x-0",
          mobileOpen ? "translate-x-0" : "-translate-x-full"
        )}
      >
        <div className="flex items-center justify-between gap-2 px-5 py-5">
          <span className="flex items-center gap-2">
            <img src={logoIcon} alt="" className="size-[30px]" />
            <img src={logoWordmark} alt="Nexfolio" className="h-[21px]" />
          </span>
          <button
            onClick={() => setMobileOpen(false)}
            className="flex size-7 items-center justify-center text-muted-foreground hover:text-foreground lg:hidden"
            aria-label="Fechar menu"
          >
            <X className="size-4" />
          </button>
        </div>

        <nav className="flex flex-1 flex-col gap-1 overflow-y-auto px-3">
          {NAV_LINKS.map((link) => {
            const Icon = link.icon;

            return (
              <NavLink
                key={link.to}
                to={link.to}
                end={link.end}
                onClick={() => setMobileOpen(false)}
                className={({ isActive }) =>
                  cn(
                    "flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors",
                    isActive
                      ? "bg-muted text-foreground"
                      : "text-muted-foreground hover:text-foreground"
                  )
                }
              >
                <Icon className="size-4" />
                {link.label}
              </NavLink>
            );
          })}
        </nav>

        <div className="flex flex-col gap-1 border-t border-border px-3 py-3">
          <NavLink
            to="/conta"
            onClick={() => setMobileOpen(false)}
            className={({ isActive }) =>
              cn(
                "flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors",
                isActive
                  ? "bg-muted text-foreground"
                  : "text-muted-foreground hover:text-foreground"
              )
            }
          >
            <UserRound className="size-4" />
            Minha Conta
          </NavLink>

          <button
            onClick={handleLogout}
            className="flex items-center gap-3 rounded-md px-3 py-2 text-left text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
          >
            <LogOut className="size-4" />
            Sair
          </button>
        </div>
      </aside>

      <main className="min-w-0 flex-1 px-6 py-8 lg:px-10">{children}</main>
    </div>
  );
}

export default Layout;
