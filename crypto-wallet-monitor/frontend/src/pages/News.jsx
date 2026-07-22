import { useEffect, useState } from "react";
import Layout from "../components/Layout";
import { getNews } from "../services/api";
import { formatDateTime } from "../utils/format";
import { NETWORKS } from "../config/networks";

const FILTERS = [
  { value: null, label: "Todas" },
  { value: "ethereum", label: "Ethereum" },
  { value: "solana", label: "Solana" },
  { value: "bitcoin", label: "Bitcoin" },
];

function News() {
  const [network, setNetwork] = useState(null);
  const [news, setNews] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    async function loadNews() {
      try {
        setLoading(true);
        setError(null);
        const response = await getNews(network);
        setNews(response.data.news ?? []);
      } catch {
        setError("Erro ao carregar notícias.");
      } finally {
        setLoading(false);
      }
    }

    loadNews();
  }, [network]);

  return (
    <Layout>
      <h1 className="mb-6 text-2xl font-bold text-slate-50">Notícias</h1>

      <div className="mb-6 flex gap-2">
        {FILTERS.map((filter) => (
          <button
            key={filter.label}
            onClick={() => setNetwork(filter.value)}
            className={`rounded-md px-3 py-1.5 text-sm ${
              network === filter.value
                ? "bg-indigo-600 text-white"
                : "border border-slate-700 text-slate-300 hover:bg-slate-800"
            }`}
          >
            {filter.label}
          </button>
        ))}
      </div>

      {loading && <p className="text-slate-400">Carregando...</p>}
      {error && <p className="text-red-400">{error}</p>}

      {!loading && !error && news.length === 0 && (
        <p className="text-slate-400">Nenhuma notícia encontrada.</p>
      )}

      {!loading && !error && news.length > 0 && (
        <ul className="flex flex-col gap-3">
          {news.map((item, index) => (
            <li
              key={item.url ?? index}
              className="rounded-lg border border-slate-800 bg-slate-950 p-4"
            >
              <a
                href={item.url}
                target="_blank"
                rel="noreferrer"
                className="font-medium text-slate-50 hover:text-indigo-400 hover:underline"
              >
                {item.title}
              </a>

              {item.summary && (
                <p className="mt-1 line-clamp-3 text-sm text-slate-400">
                  {item.summary}
                </p>
              )}

              <div className="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                {item.source && <span>{item.source}</span>}
                {item.published_at && (
                  <>
                    <span>·</span>
                    <span>{formatDateTime(item.published_at)}</span>
                  </>
                )}
                {item.currencies?.map((networkKey) => {
                  const config = NETWORKS[networkKey];

                  return (
                    <span
                      key={networkKey}
                      className="rounded-full px-2 py-0.5 text-xs font-medium text-white"
                      style={{ backgroundColor: config?.color ?? "#475569" }}
                    >
                      {config?.symbol ?? networkKey}
                    </span>
                  );
                })}
              </div>
            </li>
          ))}
        </ul>
      )}
    </Layout>
  );
}

export default News;
