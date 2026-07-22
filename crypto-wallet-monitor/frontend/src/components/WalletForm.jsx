import { useState } from "react";
import { ShieldAlert, Plus } from "lucide-react";
import { createWallet } from "../services/api";
import { Card, CardContent } from "./ui/card";
import { Input } from "./ui/input";
import { Label } from "./ui/label";
import { Select } from "./ui/select";
import { Button } from "./ui/button";
import { Alert, AlertDescription } from "./ui/alert";

const ADDRESS_PATTERNS = {
  ethereum: /^0x[a-fA-F0-9]{40}$/,
  solana: /^[1-9A-HJ-NP-Za-km-z]{32,44}$/,
  bitcoin: /^(1[a-km-zA-HJ-NP-Z1-9]{25,34}|3[a-km-zA-HJ-NP-Z1-9]{25,34}|bc1[a-z0-9]{25,62})$/,
};

const NETWORK_OPTIONS = [
  { value: "ethereum", label: "Ethereum" },
  { value: "solana", label: "Solana" },
  { value: "bitcoin", label: "Bitcoin" },
];

const ADDRESS_PLACEHOLDERS = {
  ethereum: "0x...",
  solana: "Endereço Solana",
  bitcoin: "Endereço Bitcoin",
};

function WalletForm({ onCreated }) {
  const [network, setNetwork] = useState("ethereum");
  const [address, setAddress] = useState("");
  const [name, setName] = useState("");
  const [error, setError] = useState("");
  const [saving, setSaving] = useState(false);

  async function handleSubmit(event) {
    event.preventDefault();

    const normalizedAddress = address.trim();

    if (!ADDRESS_PATTERNS[network].test(normalizedAddress)) {
      setError("Endereço inválido para a blockchain selecionada.");
      return;
    }

    setSaving(true);
    setError("");

    try {
      const response = await createWallet(normalizedAddress, network, name.trim());
      onCreated(response.data);
      setAddress("");
      setName("");
    } catch (requestError) {
      const validationErrors = requestError.response?.data?.errors;
      const firstError =
        validationErrors?.address?.[0] ?? validationErrors?.network?.[0];

      setError(firstError ?? "Não foi possível cadastrar a carteira. Tente novamente.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <Card className="mb-6">
      <CardContent className="pt-4">
        <form onSubmit={handleSubmit}>
          <div className="flex gap-2">
            <div className="w-36 shrink-0">
              <Label htmlFor="wallet-network">Blockchain</Label>

              <Select
                id="wallet-network"
                value={network}
                onChange={(event) => setNetwork(event.target.value)}
                disabled={saving}
              >
                {NETWORK_OPTIONS.map((option) => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </Select>
            </div>

            <div className="flex-1">
              <Label htmlFor="wallet-address">Endereço</Label>

              <Input
                id="wallet-address"
                type="text"
                value={address}
                onChange={(event) => setAddress(event.target.value)}
                placeholder={ADDRESS_PLACEHOLDERS[network]}
                autoComplete="off"
                disabled={saving}
              />
            </div>
          </div>

          <div className="mt-3">
            <Label htmlFor="wallet-name">Nome (opcional)</Label>

            <Input
              id="wallet-name"
              type="text"
              value={name}
              onChange={(event) => setName(event.target.value)}
              placeholder="Ex: Carteira do Trezor"
              autoComplete="off"
              disabled={saving}
            />
          </div>

          <Button type="submit" disabled={saving} className="mt-3">
            <Plus className="size-4" />
            {saving ? "Cadastrando..." : "Cadastrar carteira"}
          </Button>

          <p className="mt-3 flex items-center gap-1.5 text-xs text-muted-foreground">
            <ShieldAlert className="size-3.5 shrink-0" />
            Use somente um endereço público. Nunca informe sua chave privada.
          </p>

          {error && (
            <Alert variant="destructive" className="mt-3">
              <AlertDescription>{error}</AlertDescription>
            </Alert>
          )}
        </form>
      </CardContent>
    </Card>
  );
}

export default WalletForm;
