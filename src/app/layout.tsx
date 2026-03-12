import type { Metadata } from "next";
import "./globals.css";

import Footer from "@/components/layout/Footer";
import Navbar from "@/components/layout/Navbar";
import JsonLd from "@/components/seo/JsonLd";
import LanguageProvider from "@/components/providers/LanguageProvider";
import { siteConfig } from "@/lib/metadata";

export const metadata: Metadata = {
  metadataBase: new URL(siteConfig.url),
  title: {
    default: siteConfig.title,
    template: "%s | Ondra Beran",
  },
  description: siteConfig.description,
};

const personJsonLd = {
  "@context": "https://schema.org",
  "@type": "Person",
  name: "Ondra Beran",
  url: siteConfig.url,
  jobTitle: "Web Developer",
  brand: siteConfig.name,
};

const websiteJsonLd = {
  "@context": "https://schema.org",
  "@type": "WebSite",
  name: siteConfig.name,
  url: siteConfig.url,
  description: siteConfig.description,
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="cs" suppressHydrationWarning>
      <body className="min-h-screen bg-white text-zinc-900 antialiased">
        <LanguageProvider>
          <JsonLd data={personJsonLd} />
          <JsonLd data={websiteJsonLd} />

          <div className="flex min-h-screen flex-col">
            <Navbar />
            <div className="flex-1">{children}</div>
            <Footer />
          </div>
        </LanguageProvider>
      </body>
    </html>
  );
}