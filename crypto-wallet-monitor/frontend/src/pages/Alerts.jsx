import { useEffect, useRef, useState } from "react";
import {
  BellRing,
  Send,
  CheckCircle2,
  Plus,
  Trash2,
  TrendingDown,
  Wallet as WalletIcon,
  DollarSign,
  AlertCircle,
  Pause,
  Play,
} from "lucide-react";
import Layout from "../components/Layout";
import {
  getTelegramStatus,
  generateTelegramLinkCode,
  unlinkTelegram,
  getAlerts,
  createAlert,
  updateAlert,
  deleteAlert,
  getWallets,
} from "../services/api";
import { NETWORKS } from "../config/networks";
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from "../components/ui/card";
import { Badge } from "../components/ui/badge";
import { Button } from "../components/ui/button";
import { Input } from "../components/ui/input";
import { Label } from "../components/ui/label";
import { Select } from "../components/ui/select";
import { Alert, AlertDescription } from "../components/ui/alert";
import { Skeleton } from "../components/ui/skeleton";
import { InfoTooltip } from "../components/ui/info-tooltip";

const TYPE_OPTIONS = [
  {
    value: "wallet_balance_drop",
    label: "Queda de saldo numa wallet",
    icon: TrendingDown,
    description: 'Avisa se o saldo (quantidade da moeda, não valor) de uma wallet cair mais que o limite — pode ser sinal de movimentação que você não fez.',
  },
  {
    value: "portfolio_change",
    label: "Variação do patrimônio total",
    icon: WalletIcon,
    description: "Avisa se a soma de todas as suas wallets em USD mudar mais que o limite nas últimas 24h.",
  },
  {
    value: "price_change",
    label: "Variação de preço de uma moeda",
    icon: DollarSign,
    description: "Avisa se o preço de uma moeda específica mudar mais que o limite nas últimas 24h.",
  },
];

const DIRECTION_OPTIONS = [
  { value: "down", label: "Só quedas" },
  { value: "up", label: "Só altas" },
  { value: "any", label: "Qualquer direção" },
];

function typeMeta(type) {
  return TYPE_OPTIONS.find((option) => option.value === type) ?? TYPE_OPTIONS[0];
}

function TelegramConnection({ status, onStatusChange }) {
  const [connecting, setConnecting] = useState(false);
  const [error, setError] = useState(null);
  const pollRef = useRef(null);

  useEffect(() => {
    return () => clearInterval(pollRef.current);
  }, []);

  async function handleConnect() {
    setError(null);
    setConnecting(true);

    try {
      const response = await generateTelegramLinkCode();
      window.open(response.data.link_url, "_blank", "noopener,noreferrer");

      let attempts = 0;
      pollRef.current = setInterval(async () => {
        attempts += 1;

        try {
          const statusResponse = await getTelegramStatus();
          if (statusResponse.data.linked) {
            clearInterval(pollRef.current);
            setConnecting(false);
            onStatusChange(statusResponse.data);
          }
        } catch {
          // ignora falha pontual, tenta de novo no proximo ciclo
        }

        if (attempts >= 40) {
          clearInterval(pollRef.current);
          setConnecting(false);
        }
      }, 3000);
    } catch {
      setError("Não foi possível gerar o link de conexão.");
      setConnecting(false);
    }
  }

  async function handleUnlink() {
    try {
      await unlinkTelegram();
      onStatusChange({ linked: false });
    } catch {
      setError("Não foi possível desconectar.");
    }
  }

  if (!status?.configured) {
    return (
      <Alert>
        <AlertCircle />
        <AlertDescription>
          Integração com Telegram ainda não configurada neste servidor.
        </AlertDescription>
      </Alert>
    );
  }

  if (status.linked) {
    return (
      <div className="flex flex-wrap items-center justify-between gap-3">
        <span className="flex items-center gap-2 text-sm text-success">
          <CheckCircle2 className="size-4" />
          Telegram conectado — você vai receber os alertas por lá.
        </span>
        <Button variant="outline" size="sm" onClick={handleUnlink}>
          Desconectar
        </Button>
      </div>
    );
  }

  return (
    <div>
      <div className="flex flex-wrap items-center gap-3">
        <Button size="sm" onClick={handleConnect} disabled={connecting}>
          <Send className="size-3.5" />
          {connecting ? "Aguardando confirmação..." : "Conectar Telegram"}
        </Button>
        {connecting && (
          <span className="text-xs text-muted-foreground">
            Abrimos o Telegram numa aba nova — clique em "Iniciar" na conversa com o bot.
          </span>
        )}
      </div>
      {error && <p className="mt-2 text-sm text-destructive">{error}</p>}
    </div>
  );
}

