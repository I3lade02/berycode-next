import AboutPreview from "@/components/sections/AboutPreview";
import ContactCta from "@/components/sections/ContactCta";
import FeaturedProject from "@/components/sections/FeaturedProjects";
import Hero from "@/components/sections/Hero";

export default function HomePage() {
  return (
    <main>
      <Hero />
      <FeaturedProject />
      <AboutPreview />
      <ContactCta />
    </main>
  );
}