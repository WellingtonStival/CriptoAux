import { useState } from "react";
import { createWallet } from "../services/api";

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
      const response = await createWallet(normalizedAddress, network);
      onCreated(response.data);
      setAddress("");
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
    <form
      onSubmit={handleSubmit}
      className="mb-6 rounded-lg border border-slate-800 bg-slate-950 p-4"
    >
      <div className="flex gap-2">
        <div className="w-36 shrink-0">
          <label
            htmlFor="wallet-network"
            className="mb-1.5 block text-sm text-slate-300"
          >
            Blockchain
          </label>

          <select
            id="wallet-network"
            value={network}
            onChange={(event) => setNetwork(event.target.value)}
            disabled={saving}
            className="w-full rounded-md border border-slate-700 bg-slate-900 px-3 py-2 text-slate-50 focus:border-slate-500 focus:outline-none disabled:opacity-60"
          >
            {NETWORK_OPTIONS.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </div>

        <div className="flex-1">
          <label
            htmlFor="wallet-address"
            className="mb-1.5 block text-sm text-slate-300"
          >
            Endereço
          </label>

          <input
            id="wallet-address"
            type="text"
            value={address}
            onChange={(event) => setAddress(event.target.value)}
            placeholder={ADDRESS_PLACEHOLDERS[network]}
            autoComplete="off"
            disabled={saving}
            className="w-full rounded-md border border-slate-700 bg-slate-900 px-3 py-2 text-slate-50 placeholder:text-slate-500 focus:border-slate-500 focus:outline-none disabled:opacity-60"
          />
        </div>
      </div>

      <button
        type="submit"
        disabled={saving}
        className="mt-3 rounded-md bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-500 disabled:opacity-60"
      >
        {saving ? "Cadastrando..." : "Cadastrar carteira"}
      </button>

      <p className="mt-2 text-xs text-slate-400">
        Use somente um endereço público. Nunca informe sua chave privada.
      </p>

      {error && <p className="mt-2 text-sm text-red-400">{error}</p>}
    </form>
  );
}

export default WalletForm;
