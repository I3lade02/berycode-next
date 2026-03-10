import type { Metadata } from "next";
import { notFound } from "next/navigation";

import Container from "@/components/layout/Container";
import { getProjectBySlug, projects } from "@/content/projects";
import { siteConfig } from "@/lib/metadata";

type ProjectPageProps = {
  params: Promise<{
    slug: string;
  }>;
};

export async function generateStaticParams() {
  return projects.map((project) => ({
    slug: project.slug,
  }));
}

export async function generateMetadata({
  params,
}: ProjectPageProps): Promise<Metadata> {
  const { slug } = await params;
  const project = getProjectBySlug(slug);

  if (!project) {
    return {
      title: "Project not found",
    };
  }

  const title = project.title;
  const description = project.longDescription ?? project.description;
  const url = `${siteConfig.url}/projects/${project.slug}`;

  return {
    title,
    description,
    alternates: {
      canonical: url,
    },
    openGraph: {
      title,
      description,
      url,
      type: "article",
      siteName: siteConfig.name,
    },
    twitter: {
      card: "summary_large_image",
      title,
      description,
    },
  };
}

export default async function ProjectDetailPage({
  params,
}: ProjectPageProps) {
  const { slug } = await params;
  const project = getProjectBySlug(slug);

  if (!project) {
    notFound();
  }

  return (
    <main className="py-20">
      <Container className="space-y-10">
        <div className="max-w-3xl space-y-5">
          <p className="text-sm font-semibold uppercase tracking-[0.2em] text-zinc-500">
            Project
          </p>

          <h1 className="text-4xl font-bold tracking-tight text-zinc-900 sm:text-5xl">
            {project.title}
          </h1>

          <p className="text-lg leading-8 text-zinc-600">
            {project.longDescription ?? project.description}
          </p>
        </div>

        <div className="rounded-2xl border border-zinc-200 p-6 shadow-sm">
          <h2 className="text-xl font-semibold text-zinc-900">Tech stack</h2>

          <ul className="mt-4 flex flex-wrap gap-2">
            {project.tech.map((item) => (
              <li
                key={item}
                className="rounded-full bg-zinc-100 px-3 py-1 text-sm font-medium text-zinc-700"
              >
                {item}
              </li>
            ))}
          </ul>
        </div>

        {(project.github || project.href) && (
          <div className="flex flex-wrap gap-4">
            {project.github ? (
              <a
                href={project.github}
                target="_blank"
                rel="noreferrer"
                className="inline-flex rounded-xl bg-zinc-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-zinc-800"
              >
                View GitHub
              </a>
            ) : null}

            {project.href ? (
              <a
                href={project.href}
                target="_blank"
                rel="noreferrer"
                className="inline-flex rounded-xl bg-zinc-100 px-5 py-3 text-sm font-semibold text-zinc-900 transition hover:bg-zinc-200"
              >
                Visit Project
              </a>
            ) : null}
          </div>
        )}
      </Container>
    </main>
  );
}