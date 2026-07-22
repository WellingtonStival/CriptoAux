import { NavLink, useNavigate } from "react-router-dom";
import { LayoutDashboard, Wallet, Newspaper, LogOut } from "lucide-react";
import { useAuth } from "../context/AuthContext";
import { Button } from "./ui/button";
import { cn } from "../lib/utils";
import logoIcon from "../assets/logo-icon.png";
import logoWordmark from "../assets/logo-wordmark.png";

const NAV_LINKS = [
  { to: "/", label: "Dashboard", icon: LayoutDashboard, end: true },
  { to: "/wallets", label: "Minhas Wallets", icon: Wallet },
  { to: "/noticias", label: "Notícias", icon: Newspaper },
];

function Layout({ children }) {
  const { logout } = useAuth();
  const navigate = useNavigate();

  function handleLogout() {
    logout();
    navigate("/login");
  }

  return (
    <div className="min-h-screen bg-background">
      <header className="sticky top-0 z-10 border-b border-border bg-background/90 backdrop-blur">
        <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-6 py-4">
          <div className="flex flex-wrap items-center gap-4 sm:gap-8">
            <span className="flex shrink-0 items-center gap-2">
              <img src={logoIcon} alt="" className="size-[30px]" />
              <img
                src={logoWordmark}
                alt="Nexfolio"
                className="hidden h-[21px] sm:block"
              />
            </span>

            <nav className="flex flex-wrap gap-1">
              {NAV_LINKS.map((link) => {
                const Icon = link.icon;

                return (
                  <NavLink
                    key={link.to}
                    to={link.to}
                    end={link.end}
                    className={({ isActive }) =>
                      cn(
                        "flex items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium transition-colors",
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
          </div>

          <Button variant="outline" size="sm" onClick={handleLogout}>
            <LogOut className="size-4" />
            Sair
          </Button>
        </div>
      </header>

      <main className="mx-auto max-w-6xl px-6 py-8">{children}</main>
    </div>
  );
}

export default Layout;
