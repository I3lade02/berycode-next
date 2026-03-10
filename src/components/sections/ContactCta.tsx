import Container from "@/components/layout/Container";
import Button from "@/components/ui/Button";

export default function ContactCta() {
    return (
        <section className="py-20">
            <Container>
                <div className="rounded-3xl bg-zinc-900 px-8 py-12 text-white sm:px-12">
                    <div className="max-w-2xl space-y-5">
                        <p className="text-sm font-semibold uppercase tracking-[0.2em] text-zinc-400">
                            Contact
                        </p>

                        <h2 className="text-3xl font-bold tracking-tight sm:text-4xl">
                            Want to build something together?
                        </h2>

                        <p className="text-base leading-7 text-zinc-300 sm:text-lg">
                            Whether it's a portfolio, custom tool, interactive project or a
                            more ambitious web application, I'm open to interesting work and 
                            collaborations
                        </p>

                        <Button
                            href="/contact"
                            className="bg-white text-zinc-900 hover:bg-zinc-200"
                        >
                            Get in touch
                        </Button>
                    </div>
                </div>
            </Container>
        </section>
    );
}