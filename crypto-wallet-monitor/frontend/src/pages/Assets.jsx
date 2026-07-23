import { useEffect, useMemo, useState } from "react";
import {
  Coins,
  AlertCircle,
  Inbox,
  Search,
  Wallet,
  ShieldQuestion,
  ChevronDown,
  ChevronUp,
} from "lucide-react";
import Layout from "../components/Layout";
import StatCard from "../components/StatCard";
import { getAssets } from "../services/api";
import { formatUsd } from "../utils/format";
import { NETWORKS } from "../config/networks";
import { Card, CardContent } from "../components/ui/card";
import { Badge } from "../components/ui/badge";
import { Alert, AlertDescription } from "../components/ui/alert";
import { Skeleton } from "../components/ui/skeleton";
import { Input } from "../components/ui/input";
import { Select } from "../components/ui/select";
import { Tabs, TabsList, TabsTrigger } from "../components/ui/tabs";
import { InfoTooltip } from "../components/ui/info-tooltip";

const NETWORK_FILTERS = [
  { value: "all", label: "Todas" },
  { value: "ethereum", label: "Ethereum" },
  { value: "polygon", label: "Polygon" },
  { value: "bnb", label: "BNB Chain" },
  { value: "solana", label: "Solana" },
];

const SORT_OPTIONS = [
  { value: "value", label: "Maior valor" },
  { value: "balance", label: "Maior saldo" },
  { value: "name", label: "Nome (A-Z)" },
];

function truncateAddress(address) {
  if (!address || address.length <= 12) return address;
  return `${address.slice(0, 6)}...${address.slice(-4)}`;
}

function TokenLogo({ src, label, color }) {
  const [broken, setBroken] = useState(false);

  if (src && !broken) {
    return (
      <img
        src={src}
        alt=""
        className="size-8 shrink-0 rounded-full bg-muted object-cover"
        onError={() => setBroken(true)}
      />
    );
  }

  return (
    <span
      className="flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold text-white"
      style={{ backgroundColor: color ?? "#64748b" }}
    >
      {(label ?? "?").slice(0, 1).toUpperCase()}
    </span>
  );
}

function AssetRow({ asset, percent }) {
  const networkConfig = NETWORKS[asset.network];
  const label = asset.symbol ?? truncateAddress(asset.contract_address);

  return (
    <div className="grid grid-cols-[1fr_auto] items-center gap-3 px-4 py-3 sm:grid-cols-[1fr_120px_140px_120px]">
      <div className="flex min-w-0 items-center gap-3">
        <TokenLogo src={asset.logo_url} label={label} color={networkConfig?.color} />
        <div className="min-w-0">
          <div className="flex items-center gap-1.5">
            <span className="truncate font-medium text-foreground">{label}</span>
          </div>
          <div className="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
            <Badge
              className="text-white"
              style={{ backgroundColor: networkConfig?.color ?? "#64748b" }}
            >
              {networkConfig?.label ?? asset.network}
            </Badge>
            {asset.name && <span className="truncate">{asset.name}</span>}
            {asset.wallets_count > 1 && <span>· {asset.wallets_count} wallets</span>}
          </div>
        </div>
      </div>

      <div className="hidden text-right text-sm text-foreground sm:block">
        {asset.balance.toLocaleString("en-US", { maximumFractionDigits: 6 })}
      </div>

      <div className="hidden text-right text-sm text-muted-foreground sm:block">
        {asset.price_usd !== null ? formatUsd(asset.price_usd) : "--"}
      </div>

      <div className="text-right">
        <div className="font-medium text-foreground">{formatUsd(asset.value_usd)}</div>
        <div className="text-xs text-muted-foreground">{percent.toFixed(1)}%</div>
      </div>
    </div>
  );
}

