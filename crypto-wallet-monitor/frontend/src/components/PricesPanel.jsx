import { Coins } from "lucide-react";
import { NETWORKS } from "../config/networks";
import { Card, CardHeader, CardTitle, CardContent } from "./ui/card";
import PriceChangeBadge from "./PriceChangeBadge";

function PricesPanel({ prices }) {
  const networks = Object.keys(NETWORKS);

  return (
    <Card className="mb-6">
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <Coins className="size-4" />
          Cotações
        </CardTitle>
      </CardHeader>

      <CardContent className="flex flex-col gap-2">
        {networks.map((network) => {
          const price = prices?.[network];
          const config = NETWORKS[network];

          return (
            <div key={network} className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <span
                  className="h-2 w-2 rounded-full"
                  style={{ backgroundColor: config.color }}
                />
                <span className="text-foreground">{config.label}</span>
              </div>

              {price ? (
                <div className="flex items-center gap-2">
                  <span className="text-foreground">
                    $
                    {price.usd.toLocaleString("en-US", {
                      maximumFractionDigits: 2,
                    })}
                  </span>
                  <PriceChangeBadge change={price.change_24h} />
                </div>
              ) : (
                <span className="text-sm text-muted-foreground">--</span>
              )}
            </div>
          );
        })}
      </CardContent>
    </Card>
  );
}

export default PricesPanel;
