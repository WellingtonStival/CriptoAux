import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import {
  UserRound,
  Pencil,
  Check,
  X,
  KeyRound,
  CheckCircle2,
  Send,
  AlertTriangle,
  Trash2,
} from "lucide-react";
import Layout from "../components/Layout";
import { useAuth } from "../context/AuthContext";
import {
  getAccount,
  updateAccountName,
  updateAccountPassword,
  deleteAccount,
} from "../services/api";
import { formatDate } from "../utils/format";
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from "../components/ui/card";
import { Badge } from "../components/ui/badge";
import { Button } from "../components/ui/button";
import { Input } from "../components/ui/input";
import { Label } from "../components/ui/label";
import { Alert, AlertDescription } from "../components/ui/alert";
import { Skeleton } from "../components/ui/skeleton";

function NameField({ name, onSaved }) {
  const [editing, setEditing] = useState(false);
  const [draft, setDraft] = useState(name);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);

  if (!editing) {
    return (
      <div className="flex items-center gap-1.5">
        <span className="text-foreground">{name}</span>
        <button
          onClick={() => {
            setDraft(name);
            setEditing(true);
          }}
          title="Editar nome"
          className="text-muted-foreground hover:text-foreground"
        >
          <Pencil className="size-3.5" />
        </button>
      </div>
    );
  }

  async function handleSave() {
    setSaving(true);
    setError(null);

    try {
      await updateAccountName(draft.trim());
      onSaved(draft.trim());
      setEditing(false);
    } catch {
      setError("Não foi possível salvar o nome.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <div>
      <div className="flex items-center gap-1.5">
        <Input
          value={draft}
          onChange={(event) => setDraft(event.target.value)}
          disabled={saving}
          className="h-8 max-w-xs text-sm"
        />
        <Button size="icon" className="size-8" onClick={handleSave} disabled={saving}>
          <Check className="size-4" />
        </Button>
        <Button
          variant="outline"
          size="icon"
          className="size-8"
          onClick={() => setEditing(false)}
          disabled={saving}
        >
          <X className="size-4" />
        </Button>
      </div>
      {error && <p className="mt-1 text-xs text-destructive">{error}</p>}
    </div>
  );
}

function PasswordForm() {
  const [currentPassword, setCurrentPassword] = useState("");
  const [password, setPassword] = useState("");
  const [confirmation, setConfirmation] = useState("");
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(false);

  async function handleSubmit(event) {
    event.preventDefault();
    setSaving(true);
    setError(null);
    setSuccess(false);

    try {
      await updateAccountPassword({
        current_password: currentPassword,
        password,
        password_confirmation: confirmation,
      });
      setSuccess(true);
      setCurrentPassword("");
      setPassword("");
      setConfirmation("");
    } catch (requestError) {
      setError(
        requestError.response?.data?.message ?? "Não foi possível trocar a senha."
      );
    } finally {
      setSaving(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-3">
      <div>
        <Label>Senha atual</Label>
        <Input
          type="password"
          value={currentPassword}
          onChange={(event) => setCurrentPassword(event.target.value)}
          autoComplete="current-password"
          required
        />
      </div>
      <div>
        <Label>Nova senha</Label>
        <Input
          type="password"
          value={password}
          onChange={(event) => setPassword(event.target.value)}
          autoComplete="new-password"
          minLength={8}
          required
        />
      </div>
      <div>
        <Label>Confirmar nova senha</Label>
        <Input
          type="password"
          value={confirmation}
          onChange={(event) => setConfirmation(event.target.value)}
          autoComplete="new-password"
          required
        />
      </div>

      <Button type="submit" disabled={saving} className="self-start">
        <KeyRound className="size-3.5" />
        {saving ? "Salvando..." : "Trocar senha"}
      </Button>

      {error && <p className="text-sm text-destructive">{error}</p>}
      {success && (
        <p className="flex items-center gap-1.5 text-sm text-success">
          <CheckCircle2 className="size-3.5" />
          Senha atualizada.
        </p>
      )}
    </form>
  );
}

function DeleteAccountSection() {
  const { logout } = useAuth();
  const navigate = useNavigate();
  const [confirming, setConfirming] = useState(false);
  const [password, setPassword] = useState("");
  const [deleting, setDeleting] = useState(false);
  const [error, setError] = useState(null);

  async function handleDelete() {
    setDeleting(true);
    setError(null);

    try {
      await deleteAccount(password);
      logout();
      navigate("/login");
    } catch (requestError) {
      setError(
        requestError.response?.data?.message ?? "Não foi possível excluir a conta."
      );
      setDeleting(false);
    }
  }

  return (
    <Card className="border-destructive/40">
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-destructive">
          <AlertTriangle className="size-4" />
          Excluir conta
        </CardTitle>
        <CardDescription>
          Remove permanentemente sua conta, todas as wallets cadastradas,
          histórico e alertas. Essa ação não pode ser desfeita.
        </CardDescription>
      </CardHeader>
      <CardContent>
        {!confirming ? (
          <Button variant="destructive" onClick={() => setConfirming(true)}>
            <Trash2 className="size-3.5" />
            Excluir minha conta
          </Button>
        ) : (
          <div className="flex flex-col gap-3">
            <div>
              <Label>Digite sua senha pra confirmar</Label>
              <Input
                type="password"
                value={password}
                onChange={(event) => setPassword(event.target.value)}
                autoComplete="current-password"
                disabled={deleting}
              />
            </div>
            <div className="flex gap-2">
              <Button
                variant="destructive"
                onClick={handleDelete}
                disabled={deleting || !password}
              >
                {deleting ? "Excluindo..." : "Sim, excluir permanentemente"}
              </Button>
              <Button
                variant="outline"
                onClick={() => {
                  setConfirming(false);
                  setPassword("");
                  setError(null);
                }}
                disabled={deleting}
              >
                Cancelar
              </Button>
            </div>
            {error && <p className="text-sm text-destructive">{error}</p>}
          </div>
        )}
      </CardContent>
    </Card>
  );
}

function Account() {
  const [account, setAccount] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    async function load() {
      try {
        setLoading(true);
        setError(null);
        const response = await getAccount();
        setAccount(response.data);
      } catch {
        setError("Erro ao carregar os dados da conta.");
      } finally {
        setLoading(false);
      }
    }

    load();
  }, []);

  return (
    <Layout>
      <h1 className="mb-6 flex items-center gap-2 text-2xl font-bold text-foreground">
        <UserRound className="size-6" />
        Minha Conta
      </h1>

      {loading && (
        <div className="flex flex-col gap-3">
          <Skeleton className="h-40 w-full" />
          <Skeleton className="h-48 w-full" />
        </div>
      )}

      {error && (
        <Alert variant="destructive">
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      {!loading && !error && account && (
        <div className="flex flex-col gap-6">
          <Card>
            <CardHeader>
              <CardTitle>Dados da conta</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-3 text-sm">
              <div>
                <div className="mb-1 text-xs text-muted-foreground">Nome</div>
                <NameField
                  name={account.name}
                  onSaved={(name) => setAccount((current) => ({ ...current, name }))}
                />
              </div>

              <div>
                <div className="mb-1 text-xs text-muted-foreground">Email</div>
                <div className="flex items-center gap-1.5">
                  <span className="text-foreground">{account.email}</span>
                  {account.email_verified && (
                    <Badge variant="success">
                      <CheckCircle2 className="size-3" />
                      Verificado
                    </Badge>
                  )}
                </div>
              </div>

              <div>
                <div className="mb-1 text-xs text-muted-foreground">Telegram</div>
                <div className="flex items-center gap-1.5 text-foreground">
                  <Send className="size-3.5 text-muted-foreground" />
                  {account.telegram_linked ? "Conectado" : "Não conectado"}
                </div>
              </div>

              <div>
                <div className="mb-1 text-xs text-muted-foreground">Membro desde</div>
                <span className="text-foreground">{formatDate(account.created_at)}</span>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Trocar senha</CardTitle>
            </CardHeader>
            <CardContent>
              <PasswordForm />
            </CardContent>
          </Card>

          <DeleteAccountSection />
        </div>
      )}
    </Layout>
  );
}

export default Account;
