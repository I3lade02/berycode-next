import Container from "@/components/layout/Container";
import SectionHeading from "@/components/ui/SectionHeading";

export default function AboutPreview() {
  return (
    <section className="relative py-24">
      <Container>
        <div className="grid gap-10 lg:grid-cols-[0.95fr_1.05fr] lg:items-start">
          <SectionHeading
            eyebrow="About"
            title="Building useful things with code"
            description="I focus on web applications, clean interfaces, and projects that combine development, experimentation, and practical use."
          />

          <div className="glass-card soft-shadow rounded-[1.75rem] p-8">
            <div className="space-y-4 text-base leading-7 text-zinc-600">
              <p>
                I work across frontend and backend and enjoy the modern React
                ecosystem, TypeScript, Python, and creating workflows that solve
                real-world problems.
              </p>

              <p>
                My portfolio includes interactive tools, self-hosted platforms, 
                3D experiments, and utilities focused on practical use and pure 
                user experience.
              </p>

              <p>
                My goal is not just to "code something", but to build a digital
                product that is technically solid, easy to use and has
                its own character.
              </p>
            </div>
          </div>
        </div>
      </Container>
    </section>
  );
}