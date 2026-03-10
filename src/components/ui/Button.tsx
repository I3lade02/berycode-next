import Link from "next/link";
import { cn } from "@/lib/utils";

type ButtonProps = {
    href: string;
    children: React.ReactNode;
    className?: string;
};

export default function Button({ href, children, className }: ButtonProps) {
    return (
        <Link
            href={href}
            className={cn(
                "inline-flex items-center justify-center rounded-xl bg-zinc-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-zinc-800",
                className,
            )}
        >
            {children}
        </Link>
    );
}