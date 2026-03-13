"use client";

import Image from "next/image";
import Link from "next/link";

import Container from "@/components/layout/Container";
import SectionHeading from "@/components/ui/SectionHeading";
import { useLanguage } from "@/components/providers/LanguageProvider";
import { projects } from "@/content/projects";
import { getLocalizedText } from "@/lib/i18n/project-text";

export default function ProjectsPage() {
  const { t, locale } = useLanguage();

  return (
    <main className="py-20">
      <Container className="space-y-12">
        <SectionHeading
          eyebrow={t.projectsPage.eyebrow}
          title={t.projectsPage.title}
          description={t.projectsPage.description}
        />

        <div className="grid gap-8 md:grid-cols-2 xl:grid-cols-3">
          {projects.map((project) => (
            <article
              key={project.slug}
              className="glass-card card-hover overflow-hidden rounded-[1.75rem]"
            >
              <div className="relative aspect-16/10 w-full bg-zinc-100">
                <Image
                  src={project.image}
                  alt={`${getLocalizedText(project.title, locale)} preview`}
                  fill
                  className="object-cover transition duration-300 hover:scale-[1.03]"
                />
                <div className="absolute inset-x-0 bottom-0 h-24 bg-linear-to-t from-black/20 to-transparent" />
              </div>

              <div className="space-y-4 p-6">
                <h2 className="text-xl font-semibold text-zinc-900">
                  {getLocalizedText(project.title, locale)}
                </h2>

                <p className="text-sm leading-6 text-zinc-600">
                  {getLocalizedText(project.description, locale)}
                </p>

                <ul className="flex flex-wrap gap-2">
                  {project.tech.map((item) => (
                    <li
                      key={item}
                      className="rounded-full border border-zinc-200 bg-white/80 px-3 py-1 text-xs font-medium text-zinc-700 shadow-sm"
                    >
                      {item}
                    </li>
                  ))}
                </ul>

                <Link
                  href={`/projects/${project.slug}`}
                  prefetch={false}
                  className="inline-flex items-center text-sm font-semibold text-zinc-900 hover:text-cyan-700"
                >
                  {t.projectsPage.readMore}
                </Link>
              </div>
            </article>
          ))}
        </div>
      </Container>
    </main>
  );
}