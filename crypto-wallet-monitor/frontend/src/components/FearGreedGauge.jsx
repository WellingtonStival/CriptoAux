const RADIUS = 80;
const CENTER = 100;
const STROKE = 16;

/**
 * Ponto (x,y) na semicircunferencia: percent=0 fica na ponta esquerda,
 * percent=50 no topo, percent=100 na ponta direita.
 */
function pointOnArc(percent) {
  const angleDeg = 180 - (percent / 100) * 180;
  const angleRad = (angleDeg * Math.PI) / 180;

  return {
    x: CENTER + RADIUS * Math.cos(angleRad),
    y: CENTER - RADIUS * Math.sin(angleRad),
  };
}

function arcPath(fromPercent, toPercent) {
  const start = pointOnArc(fromPercent);
  const end = pointOnArc(toPercent);
  const largeArc = toPercent - fromPercent > 50 ? 1 : 0;

  return `M ${start.x} ${start.y} A ${RADIUS} ${RADIUS} 0 ${largeArc} 1 ${end.x} ${end.y}`;
}

/**
 * Mesmo esquema de 3 niveis (ruim/neutro/bom) ja usado no card de
 * Concentracao do Dashboard - reaproveita os tokens de status do design
 * system em vez de inventar uma paleta nova de 5 cores (evita o problema
 * classico de paleta categorica com muitas cores proximas demais).
 */
function statusColor(value) {
  if (value <= 25) return "var(--color-destructive)";
  if (value <= 45) return "#fb923c"; // laranja - "Medo", intermediario entre destructive e warning
  if (value <= 55) return "var(--color-warning)";
  if (value <= 75) return "#86efac"; // verde claro - "Ganancia", intermediario entre warning e success
  return "var(--color-success)";
}

function FearGreedGauge({ value, classification }) {
  const clamped = Math.max(0, Math.min(100, value));
  const color = statusColor(clamped);
  const needle = pointOnArc(clamped);

  return (
    <div className="flex flex-col items-center">
      <svg viewBox="10 10 180 100" className="w-full max-w-[280px]">
        <path
          d={arcPath(0, 100)}
          fill="none"
          stroke="var(--color-muted)"
          strokeWidth={STROKE}
          strokeLinecap="round"
        />
        <path
          d={arcPath(0, clamped)}
          fill="none"
          stroke={color}
          strokeWidth={STROKE}
          strokeLinecap="round"
        />
        <circle cx={needle.x} cy={needle.y} r={5} fill="var(--color-foreground)" />
      </svg>

      <div className="-mt-4 text-center">
        <div className="text-3xl font-bold text-foreground">{clamped}</div>
        <div className="text-sm font-medium" style={{ color }}>
          {classification}
        </div>
      </div>
    </div>
  );
}

export default FearGreedGauge;
