import Container from "@/components/layout/Container";
import SectionHeading from "@/components/ui/SectionHeading";
import { createMetadata } from "@/lib/seo";
import Link from "next/link";

export const metadata = createMetadata({
  title: "Services",
  description:
    "Explore the services offered by Ondra Beran, including web development, portfolio websites, internal tools and interactive digital projects.",
  path: "/services",
});

const services = [
  {
    title: "Web Development",
    description:
      "Modern websites and web applications built with performance, responsiveness and maintainability in mind.",
    points: [
      "Next.js and React development",
      "Responsive frontend implementation",
      "Clean code structure and scalable setup",
    ],
  },
  {
    title: "Portfolio & Personal Websites",
    description:
      "Custom portfolio sites and personal presentation websites designed to look professional and communicate value clearly.",
    points: [
      "Personal branding websites",
      "Developer portfolios",
      "Modern landing page design",
    ],
  },
  {
    title: "Internal Tools & Dashboards",
    description:
      "Useful web-based tools for internal workflows, administration, data handling and everyday digital processes.",
    points: [
      "Admin interfaces",
      "Custom internal systems",
      "Workflow-oriented tools",
    ],
  },
  {
    title: "UI Redesign & Frontend Polish",
    description:
      "Visual and structural improvements for existing web projects with focus on clarity, usability and modern styling.",
    points: [
      "Frontend cleanup",
      "UX and layout improvements",
      "Tailwind-based redesigns",
    ],
  },
  {
    title: "Interactive & Experimental Projects",
    description:
      "Creative web experiences, interactive components and technical experiments for projects that need something beyond the ordinary.",
    points: [
      "Interactive visual elements",
      "3D / creative frontend ideas",
      "Custom project concepts",
    ],
  },
  {
    title: "Consultation & Technical Help",
    description:
      "Support with project structure, frontend direction, deployment and practical development decisions.",
    points: [
      "Technical consultation",
      "Codebase structure advice",
      "Deployment and project setup help",
    ],
  },
];

export default function ServicesPage() {
  return (
    <main className="py-20">
      <Container className="space-y-12">
        <SectionHeading
          eyebrow="Services"
          title="What I can help with"
          description="I create practical digital solutions, modern websites and custom tools with focus on usability, clean implementation and real value."
        />

        <div className="grid gap-8 md:grid-cols-2 xl:grid-cols-3">
          {services.map((service) => (
            <article
              key={service.title}
              className="glass-card soft-shadow rounded-[1.75rem] p-6 hover:-translate-y-0.75"
            >
              <div className="space-y-4">
                <div>
                  <h2 className="text-xl font-semibold text-zinc-900 dark:text-zinc-100">
                    {service.title}
                  </h2>
                  <p className="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">
                    {service.description}
                  </p>
                </div>

                <ul className="space-y-2 text-sm text-zinc-600 dark:text-zinc-400">
                  {service.points.map((point) => (
                    <li key={point} className="flex items-start gap-2">
                      <span className="mt-2 h-2 w-2 rounded-full bg-cyan-400" />
                      <span>{point}</span>
                    </li>
                  ))}
                </ul>
              </div>
            </article>
          ))}
        </div>

        <div className="glass-card soft-shadow rounded-4xl p-8">
          <div className="max-w-3xl space-y-4">
            <p className="text-xs font-semibold tracking-[0.2em] text-zinc-500 uppercase dark:text-zinc-400">
              Collaboration
            </p>

            <h2 className="text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">
              Need something custom?
            </h2>

            <p className="text-base leading-7 text-zinc-600 dark:text-zinc-400">
              If you have a project that does not fit neatly into one category,
              that is completely fine. I am open to custom solutions, practical
              experiments and collaboration on ideas that need a clean and
              modern technical approach.
            </p>

            <Link
              href="/contact"
              className="inline-flex rounded-xl border border-zinc-900 bg-zinc-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-zinc-800"
              prefetch={false}
            >
              Get in touch
            </Link>
          </div>
        </div>
      </Container>
    </main>
  );
}
