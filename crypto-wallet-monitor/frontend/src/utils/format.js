export function formatUsd(value) {
  if (value === null || value === undefined) return "--";
  return `$${value.toLocaleString("en-US", { maximumFractionDigits: 2 })}`;
}

export function formatCompactUsd(value) {
  if (value === null || value === undefined) return "--";
  return `$${new Intl.NumberFormat("en-US", {
    notation: "compact",
    maximumFractionDigits: 2,
  }).format(value)}`;
}

export function formatDateTime(iso) {
  if (!iso) return "--";
  return new Date(iso).toLocaleString("pt-BR", {
    day: "2-digit",
    month: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
  });
}

export function formatDate(iso) {
  if (!iso) return "--";
  return new Date(iso).toLocaleDateString("pt-BR", {
    day: "2-digit",
    month: "2-digit",
    year: "2-digit",
    timeZone: "UTC",
  });
}
