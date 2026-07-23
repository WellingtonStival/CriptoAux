import { NETWORKS } from "../config/networks";
import { formatUsd } from "./format";

const PERIOD_LABELS = {
  "24h": "nas últimas 24 horas",
  "7d": "nos últimos 7 dias",
  "30d": "nos últimos 30 dias",
  all: "desde que você começou a acompanhar",
};

/**
 * Gera frases legiveis a partir de dados que a tela do Dashboard ja tem
 * (summary/allocation/concentration de GET /api/portfolio/history) - nao
 * faz nenhuma chamada nova, so interpreta o que ja foi buscado. Por isso
 * fica no frontend em vez de um endpoint proprio: e puramente
 * apresentacao, nao calculo novo.
 */
export function generateInsights(data) {
  if (!data || (data.points ?? []).length === 0) {
    return [];
  }

  const insights = [];
  const { summary, allocation = [], concentration, period } = data;

  if (summary?.change_percent !== null && summary?.change_percent !== undefined) {
    const pct = summary.change_percent;
    const periodLabel = PERIOD_LABELS[period] ?? PERIOD_LABELS.all;

    if (Math.abs(pct) < 0.5) {
      insights.push({
        tone: "neutral",
        text: `Seu patrimônio está praticamente estável ${periodLabel} (${pct >= 0 ? "+" : ""}${pct.toFixed(1)}%).`,
      });
    } else if (pct > 0) {
      insights.push({
        tone: "positive",
        text: `Seu patrimônio subiu ${pct.toFixed(1)}% ${periodLabel}, alcançando ${formatUsd(summary.current_value_usd)}.`,
      });
    } else {
      insights.push({
        tone: "negative",
        text: `Seu patrimônio caiu ${Math.abs(pct).toFixed(1)}% ${periodLabel}.`,
      });
    }
  }

  let networkTopPercent = null;

  if (concentration?.by_network && allocation.length > 0) {
    const { level, top_percent, top_network } = concentration.by_network;
    const topLabel = NETWORKS[top_network]?.label ?? top_network;
    networkTopPercent = top_percent;

    if (allocation.length === 1) {
      insights.push({
        tone: "neutral",
        text: `Todo o seu patrimônio rastreado hoje está em ${topLabel}.`,
      });
    } else if (level === "concentrado") {
      insights.push({
        tone: "warning",
        text: `Concentração alta: ${top_percent.toFixed(1)}% do seu patrimônio está em ${topLabel}.`,
      });
    } else if (level === "diversificado") {
      insights.push({
        tone: "positive",
        text: `Patrimônio diversificado entre ${allocation.length} moedas diferentes.`,
      });
    }
  }

  // So mostra a concentracao por wallet se disser algo que a concentracao
  // por rede ainda nao disse (senao vira a mesma frase duas vezes, comum
  // quando o usuario tem 1 wallet por rede).
  const walletConcentration = concentration?.by_wallet;
  const tellsSomethingNew =
    networkTopPercent === null || Math.abs(walletConcentration?.top_percent - networkTopPercent) > 5;

  if (walletConcentration?.level === "concentrado" && walletConcentration.top_percent < 100 && tellsSomethingNew) {
    insights.push({
      tone: "neutral",
      text: `A wallet "${walletConcentration.top_wallet_label}" sozinha responde por ${walletConcentration.top_percent.toFixed(1)}% do total.`,
    });
  }

  if (summary?.max_value_usd > 0 && summary?.current_value_usd !== null) {
    const distanceFromMax = ((summary.max_value_usd - summary.current_value_usd) / summary.max_value_usd) * 100;

    if (distanceFromMax <= 1) {
      insights.push({
        tone: "positive",
        text: `Você está na máxima do período: ${formatUsd(summary.current_value_usd)}.`,
      });
    }
  }

  return insights.slice(0, 4);
}
