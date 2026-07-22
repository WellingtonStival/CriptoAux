import { Card, CardContent } from "./ui/card";
import logoIcon from "../assets/logo-icon.png";
import logoWordmark from "../assets/logo-wordmark.png";

function AuthLayout({ title, children }) {
  return (
    <div className="flex min-h-screen flex-col items-center justify-center gap-6 bg-background px-4">
      <span className="flex items-center gap-2.5">
        <img src={logoIcon} alt="" className="size-[46px]" />
        <img src={logoWordmark} alt="Nexfolio" className="h-[32px]" />
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
