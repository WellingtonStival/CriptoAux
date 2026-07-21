import { NETWORKS } from "../config/networks";
import PriceChangeBadge from "./PriceChangeBadge";

function PricesPanel({ prices }) {
  const networks = Object.keys(NETWORKS);

  return (
    <div className="mb-6 rounded-lg border border-slate-800 bg-slate-950 p-4">
      <h2 className="mb-3 text-sm font-medium text-slate-300">Cotações</h2>

      <div className="flex flex-col gap-2">
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
                <span className="text-slate-200">{config.label}</span>
              </div>

              {price ? (
                <div className="flex items-center gap-2">
                  <span className="text-slate-50">
                    $
                    {price.usd.toLocaleString("en-US", {
                      maximumFractionDigits: 2,
                    })}
                  </span>
                  <PriceChangeBadge change={price.change_24h} />
                </div>
              ) : (
                <span className="text-sm text-slate-500">--</span>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}

export default PricesPanel;
