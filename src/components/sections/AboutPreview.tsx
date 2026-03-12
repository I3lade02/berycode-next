"use client";

import Container from "@/components/layout/Container";
import SectionHeading from "@/components/ui/SectionHeading";
import { useLanguage } from "@/components/providers/LanguageProvider";

export default function AboutPreview() {
  const { t } = useLanguage();

  return (
    <section className="relative py-24">
      <Container>
        <div className="grid gap-10 lg:grid-cols-[0.95fr_1.05fr] lg:items-start">
          <SectionHeading
            eyebrow={t.aboutPreview.eyebrow}
            title={t.aboutPreview.title}
            description={t.aboutPreview.description}
          />

          <div className="glass-card soft-shadow rounded-[1.75rem] p-8">
            <div className="space-y-4 text-base leading-7 text-zinc-600">
              <p>{t.aboutPreview.p1}</p>
              <p>{t.aboutPreview.p2}</p>
              <p>{t.aboutPreview.p3}</p>
            </div>
          </div>
        </div>
      </Container>
    </section>
  );
}