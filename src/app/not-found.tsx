"use client";

import Link from "next/link";
import Container from "@/components/layout/Container";
import { useLanguage } from "@/components/providers/LanguageProvider";
import { title } from "process";

export default function NotFoundPage() {
  const { locale } = useLanguage();

  const content = {
    cs: {
      badge: "404 error",
      title: "Tahle stránka se rozpadla",
      description: "Vypadá to, že ses dostal na místo, kde už cesta nedrží pohromadě. Zkus návrat na domovskou stránku nebo se podívej na projekty",
      home: "Zpět domů",
      projects: "Projekty",
      cards: [
        "route = undefined",
        "status = 404",
        "ui.panel = missing",
        "fallback = /projects",
      ],
    },
    en: {
      badge: "404 error",
      title: "This page fell appart",
      description: "Looks like you landed somewhere the route no longer holds together. Try to go back on home page or visit page with projects",
      home: "Back home",
      projects: "Projects",
      cards: [
        "route = undefined",
        "status = 404",
        "ui.panel = missing",
        "fallback = /projects",
        "component.load = failed"
      ],
    },
  } as const;

  const c = content[locale];

    return (
    <main className="relative overflow-hidden py-24 sm:py-32">
      <div className="bg-grid absolute inset-0 opacity-40" />
      <div className="hero-ring hero-ring-cyan absolute top-8 -left-10 h-56 w-56" />
      <div className="hero-ring hero-ring-violet absolute top-12 -right-5 h-72 w-72" />
      <div className="hero-ring hero-ring-cyan absolute bottom-0 left-1/3 h-56 w-56" />

      <Container className="relative">
        <div className="grid items-center gap-14 lg:grid-cols-[1.05fr_0.95fr]">
          <div className="space-y-8">
            <div className="inline-flex items-center gap-2 rounded-full border border-cyan-200 bg-white/80 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-zinc-600 shadow-sm backdrop-blur">
              <span className="h-2 w-2 rounded-full bg-cyan-400" />
              {c.badge}
            </div>

            <div className="space-y-5">
              <p className="text-gradient text-7xl font-black tracking-tight sm:text-8xl xl:text-9xl">
                404
              </p>

              <h1 className="max-w-3xl text-4xl font-bold tracking-tight text-zinc-900 sm:text-5xl">
                {c.title}
              </h1>

              <p className="max-w-2xl text-lg leading-8 text-zinc-600">
                {c.description}
              </p>
            </div>

            <div className="flex flex-wrap gap-4">
              <Link
                href="/"
                prefetch={false}
                className="inline-flex items-center justify-center rounded-xl border border-zinc-900 bg-zinc-900 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-zinc-800"
              >
                {c.home}
              </Link>

              <Link
                href="/projects"
                prefetch={false}
                className="inline-flex items-center justify-center rounded-xl border border-zinc-200 bg-white px-5 py-3 text-sm font-semibold text-zinc-900 shadow-sm transition hover:-translate-y-0.5 hover:bg-zinc-100"
              >
                {c.projects}
              </Link>
            </div>
          </div>

          <div className="relative mx-auto h-110 w-full max-w-130">
            <div className="glass-card soft-shadow absolute top-2 left-6 w-52 rounded-3xl p-4 rotate-[-8deg] float-fast">
              <p className="font-mono text-sm text-zinc-700">{c.cards[0]}</p>
            </div>

            <div className="glass-card soft-shadow absolute top-20 right-4 w-56 rounded-3xl p-5 rotate-[7deg] float-medium">
              <p className="font-mono text-sm text-zinc-700">{c.cards[1]}</p>
            </div>

            <div className="glass-card soft-shadow absolute top-36 left-14 w-60 rounded-3xl p-5 rotate-[4deg] float-slow">
              <p className="font-mono text-sm text-zinc-700">{c.cards[2]}</p>
            </div>

            <div className="glass-card soft-shadow absolute right-10 bottom-20 w-52 rounded-3xl p-4 -rotate-6 float-medium">
              <p className="font-mono text-sm text-zinc-700">{c.cards[3]}</p>
            </div>

            <div className="glass-card soft-shadow absolute bottom-10 right-70 w-48 rounded-3xl p-4 rotate-12 float-slow">
              <p className="font-mono text-sm text-zinc-700">component.load = failed</p>
            </div>

            <div className="glass-card soft-shadow absolute bottom-45 right-5 w-50 rounded-3xl p-4 -rotate-12 float-medium">
              <p className="font-mono text-sm text-zinc-700">git.checkout("home")</p>
            </div>
          </div>
        </div>
      </Container>
    </main>
  );
}