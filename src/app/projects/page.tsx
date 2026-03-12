import Image from "next/image";
import Link from "next/link";

import Container from "@/components/layout/Container";
import SectionHeading from "@/components/ui/SectionHeading";
import { projects } from "@/content/projects";
import { createMetadata } from "@/lib/seo";

export const metadata = createMetadata({
  title: "Projects",
  description:
    "Explore web development, interactive and software projects built by Ondra Beránek.",
  path: "/projects",
});

export default function ProjectsPage() {
  return (
    <main className="py-20">
      <Container className="space-y-12">
        <SectionHeading
          eyebrow="Projects"
          title="Selected projects"
          description="Wider look on software, web apps, experiments and tools, which i built with emphasis on clean code structure and pleaseable UI."
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
                  alt={`${project.title} preview`}
                  fill
                  className="object-cover transition duration-300 hover:scale-[1.03]"
                />
                <div className="absolute inset-x-0 bottom-0 h-24 bg-linear-to-t from-black/20 to-transparent" />
              </div>

              <div className="space-y-4 p-6">
                <h2 className="text-xl font-semibold text-zinc-900">
                  {project.title}
                </h2>

                <p className="text-sm leading-6 text-zinc-600">
                  {project.description}
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
                  className="inline-flex items-center text-sm font-semibold text-zinc-900 hover:text-cyan-700"
                  prefetch={false}
                >
                  Read more →
                </Link>
              </div>
            </article>
          ))}
        </div>
      </Container>
    </main>
  );
}