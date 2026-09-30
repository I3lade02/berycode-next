"use client";

import { ShieldAlert } from "lucide-react";

import Container from "@/components/layout/Container";
import SupportForm from "@/components/support/SupportForm";
import SectionHeading from "@/components/ui/SectionHeading";
import { useLanguage } from "@/components/providers/LanguageProvider";

export default function SupportPageClient() {
  const { t } = useLanguage();
  const p = t.supportPage;

  return (
    <main className="py-20">
      <Container className="space-y-12">
        <SectionHeading
          eyebrow={p.eyebrow}
          title={p.title}
          description={p.description}
        />

        <div className="grid items-start gap-8 lg:grid-cols-[1.35fr_0.65fr]">
          <SupportForm />

          <aside className="glass-card soft-shadow space-y-6 rounded-[1.75rem] p-8">
            <div>
              <h2 className="text-xs font-semibold tracking-[0.2em] text-zinc-500 uppercase">
                {p.howItWorks}
              </h2>

              <ol className="mt-5 space-y-4 text-zinc-600">
                {p.steps.map((step, index) => (
                  <li
                    key={step}
                    className="flex gap-3 rounded-2xl border border-white/70 bg-white/80 p-4"
                  >
                    <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-zinc-900 text-xs font-semibold text-white">
                      {index + 1}
                    </span>
                    <span className="text-sm leading-6">{step}</span>
                  </li>
                ))}
              </ol>
            </div>

            <div className="space-y-2">
              <h3 className="text-sm font-semibold text-zinc-900">
                {p.projectHintTitle}
              </h3>
              <p className="text-sm leading-6 text-zinc-600">{p.projectHint}</p>
            </div>

            <p className="flex gap-2 rounded-2xl border border-amber-200 bg-amber-50/80 p-4 text-sm leading-6 text-amber-900">
              <ShieldAlert className="mt-0.5 h-4 w-4 shrink-0" aria-hidden />
              {p.privacyNote}
            </p>
          </aside>
        </div>
      </Container>
    </main>
  );
}
