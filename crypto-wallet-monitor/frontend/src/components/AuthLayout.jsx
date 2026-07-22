import { Card, CardContent } from "./ui/card";

function AuthLayout({ title, children }) {
  return (
    <div className="flex min-h-screen flex-col items-center justify-center gap-6 bg-background px-4">
      <span className="text-xl font-semibold tracking-tight text-foreground">
        Nexfolio
      </span>

      <Card className="w-full max-w-sm">
        <CardContent className="pt-6">
          <h2 className="mb-6 text-xl font-bold text-foreground">{title}</h2>
          {children}
        </CardContent>
      </Card>
    </div>
  );
}

export default AuthLayout;
