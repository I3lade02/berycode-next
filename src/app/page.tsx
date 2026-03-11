import type { Metadata } from "next";

import AboutPreview from "@/components/sections/AboutPreview";
import ContactCta from "@/components/sections/ContactCta";
import FeaturedProjects from "@/components/sections/FeaturedProjects";
import Hero from "@/components/sections/Hero";
import { createMetadata } from "@/lib/seo";

export const metadata: Metadata = createMetadata({
  title: "Home",
  description:
    "Portfolio of Ondra Beránek, a full-stack web developer building modern websites, interactive applications and custom software projects.",
  path: "/",
});

export default function HomePage() {
  return (
    <main>
      <Hero />
      <FeaturedProjects />
      <AboutPreview />
      <ContactCta />
    </main>
  );
}