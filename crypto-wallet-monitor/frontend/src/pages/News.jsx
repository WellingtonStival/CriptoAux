import { useEffect, useState } from "react";
import { Newspaper, AlertCircle, ExternalLink } from "lucide-react";
import Layout from "../components/Layout";
import { getNews } from "../services/api";
import { formatDateTime } from "../utils/format";
import { NETWORKS } from "../config/networks";
import { Card, CardContent } from "../components/ui/card";
import { Badge } from "../components/ui/badge";
import { Alert, AlertDescription } from "../components/ui/alert";
import { Skeleton } from "../components/ui/skeleton";
import { Tabs, TabsList, TabsTrigger } from "../components/ui/tabs";

const FILTERS = [
  { value: "all", label: "Todas" },
  { value: "ethereum", label: "Ethereum" },
  { value: "solana", label: "Solana" },
  { value: "bitcoin", label: "Bitcoin" },
];

function News() {
  const [network, setNetwork] = useState("all");
  const [news, setNews] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    async function loadNews() {
      try {
        setLoading(true);
        setError(null);
        const response = await getNews(network === "all" ? null : network);
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
      <h1 className="mb-6 flex items-center gap-2 text-2xl font-bold text-foreground">
        <Newspaper className="size-6" />
        Notícias
      </h1>

      <Tabs value={network} onValueChange={setNetwork} className="mb-6">
        <TabsList>
          {FILTERS.map((filter) => (
            <TabsTrigger key={filter.value} value={filter.value}>
              {filter.label}
            </TabsTrigger>
          ))}
        </TabsList>
      </Tabs>

      {loading && (
        <div className="flex flex-col gap-3">
          {Array.from({ length: 4 }).map((_, index) => (
            <Skeleton key={index} className="h-28 w-full" />
          ))}
        </div>
      )}

      {error && (
        <Alert variant="destructive">
          <AlertCircle />
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      {!loading && !error && news.length === 0 && (
        <p className="text-muted-foreground">Nenhuma notícia encontrada.</p>
      )}

      {!loading && !error && news.length > 0 && (
        <ul className="flex flex-col gap-3">
          {news.map((item, index) => (
            <li key={item.url ?? index}>
              <Card>
                <CardContent className="pt-4">
                  <a
                    href={item.url}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex items-start gap-1.5 font-medium text-foreground hover:text-primary"
                  >
                    {item.title}
                    <ExternalLink className="mt-1 size-3.5 shrink-0 text-muted-foreground" />
                  </a>

                  {item.summary && (
                    <p className="mt-1 line-clamp-3 text-sm text-muted-foreground">
                      {item.summary}
                    </p>
                  )}

                  <div className="mt-2 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
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
                        <Badge
                          key={networkKey}
                          className="text-white"
                          style={{ backgroundColor: config?.color ?? "#475569" }}
                        >
                          {config?.symbol ?? networkKey}
                        </Badge>
                      );
                    })}
                  </div>
                </CardContent>
              </Card>
            </li>
          ))}
        </ul>
      )}
    </Layout>
  );
}

export default News;
