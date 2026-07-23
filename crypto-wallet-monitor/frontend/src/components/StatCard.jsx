import { Card, CardContent } from "./ui/card";
import { InfoTooltip } from "./ui/info-tooltip";

function StatCard({ icon, label, value, extra, tooltip }) {
  const Icon = icon;

  return (
    <Card>
      <CardContent className="pt-4">
        <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
          <Icon className="size-3.5" />
          {label}
          {tooltip && <InfoTooltip>{tooltip}</InfoTooltip>}
        </div>
        <div className="mt-1 flex items-center gap-2 text-lg font-semibold text-foreground">
          {value}
          {extra}
        </div>
      </CardContent>
    </Card>
  );
}

export default StatCard;
