"use client";

import Container from "@/components/layout/Container";
import Button from "@/components/ui/Button";
import Image from "next/image";
import Link from "next/link";
import { useLanguage } from "../providers/LanguageProvider";

const badges = [
  "Next.js",
  "TypeScript",
  "React",
  "Python",
  "Full-stack",
  "UI/UX",
];

const stack = [
  {
    name: "Next.js",
    description: "SEO-friendly web architecture",
    icon: "/tech/nextjs.svg",
    url: "https://nextjs.org"
  },
  {
    name: "TypeScript",
    description: "Scalable and safer development",
    icon: "/tech/typescript.svg",
    url: "https://www.typescriptlang.org"
  },
  {
    name: "Python",
    description: "Automation and backend logic",
    icon: "/tech/python.svg",
    url: "https://www.python.org"
  },
  {
    name: "Tailwind CSS",
    description: "Clean and fast UI building",
    icon: "/tech/tailwindcss.svg",
    url: "https://tailwindcss.com"
  },
];

export default function Hero() {
  const { t } = useLanguage();

  return (
    <section className="relative overflow-hidden py-24 sm:py-32">
      <div className="bg-grid absolute inset-0 opacity-40" />

      <div className="hero-ring hero-ring-cyan absolute top-16 -left-15 h-64 w-64" />
      <div className="hero-ring hero-ring-violet absolute top-24 -right-10 h-72 w-72" />
      <div className="hero-ring hero-ring-cyan absolute bottom-0 left-1/3 h-56 w-56" />

      <Container className="relative">
        <div className="grid items-center gap-14 lg:grid-cols-[1.2fr_0.8fr]">
          <div className="space-y-8">
            <div className="inline-flex items-center gap-2 rounded-full border border-cyan-200 bg-white/80 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-zinc-600 shadow-sm backdrop-blur">
              <span className="h-2 w-2 rounded-full bg-cyan-400" />
              {t.hero.badge}
            </div>

            <div className="space-y-5">
              <h1 className="max-w-4xl text-5xl font-bold tracking-tight text-zinc-900 sm:text-6xl xl:text-7xl">
                {t.hero.title1}
                <br />
                <span className="text-gradient">{t.hero.title2}</span>
                <br />
                {t.hero.title3}
              </h1>

              <p className="max-w-3xl text-lg leading-8 text-zinc-600 sm:text-xl">
                {t.hero.description}
              </p>
            </div>

            <div className="flex flex-wrap gap-3">
              {badges.map((badge) => (
                <span
                  key={badge}
                  className="tech-pill rounded-full px-4 py-2 text-sm font-medium text-zinc-700"
                >
                  {badge}
                </span>
              ))}
            </div>

            <div className="flex flex-wrap gap-4">
              <Button href="/projects">{t.hero.viewProjects}</Button>
              <Button
                href="/contact"
                className="border-zinc-200 bg-white text-zinc-900 shadow-sm hover:bg-zinc-100"
              >
                {t.hero.contactMe}
              </Button>
            </div>
          </div>

          <div className="relative hidden lg:block">
            <div className="glass-card soft-shadow relative overflow-hidden rounded-4xl p-6">
              <div className="mb-6 flex items-center justify-between">
                <div>
                  <p className="text-sm font-semibold text-zinc-900">
                    {t.hero.activeStack}
                  </p>
                  <p className="text-sm text-zinc-500">
                    {t.hero.activeStackDesc}
                  </p>
                </div>

                <div className="rounded-full border border-cyan-200 bg-cyan-50 px-3 py-1 text-xs font-semibold text-cyan-700">
                  {t.hero.live}
                </div>
              </div>

              <div className="space-y-4">
                {stack.map((tech) => (
                  <Link
                    key={tech.name}
                    href={tech.url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="block rounded-2xl border border-white/70 bg-white/75 p-4 shadow-sm transition hover:shadow-md hover:border-zinc-300 hover:-translate-y-0.5"
                    prefetch={false}
                  >
                    <div className="flex items-start gap-3">
                      <Image
                        src={tech.icon}
                        alt={tech.name}
                        width={24}
                        height={24}
                        className="mt-1"
                      />

                      <div>
                        <p className="font-semibold text-zinc-900">
                          {tech.name}
                        </p>

                        <p className="mt-1 text-sm leading-6 text-zinc-500">
                          {tech.description}
                        </p>
                      </div>
                    </div>
                  </Link>
                ))}
              </div>

              <div className="mt-6 rounded-2xl bg-zinc-900 p-5 text-white">
                <p className="text-sm font-semibold uppercase tracking-[0.2em] text-zinc-400">
                  {t.hero.focus}
                </p>
                <p className="mt-2 text-lg font-semibold">
                  {t.hero.focusText}
                </p>
              </div>
            </div>
          </div>
        </div>
      </Container>
    </section>
  );
}
