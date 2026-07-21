import { useState } from "react";
import { createWallet } from "../services/api";

const ETHEREUM_ADDRESS_PATTERN = /^0x[a-fA-F0-9]{40}$/;

function WalletForm({ onCreated }) {
  const [address, setAddress] = useState("");
  const [error, setError] = useState("");
  const [saving, setSaving] = useState(false);

  async function handleSubmit(event) {
    event.preventDefault();

    const normalizedAddress = address.trim();

    if (!ETHEREUM_ADDRESS_PATTERN.test(normalizedAddress)) {
      setError("Informe um endereço Ethereum válido.");
      return;
    }

    setSaving(true);
    setError("");

    try {
      const response = await createWallet(normalizedAddress, "ethereum");
      onCreated(response.data);
      setAddress("");
    } catch (requestError) {
      const validationErrors = requestError.response?.data?.errors;

      if (validationErrors?.address?.[0]) {
        setError(validationErrors.address[0]);
      } else {
        setError("Não foi possível cadastrar a carteira. Tente novamente.");
      }
    } finally {
      setSaving(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} style={{ marginBottom: 24 }}>
      <label htmlFor="wallet-address">Endereço Ethereum</label>

      <div style={{ display: "flex", gap: 8, marginTop: 6 }}>
        <input
          id="wallet-address"
          type="text"
          value={address}
          onChange={(event) => setAddress(event.target.value)}
          placeholder="0x..."
          autoComplete="off"
          disabled={saving}
          style={{ flex: 1 }}
        />

        <button type="submit" disabled={saving}>
          {saving ? "Cadastrando..." : "Cadastrar carteira"}
        </button>
      </div>

      <p style={{ margin: "6px 0 0", fontSize: 14 }}>
        Use somente um endereço público. Nunca informe sua chave privada.
      </p>

      {error && <p style={{ color: "red" }}>{error}</p>}
    </form>
  );
}

export default WalletForm;