function AlertForm({ wallets, onCreated }) {
  const [type, setType] = useState("wallet_balance_drop");
  const [walletId, setWalletId] = useState("");
  const [network, setNetwork] = useState("bitcoin");
  const [threshold, setThreshold] = useState(10);
  const [direction, setDirection] = useState("down");
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);

  async function handleSubmit(event) {
    event.preventDefault();
    setSaving(true);
    setError(null);

    try {
      const payload = {
        type,
        threshold_percent: Number(threshold),
      };

      if (type === "wallet_balance_drop") {
        payload.wallet_id = walletId || null;
      } else {
        payload.direction = direction;
        if (type === "price_change") {
          payload.network = network;
        }
      }

      const response = await createAlert(payload);
      onCreated(response.data);
      setThreshold(10);
    } catch (requestError) {
      setError(
        requestError.response?.data?.message ?? "Não foi possível criar o alerta."
      );
    } finally {
      setSaving(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-3">
      <div>
        <Label>Tipo de alerta</Label>
        <Select value={type} onChange={(event) => setType(event.target.value)}>
          {TYPE_OPTIONS.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </Select>
        <p className="mt-1 text-xs text-muted-foreground">{typeMeta(type).description}</p>
      </div>

      {type === "wallet_balance_drop" && (
        <div>
          <Label>Wallet</Label>
          <Select value={walletId} onChange={(event) => setWalletId(event.target.value)}>
            <option value="">Todas as suas wallets</option>
            {wallets.map((wallet) => (
              <option key={wallet.id} value={wallet.id}>
                {wallet.name || wallet.address}
              </option>
            ))}
          </Select>
        </div>
      )}

      {type === "price_change" && (
        <div>
          <Label>Moeda</Label>
          <Select value={network} onChange={(event) => setNetwork(event.target.value)}>
            {Object.entries(NETWORKS).map(([key, config]) => (
              <option key={key} value={key}>
                {config.label}
              </option>
            ))}
          </Select>
        </div>
      )}

      {type !== "wallet_balance_drop" && (
        <div>
          <Label>Direção</Label>
          <Select value={direction} onChange={(event) => setDirection(event.target.value)}>
            {DIRECTION_OPTIONS.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </Select>
        </div>
      )}

      <div>
        <Label className="flex items-center gap-1">
          Limite (%)
          <InfoTooltip>
            O quanto precisa mudar pra disparar o alerta. Ex: 10 dispara
            quando a variação passar de 10%.
          </InfoTooltip>
        </Label>
        <Input
          type="number"
          min="0.1"
          max="100"
          step="0.1"
          value={threshold}
          onChange={(event) => setThreshold(event.target.value)}
          required
        />
      </div>

      <Button type="submit" disabled={saving} className="self-start">
        <Plus className="size-3.5" />
        {saving ? "Criando..." : "Criar alerta"}
      </Button>

      {error && <p className="text-sm text-destructive">{error}</p>}
    </form>
  );
}

function AlertRow({ alert, onToggled, onDeleted }) {
  const meta = typeMeta(alert.type);
  const Icon = meta.icon;

  let target = "Patrimônio total";
  if (alert.type === "wallet_balance_drop") {
    target = alert.wallet_label ?? "Qualquer wallet";
  } else if (alert.type === "price_change") {
    target = NETWORKS[alert.network]?.label ?? alert.network;
  }

  async function handleToggle() {
    const response = await updateAlert(alert.id, { is_active: !alert.is_active });
    onToggled(response.data);
  }

  async function handleDelete() {
    await deleteAlert(alert.id);
    onDeleted(alert.id);
  }

  return (
    <div className="flex items-center justify-between gap-3 px-4 py-3">
      <div className="flex min-w-0 items-center gap-3">
        <Icon className="size-4 shrink-0 text-muted-foreground" />
        <div className="min-w-0">
          <div className="truncate text-sm text-foreground">{meta.label}</div>
          <div className="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
            <span className="truncate">{target}</span>
            <span>· {alert.threshold_percent}%</span>
            {alert.direction !== "down" && (
              <Badge variant="outline">
                {DIRECTION_OPTIONS.find((option) => option.value === alert.direction)?.label}
              </Badge>
            )}
          </div>
        </div>
      </div>

      <div className="flex shrink-0 items-center gap-1.5">
        <Button variant="outline" size="icon" className="size-8" onClick={handleToggle} title={alert.is_active ? "Pausar" : "Ativar"}>
          {alert.is_active ? <Pause className="size-3.5" /> : <Play className="size-3.5" />}
        </Button>
        <Button
          variant="outline"
          size="icon"
          className="size-8 text-destructive hover:bg-destructive-muted"
          onClick={handleDelete}
          title="Remover"
        >
          <Trash2 className="size-3.5" />
        </Button>
      </div>
    </div>
  );
}

function Alerts() {
  const [status, setStatus] = useState(null);
  const [alerts, setAlerts] = useState([]);
  const [wallets, setWallets] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    async function load() {
      try {
        setLoading(true);
        setError(null);
        const [statusResponse, alertsResponse, walletsResponse] = await Promise.all([
          getTelegramStatus(),
          getAlerts(),
          getWallets(),
        ]);
        setStatus(statusResponse.data);
        setAlerts(alertsResponse.data.alerts ?? []);
        setWallets(walletsResponse.data.data ?? []);
      } catch {
        setError("Erro ao carregar seus alertas.");
      } finally {
        setLoading(false);
      }
    }

    load();
  }, []);

  return (
    <Layout>
      <h1 className="mb-2 flex items-center gap-2 text-2xl font-bold text-foreground">
        <BellRing className="size-6" />
        Alertas
      </h1>
      <p className="mb-6 text-sm text-muted-foreground">
        Receba um aviso no Telegram quando algo relevante acontecer — queda
        de saldo numa wallet, variação do seu patrimônio total, ou preço de
        uma moeda.
      </p>

      {loading && (
        <div className="flex flex-col gap-3">
          <Skeleton className="h-16 w-full" />
          <Skeleton className="h-40 w-full" />
        </div>
      )}

      {error && (
        <Alert variant="destructive">
          <AlertCircle />
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      {!loading && !error && (
        <>
          <Card className="mb-6">
            <CardHeader>
              <CardTitle>Telegram</CardTitle>
              <CardDescription>
                Canal usado pra te avisar — gratuito, sem precisar instalar nada além do Telegram.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <TelegramConnection status={status} onStatusChange={setStatus} />
            </CardContent>
          </Card>

          {status?.linked && (
            <>
              <Card className="mb-6">
                <CardHeader>
                  <CardTitle>Novo alerta</CardTitle>
                </CardHeader>
                <CardContent>
                  <AlertForm
                    wallets={wallets}
                    onCreated={(alert) => setAlerts((current) => [alert, ...current])}
                  />
                </CardContent>
              </Card>

              <Card className="overflow-hidden py-0">
                <CardHeader className="pb-0 pt-4">
                  <CardTitle>Seus alertas</CardTitle>
                </CardHeader>
                {alerts.length === 0 ? (
                  <p className="px-4 py-4 text-sm text-muted-foreground">
                    Nenhum alerta configurado ainda.
                  </p>
                ) : (
                  <div className="mt-2 divide-y divide-border">
                    {alerts.map((alert) => (
                      <AlertRow
                        key={alert.id}
                        alert={alert}
                        onToggled={(updated) =>
                          setAlerts((current) =>
                            current.map((item) => (item.id === updated.id ? updated : item))
                          )
                        }
                        onDeleted={(id) =>
                          setAlerts((current) => current.filter((item) => item.id !== id))
                        }
                      />
                    ))}
                  </div>
                )}
              </Card>
            </>
          )}
        </>
      )}
    </Layout>
  );
}

export default Alerts;
