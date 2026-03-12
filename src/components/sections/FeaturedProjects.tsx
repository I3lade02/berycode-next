"use client";

import Image from "next/image";
import Link from "next/link";

import Container from "@/components/layout/Container";
import SectionHeading from "@/components/ui/SectionHeading";
import { useLanguage } from "@/components/providers/LanguageProvider";
import { getFeaturedProjects } from "@/content/projects";

export default function FeaturedProjects() {
  const featuredProjects = getFeaturedProjects();
  const { t } = useLanguage();

  return (
    <section className="section-tint py-24">
      <Container className="space-y-12">
        <SectionHeading
          eyebrow={t.featuredProjects.eyebrow}
          title={t.featuredProjects.title}
          description={t.featuredProjects.description}
        />

        <div className="grid gap-8 md:grid-cols-2 xl:grid-cols-3">
          {featuredProjects.map((project) => (
            <article
              key={project.slug}
              className="glass-card card-hover overflow-hidden rounded-[1.75rem]"
            >
              <div className="relative aspect-16/10 w-full overflow-hidden bg-zinc-100">
                <Image
                  src={project.image}
                  alt={`${project.title} project preview`}
                  fill
                  className="object-cover transition duration-300 hover:scale-[1.03]"
                />
                <div className="absolute inset-x-0 bottom-0 h-24 bg-linear-to-t from-black/20 to-transparent" />
              </div>

              <div className="space-y-5 p-6">
                <div>
                  <h3 className="text-xl font-semibold text-zinc-900">
                    {project.title}
                  </h3>
                  <p className="mt-2 text-sm leading-6 text-zinc-600">
                    {project.description}
                  </p>
                </div>

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
                  {t.featuredProjects.viewProject}
                </Link>
              </div>
            </article>
          ))}
        </div>
      </Container>
    </section>
  );
}