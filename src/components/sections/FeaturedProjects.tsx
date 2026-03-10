import Link from "next/link";
import Container from "../layout/Container";
import SectionHeading from "../ui/SectionHeading";
import { getFeaturedProjects } from "@/content/projects";

export default function FeaturedProject() {
    const FeaturedProjects = getFeaturedProjects();

    return (
        <section className="py-20">
            <Container className="space-y-10">
                <SectionHeading
                    eyebrow="Projects"
                    title="Featured Work"
                    description="A selection of projects focused on web applications, developer tools, interactive experiences and custom software ideas."
                />

                <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                    {FeaturedProjects.map((project) => (
                        <article
                            key={project.slug}
                            className="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-md"
                        >
                            <div className="space-y-4">
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
                                    View Project
                                </Link>
                            </div>
                        </article>
                    ))}
                </div>
            </Container>
        </section>
    );
}