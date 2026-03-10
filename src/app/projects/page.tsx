import Link from "next/link";

import Container from "@/components/layout/Container";
import SectionHeading from "@/components/ui/SectionHeading";
import { projects } from "@/content/projects";
import { createMetadata } from "@/lib/seo";

export const metadata = createMetadata({
    title: "Projects",
    description: "Explore web development, interactive and software projects built by Ondra Beránek.",
    path: "/projects",
});

export default function ProjectsPage() {
  return (
    <main className="py-20">
      <Container className="space-y-10">
        <SectionHeading
          eyebrow="Projects"
          title="Selected projects"
          description="A broader look at software, tools and experiments built across web development, interactivity and practical problem-solving."
        />

        <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
          {projects.map((project) => (
            <article
              key={project.slug}
              className="rounded-2xl border border-zinc-200 p-6 shadow-sm"
            >
              <div className="space-y-4">
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
                      className="rounded-full bg-zinc-100 px-3 py-1 text-xs font-medium text-zinc-700"
                    >
                      {item}
                    </li>
                  ))}
                </ul>

                <Link
                  href={`/projects/${project.slug}`}
                  className="inline-flex text-sm font-semibold text-zinc-900 hover:text-zinc-700"
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