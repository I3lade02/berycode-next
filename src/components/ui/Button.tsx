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
      prefetch={false}
      className={cn(
        "inline-flex items-center justify-center rounded-xl border border-zinc-900 bg-zinc-900 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-zinc-800",
        className,
      )}
    >
      {children}
    </Link>
  );
}