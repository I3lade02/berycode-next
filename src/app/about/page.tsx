import Container from "@/components/layout/Container";
import SectionHeading from "@/components/ui/SectionHeading";
import { createMetadata } from "@/lib/seo";

export const metadata = createMetadata({
  title: "About",
  description:
    "Learn more about Ondra Beran, his development background, technologies and project focus.",
  path: "/about",
});

export default function AboutPage() {
  return (
    <main className="py-20">
      <Container className="space-y-12">
        <SectionHeading
          eyebrow="About"
          title="About me"
          description="I develop modern apps, interactive tools and practical digital solutions with a focus on usability, performance and clean implementation"
        />

        <div className="grid gap-8 lg:grid-cols-[1.15fr_0.85fr]">
          <div className="glass-card soft-shadow rounded-[1.75rem] p-8">
            <div className="space-y-5 text-base leading-7 text-zinc-600 dark:text-zinc-400">
              <p>
                I&apos;m Ondra Beránek, a developer focused on web technologies,
                creative interfaces, and software projects that make practical
                sense.
              </p>

              <p>
                My work usually connects frontend with backend logic, own
                tooling and experiments with technologies such as Next.js,
                React, TypeScript, Python or Three.js
              </p>

              <p>
                I enjoy creating projects that are technically interesting and
                at the same time truly usable, whether it&apos;s developer
                utilities, interactive visualizations, or self-hosted systems.
              </p>
            </div>
          </div>

          <div className="glass-card soft-shadow rounded-[1.75rem] p-8">
            <p className="text-xs font-semibold tracking-[0.2em] text-zinc-500 uppercase dark:text-zinc-400">
              Focus areas
            </p>

            <ul className="mt-5 space-y-4 text-zinc-700 dark:text-zinc-300">
              <li className="rounded-2xl border border-white/70 bg-white/80 p-4 dark:border-zinc-800/80 dark:bg-zinc-900/80">
                <strong>Modern web apps</strong>
              </li>
              <li className="rounded-2xl border border-white/70 bg-white/80 p-4 dark:border-zinc-800/80 dark:bg-zinc-900/80">
                <strong>Interactive interfaces</strong>
              </li>
              <li className="rounded-2xl border border-white/70 bg-white/80 p-4 dark:border-zinc-800/80 dark:bg-zinc-900/80">
                <strong>Developer tools & utilities</strong>
              </li>
              <li className="rounded-2xl border border-white/70 bg-white/80 p-4 dark:border-zinc-800/80 dark:bg-zinc-900/80">
                <strong>Self-hosted and experimental projects</strong>
              </li>
            </ul>
          </div>
        </div>
      </Container>
    </main>
  );
}
