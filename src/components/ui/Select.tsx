import * as React from "react";
import { ChevronDown } from "lucide-react";
import { cn } from "@/lib/utils";

type SelectProps = React.SelectHTMLAttributes<HTMLSelectElement>;

const Select = React.forwardRef<HTMLSelectElement, SelectProps>(
  ({ className, children, ...props }, ref) => {
    return (
      <div className="relative">
        <select
          ref={ref}
          className={cn(
            "flex h-12 w-full appearance-none rounded-xl border border-zinc-200 bg-white/85 pr-11 pl-4 text-sm text-zinc-900 shadow-sm focus:border-zinc-900 [&>option]:text-zinc-900",
            className,
          )}
          {...props}
        >
          {children}
        </select>
        <ChevronDown
          className="pointer-events-none absolute top-1/2 right-4 h-4 w-4 -translate-y-1/2 text-zinc-500"
          aria-hidden
        />
      </div>
    );
  },
);

Select.displayName = "Select";

export default Select;
