"use client";

import Container from "@/components/layout/Container";
import ContactForm from "@/components/sections/ContactForm";
import SectionHeading from "@/components/ui/SectionHeading";
import { useLanguage } from "@/components/providers/LanguageProvider";

export default function ContactPage() {
  const { t } = useLanguage();

  return (
    <main className="py-20">
      <Container className="space-y-12">
        <SectionHeading
          eyebrow={t.contactPage.eyebrow}
          title={t.contactPage.title}
          description={t.contactPage.description}
        />

        <div className="grid gap-8 lg:grid-cols-[1fr_0.85fr]">
          <ContactForm />

          <div className="glass-card soft-shadow rounded-[1.75rem] p-8">
            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500">
              {t.contactPage.collaboration}
            </p>

            <h2 className="mt-3 text-2xl font-semibold tracking-tight text-zinc-900">
              {t.contactPage.talkAbout}
            </h2>

            <ul className="mt-6 space-y-4 text-zinc-600">
              {t.contactPage.items.map((item) => (
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