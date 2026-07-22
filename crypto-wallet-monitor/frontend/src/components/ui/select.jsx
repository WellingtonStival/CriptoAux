import { ChevronDown } from "lucide-react";
import { cn } from "../../lib/utils";

function Select({ className, children, ...props }) {
  return (
    <div className="relative">
      <select
        className={cn(
          "w-full appearance-none rounded-md border border-input bg-background px-3 py-2 pr-8 text-sm text-foreground focus:border-ring focus:outline-none disabled:opacity-60",
          className
        )}
        {...props}
      >
        {children}
      </select>
      <ChevronDown className="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
    </div>
  );
}

export { Select };
