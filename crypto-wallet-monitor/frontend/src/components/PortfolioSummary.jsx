function PortfolioSummary({ wallets, balances, prices }) {
  let total = 0;
  let walletsWithValue = 0;

  for (const wallet of wallets) {
    const balance = balances[wallet.id];
    const price = prices?.[wallet.network];

    if (balance == null || !price) {
      continue;
    }

    total += balance * price.usd;
    walletsWithValue += 1;
  }

  const pending = wallets.length - walletsWithValue;

  return (
    <div className="mb-6 rounded-lg border border-slate-800 bg-slate-950 p-4">
      <div className="text-xs text-slate-400">Valor total do portfólio</div>
      <div className="mt-1 text-2xl font-bold text-slate-50">
        ${total.toLocaleString("en-US", { maximumFractionDigits: 2 })}
      </div>

      {pending > 0 && (
        <div className="mt-1 text-xs text-slate-500">
          Consultando saldo de {pending} carteira(s)...
        </div>
      )}
    </div>
  );
}

export default PortfolioSummary;
