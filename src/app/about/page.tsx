import Container from "@/components/layout/Container";
import SectionHeading from "@/components/ui/SectionHeading";
import { createMetadata } from "@/lib/seo";

export const metadata = createMetadata({
    title: "About",
    description: "Learn more about Ondra Beran, his development background, technologies and project focus.",
    path: "/about",
});

export default function AboutPage() {
  return (
    <main className="py-20">
      <Container className="space-y-10">
        <SectionHeading
          eyebrow="About"
          title="About me"
          description="I build modern web applications, interactive tools and practical digital experiences with a focus on usability and clean implementation."
        />

        <div className="max-w-3xl space-y-5 text-base leading-7 text-zinc-600">
          <p>
            I’m Ondra Beran, a developer focused on web technologies, creative
            interfaces and software projects that have a practical purpose.
          </p>

          <p>
            My work often combines frontend development with backend logic,
            custom tooling and experimentation with technologies like Next.js,
            React, TypeScript, Python and Three.js.
          </p>

          <p>
            I enjoy creating projects that are both technically interesting and
            genuinely useful, whether that means developer tools, interactive
            visual experiences or self-hosted systems.
          </p>
        </div>
      </Container>
    </main>
  );
}