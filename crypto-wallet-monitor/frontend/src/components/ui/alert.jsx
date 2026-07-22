import { cva } from "class-variance-authority";
import { cn } from "../../lib/utils";

const alertVariants = cva(
  "relative flex items-start gap-3 rounded-lg border p-4 text-sm [&_svg]:size-4 [&_svg]:mt-0.5 [&_svg]:shrink-0",
  {
    variants: {
      variant: {
        default: "border-border bg-card text-foreground",
        destructive: "border-destructive/30 bg-destructive-muted text-destructive",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  }
);

function Alert({ className, variant, ...props }) {
  return (
    <div role="alert" className={cn(alertVariants({ variant }), className)} {...props} />
  );
}

function AlertDescription({ className, ...props }) {
  return <div className={cn("leading-relaxed", className)} {...props} />;
}

export { Alert, AlertDescription };
