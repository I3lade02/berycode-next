"use client";

import Image from "next/image";
import Link from "next/link";
import Container from "./Container";
import LanguageSwitcher from "./LanguageSwitcher";
import { useLanguage } from "@/components/providers/LanguageProvider";

export default function Navbar() {
  const { t } = useLanguage();

  const links = [
    { href: "/", label: t.nav.home },
    { href: "/projects", label: t.nav.projects },
    { href: "/services", label: t.nav.services },
    { href: "/about", label: t.nav.about },
    { href: "/contact", label: t.nav.contact },
  ];

  return (
    <header className="sticky top-0 z-50 border-b border-white/60 bg-white/70 backdrop-blur-xl">
      <Container className="flex h-18 items-center justify-between gap-4">
        <Link href="/" prefetch={false} className="flex items-center gap-3">
          <Image
            src="/logo/berycode-logo.jpg"
            alt="BeryCode"
            width={160}
            height={48}
            className="h-10 w-auto"
            priority
          />
        </Link>

        <div className="ml-auto flex items-center gap-3">
          <nav className="hidden items-center gap-2 rounded-full border border-zinc-200/80 bg-white/80 p-1 shadow-sm md:flex">
            {links.map((link) => (
              <Link
                key={link.href}
                href={link.href}
                prefetch={false}
                className="rounded-full px-4 py-2 text-sm font-medium text-zinc-600 transition hover:bg-zinc-100 hover:text-zinc-900"
              >
                {link.label}
              </Link>
            ))}
          </nav>

          <LanguageSwitcher />
        </div>
      </Container>
    </header>
  );
}