"use client";

import Container from "@/components/layout/Container";
import SectionHeading from "@/components/ui/SectionHeading";
import { useLanguage } from "@/components/providers/LanguageProvider";

export default function ServicesPage() {
  const { t } = useLanguage();

  return (
    <main className="py-20">
      <Container className="space-y-12">
        <SectionHeading
          eyebrow={t.services.eyebrow}
          title={t.services.title}
          description={t.services.description}
        />

        <div className="grid gap-8 md:grid-cols-2">
          {t.services.items.map((item) => (
            <article
              key={item.title}
              className="glass-card soft-shadow rounded-[1.75rem] p-8"
            >
              <h2 className="text-2xl font-semibold text-zinc-900">
                {item.title}
              </h2>
              <p className="mt-3 leading-7 text-zinc-600">
                {item.description}
              </p>
            </article>
          ))}
        </div>
      </Container>
    </main>
  );
}