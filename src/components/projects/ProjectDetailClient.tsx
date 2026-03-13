"use client";

import Image from "next/image";
import { useLanguage } from "@/components/providers/LanguageProvider";
import type { Project } from "@/types/project";
import { getLocalizedText } from "@/lib/i18n/project-text";

type Props = {
  project: Project;
};

export default function ProjectDetailClient({ project }: Props) {
  const { t, locale } = useLanguage();

  return (
    <main className="py-20">
      <div className="mx-auto w-full max-w-6xl px-6 space-y-12">
        <div className="max-w-3xl space-y-5">
          <div className="inline-flex items-center gap-2 rounded-full border border-zinc-200 bg-white/80 px-4 py-2 shadow-sm">
            <span className="h-2 w-2 rounded-full bg-cyan-400" />
            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500">
              {t.projectDetail.badge}
            </p>
          </div>

          <h1 className="text-4xl font-bold tracking-tight text-zinc-900 sm:text-5xl">
            {getLocalizedText(project.title, locale)}
          </h1>

          <p className="text-lg leading-8 text-zinc-600">
            {getLocalizedText(project.longDescription, locale) ||
              getLocalizedText(project.description, locale)}
          </p>
        </div>

        <div className="glass-card soft-shadow relative aspect-video w-full overflow-hidden rounded-4xl">
          <Image
            src={project.image}
            alt={`${getLocalizedText(project.title, locale)} detailed preview`}
            fill
            className="object-cover"
            priority
          />
        </div>

        <div className="grid gap-8 lg:grid-cols-[2fr_1fr]">
          <section className="glass-card soft-shadow space-y-8 rounded-[1.75rem] p-8">
            <div className="space-y-3">
              <h2 className="text-2xl font-semibold text-zinc-900">
                {t.projectDetail.overview}
              </h2>
              <p className="leading-7 text-zinc-600">
                {getLocalizedText(project.longDescription, locale) ||
                  getLocalizedText(project.description, locale)}
              </p>
              <p className="leading-7 text-zinc-600">
                {t.projectDetail.overviewText}
              </p>
            </div>

            <div className="space-y-3">
              <h2 className="text-2xl font-semibold text-zinc-900">
                {t.projectDetail.focusTitle}
              </h2>
              <ul className="list-disc space-y-2 pl-5 text-zinc-600">
                {t.projectDetail.focusPoints.map((point) => (
                  <li key={point}>{point}</li>
                ))}
              </ul>
            </div>
          </section>

          <aside className="glass-card soft-shadow space-y-6 rounded-[1.75rem] p-6">
            <div>
              <h2 className="text-lg font-semibold text-zinc-900">
                {t.projectDetail.techStack}
              </h2>

              <ul className="mt-4 flex flex-wrap gap-2">
                {project.tech.map((item) => (
                  <li
                    key={item}
                    className="rounded-full border border-zinc-200 bg-white/80 px-3 py-1 text-sm font-medium text-zinc-700 shadow-sm"
                  >
                    {item}
                  </li>
                ))}
              </ul>
            </div>

            {(project.github || project.href) && (
              <div className="flex flex-col gap-3">
                {project.github ? (
                  <a
                    href={project.github}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex justify-center rounded-xl border border-zinc-900 bg-zinc-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-zinc-800"
                  >
                    {t.projectDetail.github}
                  </a>
                ) : null}

                {project.href ? (
                  <a
                    href={project.href}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex justify-center rounded-xl border border-zinc-200 bg-white px-5 py-3 text-sm font-semibold text-zinc-900 transition hover:bg-zinc-100"
                  >
                    {t.projectDetail.visit}
                  </a>
                ) : null}
              </div>
            )}
          </aside>
        </div>
      </div>
    </main>
  );
}