function UnpricedRow({ asset }) {
  const networkConfig = NETWORKS[asset.network];
  const label = asset.symbol ?? truncateAddress(asset.contract_address);

  return (
    <div className="flex items-center justify-between gap-3 px-4 py-2.5">
      <div className="flex min-w-0 items-center gap-3">
        <TokenLogo src={asset.logo_url} label={label} color="#475569" />
        <div className="min-w-0">
          <div className="truncate text-sm text-muted-foreground">{label}</div>
          <Badge variant="outline" className="mt-0.5">
            {networkConfig?.label ?? asset.network}
          </Badge>
        </div>
      </div>

      <div className="shrink-0 text-right text-sm text-muted-foreground">
        {asset.balance.toLocaleString("en-US", { maximumFractionDigits: 4 })}
      </div>
    </div>
  );
}

function Assets() {
  const [assets, setAssets] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [search, setSearch] = useState("");
  const [network, setNetwork] = useState("all");
  const [sortBy, setSortBy] = useState("value");
  const [showUnpriced, setShowUnpriced] = useState(false);

  useEffect(() => {
    async function load() {
      try {
        setLoading(true);
        setError(null);
        const response = await getAssets();
        setAssets(response.data.assets ?? []);
      } catch {
        setError("Erro ao carregar seus ativos.");
      } finally {
        setLoading(false);
      }
    }

    load();
  }, []);

  const totalValue = useMemo(
    () => assets.reduce((sum, asset) => sum + (asset.value_usd ?? 0), 0),
    [assets]
  );

  const filtered = useMemo(() => {
    const term = search.trim().toLowerCase();

    return assets.filter((asset) => {
      if (network !== "all" && asset.network !== network) return false;

      if (!term) return true;

      return (
        asset.symbol?.toLowerCase().includes(term) ||
        asset.name?.toLowerCase().includes(term) ||
        asset.contract_address?.toLowerCase().includes(term)
      );
    });
  }, [assets, search, network]);

  const priced = useMemo(() => {
    const list = filtered.filter((asset) => asset.value_usd !== null);

    return [...list].sort((a, b) => {
      if (sortBy === "name") {
        return (a.symbol ?? a.contract_address).localeCompare(b.symbol ?? b.contract_address);
      }
      if (sortBy === "balance") {
        return b.balance - a.balance;
      }
      return b.value_usd - a.value_usd;
    });
  }, [filtered, sortBy]);

  const unpriced = useMemo(
    () => filtered.filter((asset) => asset.value_usd === null),
    [filtered]
  );

  return (
    <Layout>
      <h1 className="mb-2 flex items-center gap-2 text-2xl font-bold text-foreground">
        <Coins className="size-6" />
        Ativos
      </h1>
      <p className="mb-6 text-sm text-muted-foreground">
        Todos os tokens das suas wallets, somados por ativo. Pra descobrir os
        tokens de uma wallet, use o botão "Buscar tokens" no card dela em
        Minhas Wallets.
      </p>

      {!loading && !error && assets.length > 0 && (
        <div className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
          <StatCard
            icon={Wallet}
            label="Valor total"
            value={formatUsd(totalValue)}
            tooltip="Soma do valor (saldo × preço) de todos os tokens que têm cotação disponível."
          />
          <StatCard
            icon={Coins}
            label="Ativos com preço"
            value={priced.length}
            tooltip="Quantos tokens diferentes têm cotação encontrada na CoinGecko."
          />
          <StatCard
            icon={ShieldQuestion}
            label="Sem preço"
            value={assets.filter((a) => a.value_usd === null).length}
            tooltip="Tokens sem cotação na CoinGecko — geralmente muito novos, sem liquidez, ou spam enviado sem você pedir. Veja a seção colapsada abaixo da lista."
          />
          <StatCard
            icon={Wallet}
            label="Total rastreado"
            value={assets.length}
            tooltip="Todos os tokens já descobertos em qualquer uma das suas wallets, com ou sem preço."
          />
        </div>
      )}

      {loading && (
        <div className="flex flex-col gap-3">
          {Array.from({ length: 3 }).map((_, index) => (
            <Skeleton key={index} className="h-16 w-full" />
          ))}
        </div>
      )}

      {error && (
        <Alert variant="destructive">
          <AlertCircle />
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      {!loading && !error && assets.length === 0 && (
        <Card>
          <CardContent className="flex flex-col items-start gap-2 pt-4">
            <Inbox className="size-6 text-muted-foreground" />
            <p className="text-muted-foreground">
              Nenhum token encontrado ainda. Vá em Minhas Wallets e use
              "Buscar tokens" no card de uma wallet Ethereum, Polygon, BNB
              Chain ou Solana.
            </p>
          </CardContent>
        </Card>
      )}

      {!loading && !error && assets.length > 0 && (
        <>
          <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <Tabs value={network} onValueChange={setNetwork}>
              <TabsList>
                {NETWORK_FILTERS.map((filter) => (
                  <TabsTrigger key={filter.value} value={filter.value}>
                    {filter.label}
                  </TabsTrigger>
                ))}
              </TabsList>
            </Tabs>

            <div className="flex gap-2 sm:w-auto">
              <div className="relative flex-1 sm:w-56">
                <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                  value={search}
                  onChange={(event) => setSearch(event.target.value)}
                  placeholder="Buscar por nome ou símbolo..."
                  className="pl-9"
                />
              </div>

              <Select
                value={sortBy}
                onChange={(event) => setSortBy(event.target.value)}
                className="w-40"
              >
                {SORT_OPTIONS.map((option) => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </Select>
            </div>
          </div>

          {priced.length === 0 && unpriced.length === 0 && (
            <p className="text-sm text-muted-foreground">
              Nenhum ativo encontrado com esse filtro.
            </p>
          )}

          {priced.length > 0 && (
            <Card className="mb-4 overflow-hidden py-0">
              <div className="hidden grid-cols-[1fr_120px_140px_120px] gap-3 border-b border-border px-4 py-2 text-xs text-muted-foreground sm:grid">
                <span>Ativo</span>
                <span className="text-right">Saldo</span>
                <span className="text-right">Preço</span>
                <span className="flex items-center justify-end gap-1">
                  Valor
                  <InfoTooltip>Saldo × preço, e o quanto isso representa do seu valor total em ativos.</InfoTooltip>
                </span>
              </div>
              <div className="divide-y divide-border">
                {priced.map((asset) => (
                  <AssetRow
                    key={`${asset.network}:${asset.contract_address}`}
                    asset={asset}
                    percent={totalValue > 0 ? (asset.value_usd / totalValue) * 100 : 0}
                  />
                ))}
              </div>
            </Card>
          )}

          {unpriced.length > 0 && (
            <Card className="overflow-hidden py-0">
              <button
                onClick={() => setShowUnpriced((current) => !current)}
                className="flex w-full items-center justify-between gap-2 px-4 py-3 text-left text-sm text-muted-foreground hover:text-foreground"
              >
                <span className="flex items-center gap-1.5">
                  <ShieldQuestion className="size-4" />
                  {unpriced.length} {unpriced.length === 1 ? "token" : "tokens"} sem
                  preço / não verificado{unpriced.length === 1 ? "" : "s"}
                </span>
                {showUnpriced ? (
                  <ChevronUp className="size-4 shrink-0" />
                ) : (
                  <ChevronDown className="size-4 shrink-0" />
                )}
              </button>

              {showUnpriced && (
                <>
                  <p className="border-t border-border px-4 py-2 text-xs text-muted-foreground">
                    Sem cotação na CoinGecko — geralmente tokens muito novos,
                    sem liquidez, ou spam/phishing enviado direto pra sua
                    wallet sem você pedir. Não confie no nome exibido.
                  </p>
                  <div className="divide-y divide-border border-t border-border">
                    {unpriced.map((asset) => (
                      <UnpricedRow
                        key={`${asset.network}:${asset.contract_address}`}
                        asset={asset}
                      />
                    ))}
                  </div>
                </>
              )}
            </Card>
          )}
        </>
      )}
    </Layout>
  );
}

export default Assets;
