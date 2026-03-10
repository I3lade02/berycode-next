import Button from "../ui/Button";
import Container from "../layout/Container";

export default function Hero() {
    return (
        <section className="py-24 sm:py-32">
            <Container>
                <div className="max-w-4xl space-y-8">
                    <div className="space-y-4">
                        <p className="text-sm font-semibold uppercase tracking-[0.2em] text-zinc-500">
                            BeryCode
                        </p>

                        <h1 className="text-4xl font-bold tracking-tight text-zinc-900 sm:text-6xl">
                            Ondra Beránek
                        </h1>

                        <p className="max-w-2xl text-lg leading-8 text-zinc-600 sm:text-xl">
                            Frontend developer building modern web and mobile apps, interactive tools,
                            and experimental projects with Next.js, TypeScript, Python, and
                            creative frontend technologies.
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-4">
                        <Button href="/project">View Projects</Button>
                        <Button
                            href="/contact"
                            className="bg-zinc-100 text-zinc-900 hover:bg-zinc-200"
                        >
                            Contact me
                        </Button>
                    </div>
                </div>
            </Container>
        </section>
    );
}