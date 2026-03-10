import Container from "@/components/layout/Container";
import SectionHeading from "@/components/ui/SectionHeading";
import ContactForm from "@/components/sections/ContactForm";
import { createMetadata } from "@/lib/seo";

export const metadata = createMetadata({
    title: "Contact",
    description: "Get in touch with me, I'm looking for web development, collaboration or project opportunities.",
    path: "/contact",
});

export default function ContactPage() {
  return (
    <main className="py-20">
      <Container className="space-y-10">
        <SectionHeading
          eyebrow="Contact"
          title="Get in touch"
          description="If you want to discuss a project, collaboration or an interesting idea, feel free to reach out."
        />

        <ContactForm />
      </Container>
    </main>
  );
}