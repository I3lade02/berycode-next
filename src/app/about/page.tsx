"use client";

import Container from "@/components/layout/Container";
import SectionHeading from "@/components/ui/SectionHeading";
import { useLanguage } from "@/components/providers/LanguageProvider";

export default function AboutPage() {
  const { t } = useLanguage();

  return (
    <main className="py-20">
      <Container className="space-y-12">
        <SectionHeading
          eyebrow={t.aboutPage.eyebrow}
          title={t.aboutPage.title}
          description={t.aboutPage.description}
        />

        <div className="grid gap-8 lg:grid-cols-[1.15fr_0.85fr]">
          <div className="glass-card soft-shadow rounded-[1.75rem] p-8">
            <div className="space-y-5 text-base leading-7 text-zinc-600">
              <p>{t.aboutPage.p1}</p>
              <p>{t.aboutPage.p2}</p>
              <p>{t.aboutPage.p3}</p>
            </div>
          </div>

          <div className="glass-card soft-shadow rounded-[1.75rem] p-8">
            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500">
              {t.aboutPage.focusAreas}
            </p>

            <ul className="mt-5 space-y-4 text-zinc-700">
              {t.aboutPage.areas.map((item) => (
                <li
                  key={item}
                  className="rounded-2xl border border-white/70 bg-white/80 p-4"
                >
                  {item}
                </li>
              ))}
            </ul>
          </div>
        </div>
      </Container>
    </main>
  );
}