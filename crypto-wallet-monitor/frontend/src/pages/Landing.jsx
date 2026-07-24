import { Link } from "react-router-dom";
import {
  Wallet,
  Coins,
  BellRing,
  ShieldAlert,
  Lightbulb,
  Scale,
  ShieldCheck,
  ArrowRight,
} from "lucide-react";
import { Button } from "../components/ui/button";
import { Card, CardContent } from "../components/ui/card";
import logoIcon from "../assets/logo-icon.png";
import logoWordmark from "../assets/logo-wordmark.png";

const FEATURES = [
  {
    icon: Wallet,
    title: "Multi-blockchain",
    description:
      "Ethereum, Polygon, BNB Chain, Avalanche, Arbitrum, Solana e Bitcoin numa única tela, com saldo consultado direto na blockchain.",
  },
  {
    icon: Coins,
    title: "Tokens e ativos",
    description:
      "Descubra automaticamente os tokens ERC-20 e SPL das suas wallets, com preço e valor consolidado.",
  },
  {
    icon: BellRing,
    title: "Alertas via Telegram",
    description:
      "Receba um aviso no Telegram quando o saldo cair, o patrimônio variar ou o preço de uma moeda mudar.",
  },
  {
    icon: ShieldAlert,
    title: "Scanner de segurança",
    description:
      "Veja quais contratos têm permissão pra movimentar seus tokens e identifique aprovações arriscadas.",
  },
  {
    icon: Lightbulb,
    title: "Insights automáticos",
    description:
      "Frases diretas sobre concentração, variação e desempenho do seu patrimônio, sem precisar interpretar gráfico.",
  },
  {
    icon: Scale,
    title: "Você vs. Bitcoin",
    description:
      "Compare o desempenho real da sua carteira com o que teria sido se estivesse 100% em Bitcoin.",
  },
];

const STEPS = [
  {
    step: "1",
    title: "Cadastre uma wallet pública",
    description:
      "Só o endereço — nunca pedimos chave privada ou frase de recuperação.",
  },
  {
    step: "2",
    title: "Acompanhe automaticamente",
    description:
      "Saldo, tokens e valorização são atualizados sozinhos, com histórico ao longo do tempo.",
  },
  {
    step: "3",
    title: "Receba alertas",
    description:
      "Configure o que importa pra você e seja avisado no Telegram, sem precisar abrir a tela.",
  },
];

function Landing() {
  return (
    <div className="min-h-screen bg-background">
      <header className="mx-auto flex max-w-6xl items-center justify-between px-6 py-6">
        <span className="flex items-center gap-2">
          <img src={logoIcon} alt="" className="size-[30px]" />
          <img
            src={logoWordmark}
            alt="Nexfolio"
            className="hidden h-[21px] sm:block"
          />
        </span>

        <div className="flex items-center gap-2">
          <Button variant="ghost" size="sm" asChild>
            <Link to="/login">Entrar</Link>
          </Button>
          <Button size="sm" asChild>
            <Link to="/register">Criar conta</Link>
          </Button>
        </div>
      </header>

      <section className="relative overflow-hidden px-6 pb-20 pt-16 sm:pt-24">
        <div
          className="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[480px]"
          style={{
            background:
              "radial-gradient(600px circle at 50% 0%, color-mix(in srgb, var(--color-primary) 18%, transparent), transparent 70%)",
          }}
        />

        <div className="mx-auto max-w-3xl text-center">
          <span className="mb-4 inline-flex items-center gap-1.5 rounded-full border border-border bg-card px-3 py-1 text-xs font-medium text-muted-foreground">
            <ShieldCheck className="size-3.5 text-success" />
            Somente leitura — nunca pedimos sua chave privada
          </span>

          <h1 className="text-4xl font-bold tracking-tight text-foreground sm:text-5xl">
            Todo o seu patrimônio em cripto, numa única tela.
          </h1>

          <p className="mx-auto mt-4 max-w-xl text-lg text-muted-foreground">
            Monitore carteiras em várias blockchains, acompanhe a
            valorização e receba alertas — sem entregar custódia dos
            seus ativos.
          </p>

          <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
            <Button size="lg" asChild>
              <Link to="/register">
                Criar conta grátis
                <ArrowRight className="size-4" />
              </Link>
            </Button>
            <Button size="lg" variant="outline" asChild>
              <Link to="/login">Já tenho conta</Link>
            </Button>
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-6xl px-6 pb-20">
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {FEATURES.map((feature) => {
            const Icon = feature.icon;

            return (
              <Card key={feature.title}>
                <CardContent className="pt-6">
                  <div className="mb-3 flex size-10 items-center justify-center rounded-lg bg-primary/15 text-primary">
                    <Icon className="size-5" />
                  </div>
                  <h3 className="mb-1.5 font-semibold text-foreground">
                    {feature.title}
                  </h3>
                  <p className="text-sm text-muted-foreground">
                    {feature.description}
                  </p>
                </CardContent>
              </Card>
            );
          })}
        </div>
      </section>

      <section className="border-t border-border bg-card/40 px-6 py-20">
        <div className="mx-auto max-w-4xl">
          <h2 className="mb-10 text-center text-2xl font-bold text-foreground">
            Como funciona
          </h2>

          <div className="grid gap-8 sm:grid-cols-3">
            {STEPS.map((item) => (
              <div key={item.step} className="text-center">
                <div className="mx-auto mb-3 flex size-9 items-center justify-center rounded-full bg-primary text-sm font-bold text-primary-foreground">
                  {item.step}
                </div>
                <h3 className="mb-1.5 font-semibold text-foreground">
                  {item.title}
                </h3>
                <p className="text-sm text-muted-foreground">
                  {item.description}
                </p>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section className="px-6 py-20 text-center">
        <h2 className="mb-4 text-2xl font-bold text-foreground">
          Comece a acompanhar seu patrimônio agora
        </h2>
        <Button size="lg" asChild>
          <Link to="/register">
            Criar conta grátis
            <ArrowRight className="size-4" />
          </Link>
        </Button>
      </section>

      <footer className="border-t border-border px-6 py-8">
        <div className="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 sm:flex-row">
          <span className="flex items-center gap-2">
            <img src={logoIcon} alt="" className="size-[22px]" />
            <img src={logoWordmark} alt="Nexfolio" className="h-[15px]" />
          </span>
          <p className="text-xs text-muted-foreground">
            Monitoramento de patrimônio em cripto. Sem custódia, sem trading.
          </p>
        </div>
      </footer>
    </div>
  );
}

export default Landing;
