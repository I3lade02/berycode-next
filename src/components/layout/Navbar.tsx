"use client";

import Image from "next/image";
import Link from "next/link";
import { useState } from "react";
import { Menu, X } from "lucide-react";

import Container from "./Container";

const links = [
  { href: "/", label: "Home", prefetch: false },
  { href: "/projects", label: "Projects", prefetch: false },
  { href: "/services", label: "Services", prefetch: false },
  { href: "/about", label: "About", prefetch: false },
  { href: "/contact", label: "Contact", prefetch: false },
];

export default function Navbar() {
  const [isOpen, setIsOpen] = useState(false);

  function closeMenu() {
    setIsOpen(false);
  }

  function toggleMenu() {
    setIsOpen((prev) => !prev);
  }

  return (
    <header className="sticky top-0 z-50 border-b border-white/60 bg-white/70 backdrop-blur-xl dark:border-zinc-800/80 dark:bg-zinc-950/70">
      <Container className="relative flex h-18 items-center justify-between">
        <Link
          href="/"
          prefetch={false}
          className="flex items-center gap-3"
          onClick={closeMenu}
        >
          <Image
            src="/logo/berycode-logo.jpg"
            alt="BeryCode"
            width={160}
            height={48}
            className="h-10 w-auto"
            priority
          />
        </Link>

        <nav className="hidden items-center gap-2 rounded-full border border-zinc-200 bg-white/80 p-1 shadow-sm sm:flex dark:border-zinc-800/80 dark:bg-zinc-900/80">
          {links.map((link) => (
            <Link
              key={link.href}
              href={link.href}
              prefetch={false}
              className="rounded-full px-4 py-2 text-sm font-medium text-zinc-600 transition hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
            >
              {link.label}
            </Link>
          ))}
        </nav>

        <button
          type="button"
          onClick={toggleMenu}
          aria-label={isOpen ? "Close menu" : "Open menu"}
          aria-expanded={isOpen}
          className="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-zinc-200 bg-white text-zinc-900 shadow-sm transition hover:bg-zinc-100 sm:hidden dark:border-zinc-800 dark:text-zinc-100 dark:hover:bg-zinc-800"
        >
          {isOpen ? <X size={20} /> : <Menu size={20} />}
        </button>

        {isOpen && (
          <div className="absolute top-full right-0 left-0 mt-3 sm:hidden">
            <div className="soft-shadow overflow-hidden rounded-3xl border border-white/70 bg-white/90 p-3 dark:border-zinc-800/80 dark:bg-zinc-900/90">
              <nav className="flex flex-col">
                {links.map((link) => (
                  <Link
                    key={link.href}
                    href={link.href}
                    prefetch={false}
                    onClick={closeMenu}
                    className="rounded-2xl px-4 py-3 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                  >
                    {link.label}
                  </Link>
                ))}
              </nav>
            </div>
          </div>
        )}
      </Container>
    </header>
  );
}
