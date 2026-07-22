import { useEffect, useRef } from "react";

const SYMBOLS = {
  ethereum: "BINANCE:ETHUSDT",
  bitcoin: "BINANCE:BTCUSDT",
  solana: "BINANCE:SOLUSDT",
};

function TradingViewChart({ network }) {
  const containerRef = useRef(null);

  useEffect(() => {
    const container = containerRef.current;
    if (!container) return;

    container.innerHTML = "";

    const widgetId = `tradingview_${network}_${Date.now()}`;
    const widgetDiv = document.createElement("div");
    widgetDiv.id = widgetId;
    widgetDiv.style.height = "500px";
    container.appendChild(widgetDiv);

    function createWidget() {
      if (!window.TradingView || !container.isConnected) return;

      new window.TradingView.widget({
        autosize: true,
        symbol: SYMBOLS[network] ?? "BINANCE:BTCUSDT",
        interval: "D",
        timezone: "Etc/UTC",
        theme: "dark",
        style: "1",
        locale: "br",
        toolbar_bg: "#0f172a",
        enable_publishing: false,
        allow_symbol_change: false,
        container_id: widgetId,
      });
    }

    if (window.TradingView) {
      createWidget();
    } else {
      const script = document.createElement("script");
      script.src = "https://s3.tradingview.com/tv.js";
      script.async = true;
      script.onload = createWidget;
      document.body.appendChild(script);
    }
  }, [network]);

  return (
    <div ref={containerRef} className="overflow-hidden rounded-lg" />
  );
}

export default TradingViewChart;
