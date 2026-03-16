"use client";

import { useState } from "react";
import Image from "next/image";
import Link from "next/link";
import { usePathname } from "next/navigation";

import Container from "./Container";
import LanguageSwitcher from "./LanguageSwitcher";
import { useLanguage } from "@/components/providers/LanguageProvider";

export default function Navbar() {
  const { t } = useLanguage();
  const pathname = usePathname();
  const [open, setOpen] = useState(false);

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
        <Link href="/" className="flex items-center gap-3">
          <Image
            src="/logo/berycode-logo.jpg"
            alt="BeryCode"
            width={160}
            height={48}
            className="h-10 w-auto"
            priority
          />
        </Link>

        {/* desktop menu */}
        <nav className="hidden items-center gap-2 rounded-full border border-zinc-200/80 bg-white/80 p-1 shadow-sm md:flex">
          {links.map((link) => (
            <Link
              key={link.href}
              href={link.href}
              className={`rounded-full px-4 py-2 text-sm font-medium transition
                ${
                  pathname === link.href
                    ? "bg-zinc-900 text-white"
                    : "text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900"
                }
              `}
            >
              {link.label}
            </Link>
          ))}
        </nav>

        <div className="flex items-center gap-3">
          <LanguageSwitcher />

          {/* hamburger */}
          <button
            onClick={() => setOpen((p) => !p)}
            className="relative flex h-11 w-11 items-center justify-center rounded-xl border border-zinc-200 bg-white md:hidden"
          >
            <span className="relative h-4 w-5">
              <span
                className={`absolute left-0 h-0.5 w-5 bg-zinc-900 transition ${
                  open ? "top-1/2 rotate-45" : "top-0"
                }`}
              />
              <span
                className={`absolute top-1/2 h-0.5 w-5 -translate-y-1/2 bg-zinc-900 transition ${
                  open ? "opacity-0" : ""
                }`}
              />
              <span
                className={`absolute left-0 h-0.5 w-5 bg-zinc-900 transition ${
                  open ? "top-1/2 -rotate-45" : "bottom-0"
                }`}
              />
            </span>
          </button>
        </div>
      </Container>

      {/* mobile menu */}
      <div
        className={`overflow-hidden border-t border-zinc-200 bg-white transition-all duration-300 md:hidden ${
          open ? "max-h-96 opacity-100" : "max-h-0 opacity-0"
        }`}
      >
        <Container className="py-4 flex flex-col">
          {links.map((link) => (
            <Link
              key={link.href}
              href={link.href}
              onClick={() => setOpen(false)}
              className="py-3 text-base font-medium text-zinc-700 hover:text-zinc-900"
            >
              {link.label}
            </Link>
          ))}
        </Container>
      </div>
    </header>
  );
}