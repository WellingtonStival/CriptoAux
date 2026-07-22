import { cn } from "../../lib/utils";

function Label({ className, ...props }) {
  return (
    <label
      className={cn("mb-1.5 block text-sm text-muted-foreground", className)}
      {...props}
    />
  );
}

export { Label };
