import Link from "next/link";
import Container from "./Container";
import Image from "next/image";

const links = [
  { href: "/", label: "Home", prefetch: false },
  { href: "/projects", label: "Projects", prefetch: false },
  { href: "/about", label: "About", prefetch: false },
  { href: "/contact", label: "Contact", prefetch: false },
];

export default function Navbar() {
  return (
    <header className="sticky top-0 z-50 border-b border-white/60 bg-white/70 backdrop-blur-xl">
      <Container className="flex h-18 items-center justify-between">
        <Link href="/" className="group flex items-center gap-3" prefetch={false}>
            <Image
            src="/logo/berycode-logo.jpg"
            alt="BeryCode"
            width={160}
            height={48}
            className="h-10 w-auto"
            priority
          />

          <div className="flex flex-col leading-none">
            <span className="text-sm font-semibold tracking-tight text-zinc-900">
              BeryCode
            </span>
            <span className="text-[11px] uppercase tracking-[0.2em] text-zinc-500">
              Portfolio
            </span>
          </div>
        </Link>

        <nav className="hidden items-center gap-2 rounded-full border border-zinc-200/80 bg-white/80 p-1 shadow-sm sm:flex">
          {links.map((link) => (
            <Link
              key={link.href}
              href={link.href}
              className="rounded-full px-4 py-2 text-sm font-medium text-zinc-600 transition hover:bg-zinc-100 hover:text-zinc-900"
              prefetch={false}
            >
              {link.label}
            </Link>
          ))}
        </nav>
      </Container>
    </header>
  );
}