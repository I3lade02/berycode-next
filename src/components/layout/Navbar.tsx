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
    <header className="sticky top-0 z-50 border-b border-white/60 bg-white/70 backdrop-blur-xl">
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

        <nav className="hidden items-center gap-2 rounded-full border border-zinc-200/80 bg-white/80 p-1 shadow-sm sm:flex">
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

        <button
          type="button"
          onClick={toggleMenu}
          aria-label={isOpen ? "Close menu" : "Open menu"}
          aria-expanded={isOpen}
          className="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-zinc-200 bg-white text-zinc-900 shadow-sm transition hover:bg-zinc-100 sm:hidden"
        >
          {isOpen ? <X size={20} /> : <Menu size={20} />}
        </button>

        {isOpen && (
          <div className="absolute top-full right-0 left-0 mt-3 sm:hidden">
            <div className="soft-shadow overflow-hidden rounded-3xl border border-white/70 bg-white/90 p-3">
              <nav className="flex flex-col">
                {links.map((link) => (
                  <Link
                    key={link.href}
                    href={link.href}
                    prefetch={false}
                    onClick={closeMenu}
                    className="rounded-2xl px-4 py-3 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100 hover:text-zinc-900"
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