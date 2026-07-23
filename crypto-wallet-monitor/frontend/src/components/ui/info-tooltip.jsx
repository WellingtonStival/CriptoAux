import * as Popover from "@radix-ui/react-popover";
import { HelpCircle } from "lucide-react";
import { cn } from "../../lib/utils";

/**
 * Icone "?" que abre uma explicacao curta ao clicar - clique em vez de
 * hover de proposito, pra funcionar em touch/mobile tambem (hover nao
 * existe em tela sensivel ao toque).
 */
function InfoTooltip({ children, className }) {
  return (
    <Popover.Root>
      <Popover.Trigger asChild>
        <button
          type="button"
          onClick={(event) => event.stopPropagation()}
          className={cn(
            "inline-flex size-4 shrink-0 items-center justify-center rounded-full text-muted-foreground hover:text-foreground",
            className
          )}
          aria-label="Mais informações"
        >
          <HelpCircle className="size-full" />
        </button>
      </Popover.Trigger>
      <Popover.Portal>
        <Popover.Content
          side="top"
          align="start"
          sideOffset={6}
          collisionPadding={12}
          className="z-50 max-w-[280px] rounded-lg border border-border bg-card p-3 text-xs leading-relaxed text-foreground shadow-lg"
        >
          {children}
          <Popover.Arrow className="fill-border" />
        </Popover.Content>
      </Popover.Portal>
    </Popover.Root>
  );
}

export { InfoTooltip };
