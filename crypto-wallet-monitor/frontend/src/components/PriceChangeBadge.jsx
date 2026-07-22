import { TrendingUp, TrendingDown } from "lucide-react";
import { Badge } from "./ui/badge";

function PriceChangeBadge({ change }) {
  if (change === null || change === undefined) {
    return null;
  }

  const isPositive = change >= 0;
  const Icon = isPositive ? TrendingUp : TrendingDown;

  return (
    <Badge variant={isPositive ? "success" : "destructive"}>
      <Icon className="size-3" />
      {Math.abs(change).toFixed(2)}%
    </Badge>
  );
}

export default PriceChangeBadge;
