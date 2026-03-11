import Container from "@/components/layout/Container";
import Button from "@/components/ui/Button";

const badges = [
  "Next.js",
  "TypeScript",
  "React",
  "Python",
  "Full-stack",
  "UI/UX",
];

export default function Hero() {
  return (
    <section className="relative overflow-hidden py-24 sm:py-32">
      <div className="bg-grid absolute inset-0 opacity-40" />

      <div className="hero-ring hero-ring-cyan absolute top-16 -left-15 h-64 w-64" />
      <div className="hero-ring hero-ring-violet absolute top-24 -right-10 h-72 w-72" />
      <div className="hero-ring hero-ring-cyan absolute bottom-0 left-1/3 h-56 w-56" />

      <Container className="relative">
        <div className="grid items-center gap-14 lg:grid-cols-[1.2fr_0.8fr]">
          <div className="space-y-8">
            <div className="inline-flex items-center gap-2 rounded-full border border-cyan-200 bg-white/80 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-zinc-600 shadow-sm backdrop-blur">
              <span className="h-2 w-2 rounded-full bg-cyan-400" />
              BeryCode Portfolio
            </div>

            <div className="space-y-5">
              <h1 className="max-w-4xl text-5xl font-bold tracking-tight text-zinc-900 sm:text-6xl xl:text-7xl">
                Modern websites,
                <br />
                <span className="text-gradient">interactive apps</span>
                <br />and ideas turned into reality.
              </h1>

              <p className="max-w-3xl text-lg leading-8 text-zinc-600 sm:text-xl">
                I'm Ondra Beránek, full-stack web developer focused on creation
                of modern web apps, developer tools, interactive 
                and practical projects build with Next.js, React,
                TypeScript and Python.
              </p>
            </div>

            <div className="flex flex-wrap gap-3">
              {badges.map((badge) => (
                <span
                  key={badge}
                  className="tech-pill rounded-full px-4 py-2 text-sm font-medium text-zinc-700"
                >
                  {badge}
                </span>
              ))}
            </div>

            <div className="flex flex-wrap gap-4">
              <Button href="/projects">View Projects</Button>
              <Button
                href="/contact"
                className="border-zinc-200 bg-white text-zinc-900 shadow-sm hover:bg-zinc-100"
              >
                Contact Me
              </Button>
            </div>

            <div className="flex flex-wrap gap-8 pt-4">
              <div>
                <p className="text-2xl font-bold tracking-tight text-zinc-900">
                  3+
                </p>
                <p className="text-sm text-zinc-500">Featured projects</p>
              </div>
              <div>
                <p className="text-2xl font-bold tracking-tight text-zinc-900">
                  Full-stack
                </p>
                <p className="text-sm text-zinc-500">frontend + backend focus</p>
              </div>
              <div>
                <p className="text-2xl font-bold tracking-tight text-zinc-900">
                  Custom
                </p>
                <p className="text-sm text-zinc-500">Tools & web experiences</p>
              </div>
            </div>
          </div>

          <div className="relative hidden lg:block xl:pr-10">
            <div className="glass-card soft-shadow relative overflow-hidden rounded-4xl p-6">
              <div className="mb-6 flex items-center justify-between">
                <div>
                  <p className="text-sm font-semibold text-zinc-900">
                    Active stack
                  </p>
                  <p className="text-sm text-zinc-500">
                    Technologies I enjoy building with
                  </p>
                </div>

                <div className="rounded-full border border-cyan-200 bg-cyan-50 px-3 py-1 text-xs font-semibold text-cyan-700">
                  Live
                </div>
              </div>

              <div className="space-y-4">
                {[
                  ["Next.js", "SEO-friendly web architecture"],
                  ["TypeScript", "scalable and safer development"],
                  ["Python", "automation and backend logic"],
                  ["Tailwind CSS", "clean and fast UI building"],
                ].map(([title, text]) => (
                  <div
                    key={title}
                    className="rounded-2xl border border-white/70 bg-white/75 p-4 shadow-sm"
                  >
                    <p className="font-semibold text-zinc-900">{title}</p>
                    <p className="mt-1 text-sm leading-6 text-zinc-500">
                      {text}
                    </p>
                  </div>
                ))}
              </div>

              <div className="mt-6 rounded-2xl bg-zinc-900 p-5 text-white">
                <p className="text-sm font-semibold uppercase tracking-[0.2em] text-zinc-400">
                  Focus
                </p>
                <p className="mt-2 text-lg font-semibold">
                  Web apps, dev tools, experiments and practical digital
                  products.
                </p>
              </div>
            </div>
          </div>
        </div>
      </Container>
    </section>
  );
}