import * as React from "react";
import { cn } from "@/lib/utils";

type InputProps = React.InputHTMLAttributes<HTMLInputElement>;

const Input = React.forwardRef<HTMLInputElement, InputProps>(
    ({ className, ...props}, ref) => {
        return (
            <input
                ref={ref}
                className={cn(
                    "flex h-12 w-full rounded-xl border border-zinc-300 bg-white px-4 text-sm text-zinc-900 placeholder:text-zinc-400 focus:border-zinc-900",
                    className,
                )}
                {...props}
            />
        );
    },
);

Input.displayName = "Input";

export default Input;