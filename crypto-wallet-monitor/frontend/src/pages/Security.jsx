import { useState } from "react";
import { ShieldAlert, ShieldCheck, ExternalLink, RefreshCw, AlertCircle } from "lucide-react";
import Layout from "../components/Layout";
import StatCard from "../components/StatCard";
import { getSecurityApprovals } from "../services/api";
import { NETWORKS } from "../config/networks";
import { Card, CardContent } from "../components/ui/card";
import { Badge } from "../components/ui/badge";
import { Button } from "../components/ui/button";
import { Alert, AlertDescription } from "../components/ui/alert";
import { Skeleton } from "../components/ui/skeleton";
import { InfoTooltip } from "../components/ui/info-tooltip";

const RISK_BADGES = {
  alta: { label: "Risco alto", variant: "destructive" },
  media: { label: "Risco médio", variant: "warning" },
  baixa: { label: "Risco baixo", variant: "success" },
};

function truncateAddress(address) {
  if (!address || address.length <= 12) return address;
  return `${address.slice(0, 6)}...${address.slice(-4)}`;
}

function ApprovalRow({ approval }) {
  const networkConfig = NETWORKS[approval.network];
  const risk = RISK_BADGES[approval.risk] ?? RISK_BADGES.baixa;
  const explorerUrl = networkConfig?.explorerUrl
    ? `${networkConfig.explorerUrl}${approval.spender_address}`
    : null;

  return (
    <div className="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
      <div className="min-w-0 flex-1">
        <div className="flex flex-wrap items-center gap-1.5">
          <span className="font-medium text-foreground">
            {approval.token_symbol ?? truncateAddress(approval.token_address)}
          </span>
          <Badge
            className="text-white"
            style={{ backgroundColor: networkConfig?.color ?? "#64748b" }}
          >
            {networkConfig?.label ?? approval.network}
          </Badge>
          <Badge variant={risk.variant}>{risk.label}</Badge>
        </div>
        <div className="mt-1 text-xs text-muted-foreground">
          Wallet: {approval.wallet_label} · Contrato aprovado:{" "}
          {approval.spender_name ?? truncateAddress(approval.spender_address)}
          {explorerUrl && (
            <a
              href={explorerUrl}
              target="_blank"
              rel="noreferrer"
              className="ml-1 inline-flex items-center text-primary hover:underline"
            >
              ver <ExternalLink className="ml-0.5 size-3" />
            </a>
          )}
        </div>
      </div>

      <div className="shrink-0 text-right text-sm">
        <div className={approval.is_unlimited ? "font-medium text-destructive" : "text-foreground"}>
          {approval.is_unlimited ? "Ilimitado" : approval.approved_amount}
        </div>
        {!approval.is_open_source && (
          <div className="text-xs text-muted-foreground">contrato não verificado</div>
        )}
      </div>
    </div>
  );
}

function Security() {
  const [approvals, setApprovals] = useState(null);
  const [summary, setSummary] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [scanned, setScanned] = useState(false);

  async function handleScan() {
    setLoading(true);
    setError(null);

    try {
      const response = await getSecurityApprovals();
      setApprovals(response.data.approvals ?? []);
      setSummary(response.data.summary ?? null);
      setScanned(true);
    } catch {
      setError("Não foi possível verificar as aprovações agora.");
    } finally {
      setLoading(false);
    }
  }

  return (
    <Layout>
      <h1 className="mb-2 flex items-center gap-2 text-2xl font-bold text-foreground">
        <ShieldAlert className="size-6" />
        Segurança
        <InfoTooltip>
          Quando você usa um app descentralizado (DEX, empréstimo, etc.),
          normalmente precisa "aprovar" que o contrato dele movimente seus
          tokens — essa permissão fica ativa até você revogar, mesmo
          depois de parar de usar o app. Aprovações "ilimitadas" pra
          contratos não verificados são o principal jeito de wallets
          serem esvaziadas por golpes. Aqui você só visualiza — pra
          revogar, use o link do explorer de cada aprovação.
        </InfoTooltip>
      </h1>
      <p className="mb-6 text-sm text-muted-foreground">
        Verifica quais contratos têm permissão pra movimentar tokens das
        suas wallets Ethereum, Polygon e BNB Chain — Solana ainda não é
        suportado aqui.
      </p>

      {!scanned && !loading && (
        <Card>
          <CardContent className="flex flex-col items-start gap-3 pt-4">
            <p className="text-muted-foreground">
              Isso consulta um serviço externo (GoPlus Security) pra cada
              uma das suas wallets — pode levar alguns segundos.
            </p>
            <Button onClick={handleScan}>
              <ShieldAlert className="size-3.5" />
              Verificar aprovações
            </Button>
          </CardContent>
        </Card>
      )}

      {loading && (
        <div className="flex flex-col gap-3">
          <Skeleton className="h-16 w-full" />
          <Skeleton className="h-16 w-full" />
          <Skeleton className="h-16 w-full" />
        </div>
      )}

      {error && (
        <Alert variant="destructive" className="mb-6">
          <AlertCircle />
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      {scanned && !loading && !error && (
        <>
          <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div className="grid flex-1 grid-cols-3 gap-3">
              <StatCard icon={ShieldAlert} label="Aprovações" value={summary?.total ?? 0} />
              <StatCard icon={ShieldAlert} label="Risco alto" value={summary?.high_risk ?? 0} />
              <StatCard icon={ShieldCheck} label="Wallets verificadas" value={summary?.scanned_wallets ?? 0} />
            </div>
          </div>

          <Button variant="outline" size="sm" onClick={handleScan} className="mb-4">
            <RefreshCw className="size-3.5" />
            Verificar de novo
          </Button>

          {summary?.scanned_wallets === 0 && (
            <Card>
              <CardContent className="pt-4 text-muted-foreground">
                Nenhuma wallet Ethereum, Polygon ou BNB Chain cadastrada
                ainda.
              </CardContent>
            </Card>
          )}

          {summary?.scanned_wallets > 0 && approvals.length === 0 && (
            <Card>
              <CardContent className="flex items-center gap-2 pt-4 text-success">
                <ShieldCheck className="size-4" />
                Nenhuma aprovação de token encontrada nas suas wallets.
              </CardContent>
            </Card>
          )}

          {approvals.length > 0 && (
            <Card className="overflow-hidden py-0">
              <div className="divide-y divide-border">
                {approvals.map((approval, index) => (
                  <ApprovalRow key={index} approval={approval} />
                ))}
              </div>
            </Card>
          )}
        </>
      )}
    </Layout>
  );
}

export default Security;
