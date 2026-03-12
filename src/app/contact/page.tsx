import Container from "@/components/layout/Container";
import ContactForm from "@/components/sections/ContactForm";
import SectionHeading from "@/components/ui/SectionHeading";
import { createMetadata } from "@/lib/seo";

export const metadata = createMetadata({
  title: "Contact",
  description:
    "Get in touch with Ondra Beránek about web development, collaboration or project opportunities.",
  path: "/contact",
});

export default function ContactPage() {
  return (
    <main className="py-20">
      <Container className="space-y-12">
        <SectionHeading
          eyebrow="Contact"
          title="Get in touch"
          description="If you want to discuss a project, collaboration or interesting idea, hit me up. This site is open to good tech talk."
        />

        <div className="grid gap-8 lg:grid-cols-[1fr_0.85fr]">
          <ContactForm />

          <div className="glass-card soft-shadow rounded-[1.75rem] p-8">
            <p className="text-xs font-semibold tracking-[0.2em] text-zinc-500 uppercase dark:text-zinc-400">
              Collaboration
            </p>

            <h2 className="mt-3 text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">
              What we can talk about
            </h2>

            <ul className="mt-6 space-y-4 text-zinc-600 dark:text-zinc-400">
              <li className="rounded-2xl border border-white/70 bg-white/80 p-4 dark:border-zinc-800/80 dark:bg-zinc-900/80">
                <strong>Web pages and portfolios</strong>
              </li>
              <li className="rounded-2xl border border-white/70 bg-white/80 p-4 dark:border-zinc-800/80 dark:bg-zinc-900/80">
                <strong>Internal tools and utilities</strong>
              </li>
              <li className="rounded-2xl border border-white/70 bg-white/80 p-4 dark:border-zinc-800/80 dark:bg-zinc-900/80">
                <strong>Redesign of existing projects</strong>
              </li>
              <li className="rounded-2xl border border-white/70 bg-white/80 p-4 dark:border-zinc-800/80 dark:bg-zinc-900/80">
                <strong>Interactive or experimental web projects</strong>
              </li>
            </ul>
          </div>
        </div>
      </Container>
    </main>
  );
}
