import Container from "@/components/layout/Container";
import Button from "@/components/ui/Button";

export default function ContactCta() {
  return (
    <section className="py-24">
      <Container>
        <div className="relative overflow-hidden rounded-4xl border border-zinc-800 bg-zinc-950 px-8 py-12 text-white shadow-2xl sm:px-12">
          <div className="absolute -top-10 -left-10 h-40 w-40 rounded-full bg-cyan-400/20 blur-3xl" />
          <div className="absolute right-0 bottom-0 h-48 w-48 rounded-full bg-violet-500/20 blur-3xl" />

          <div className="relative max-w-2xl space-y-5">
            <div className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2">
              <span className="h-2 w-2 rounded-full bg-cyan-400" />
              <p className="text-xs font-semibold tracking-[0.2em] text-zinc-300 uppercase">
                Contact
              </p>
            </div>

            <h2 className="text-3xl font-bold tracking-tight sm:text-4xl">
              Want to build something together?
            </h2>

            <p className="text-base leading-7 text-zinc-300 sm:text-lg">
              Whether it&apos;s a portfolio, an internal tool, an interactive
              project, or a more ambitious web application, I&apos;m happy to
              see interesting collaborations and new ideas.
            </p>

            <Button
              href="/contact"
              className="border-white bg-white text-zinc-900 hover:bg-zinc-200"
            >
              Get in touch
            </Button>
          </div>
        </div>
      </Container>
    </section>
  );
}
