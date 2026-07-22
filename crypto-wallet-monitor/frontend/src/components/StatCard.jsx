import { Card, CardContent } from "./ui/card";

function StatCard({ icon, label, value, extra }) {
  const Icon = icon;

  return (
    <Card>
      <CardContent className="pt-4">
        <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
          <Icon className="size-3.5" />
          {label}
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
