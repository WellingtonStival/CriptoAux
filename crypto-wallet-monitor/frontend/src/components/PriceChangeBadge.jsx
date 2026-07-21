function PriceChangeBadge({ change }) {
  if (change === null || change === undefined) {
    return null;
  }

  const isPositive = change >= 0;

  return (
    <span
      className={`rounded px-1.5 py-0.5 text-xs font-medium ${
        isPositive
          ? "bg-emerald-500/15 text-emerald-400"
          : "bg-red-500/15 text-red-400"
      }`}
    >
      {isPositive ? "▲" : "▼"} {Math.abs(change).toFixed(2)}%
    </span>
  );
}

export default PriceChangeBadge;
