import Image from "next/image";
import type { Metadata } from "next";
import { notFound } from "next/navigation";
import Link from "next/link";

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
      images: [
        {
          url: project.image,
          alt: `${project.title} preview`,
        },
      ],
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
      <Container className="space-y-12">
        <div className="max-w-3xl space-y-5">
          <div className="inline-flex items-center gap-2 rounded-full border border-zinc-200 bg-white/80 px-4 py-2 shadow-sm">
            <span className="h-2 w-2 rounded-full bg-cyan-400" />
            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500">
              Project
            </p>
          </div>

          <h1 className="text-4xl font-bold tracking-tight text-zinc-900 sm:text-5xl">
            {project.title}
          </h1>

          <p className="text-lg leading-8 text-zinc-600">
            {project.longDescription ?? project.description}
          </p>
        </div>

        <div className="glass-card soft-shadow relative aspect-video w-full overflow-hidden rounded-4xl">
          <Image
            src={project.image}
            alt={`${project.title} detailed preview`}
            fill
            className="object-cover"
            priority
          />
        </div>

        <div className="grid gap-8 lg:grid-cols-[2fr_1fr]">
          <section className="glass-card soft-shadow space-y-8 rounded-[1.75rem] p-8">
            <div className="space-y-3">
              <h2 className="text-2xl font-semibold text-zinc-900">Overview</h2>
              <p className="leading-7 text-zinc-600">
                {project.longDescription ?? project.description}
              </p>
              <p className="leading-7 text-zinc-600">
                This project shows my interest in creating something practical and
                visually strong software with help of modern technologies, clean
                architecture and emphasis on usability
              </p>
            </div>

            <div className="space-y-3">
              <h2 className="text-2xl font-semibold text-zinc-900">
                What I focused on
              </h2>
              <ul className="list-disc space-y-2 pl-5 text-zinc-600">
                <li>Readable and natural user interface</li>
                <li>Sustainable code structure</li>
                <li>Responsive frontend and modern tools</li>
                <li>Functionality with space for further expansion</li>
              </ul>
            </div>
          </section>

          <aside className="glass-card soft-shadow space-y-6 rounded-[1.75rem] p-6">
            <div>
              <h2 className="text-lg font-semibold text-zinc-900">Tech stack</h2>

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
                  <Link
                    href={project.github}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex justify-center rounded-xl border border-zinc-900 bg-zinc-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-zinc-800"
                    prefetch={false}
                  >
                    View GitHub
                  </Link>
                ) : null}

                {project.href ? (
                  <Link
                    href={project.href}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex justify-center rounded-xl border border-zinc-200 bg-white px-5 py-3 text-sm font-semibold text-zinc-900 transition hover:bg-zinc-100"
                    prefetch={false}
                  >
                    Visit Project
                  </Link>
                ) : null}
              </div>
            )}
          </aside>
        </div>
      </Container>
    </main>
  );
}