import Container from "@/components/layout/Container";
import SectionHeading from "@/components/ui/SectionHeading";

export default function AboutPreview() {
    return (
        <section className="py-20">
            <Container className="space-y-8">
                <SectionHeading
                    eyebrow="About"
                    title="Building useful things with code"
                    description="I focus on practical web and mobile apps, clean interfaces and projects that blend development, experimentation and real-world usefulness"
                />

                <div className="max-w-3xl space-y-4 text-base leading-7 text-zinc-600">
                    <p>
                        I work across frontend and backedn development, with a strong
                        interest in modern React ecosystems, TypeScript, Python and custom
                        workflows that solve real problems
                    </p>

                    <p>
                        My portfolio includes interactive tools, self-hosted platforms,
                        3D-based experiments and utility-driven applications that reflect
                        both technical curiosity and product thinking
                    </p>
                </div>
            </Container>
        </section>
    );
}