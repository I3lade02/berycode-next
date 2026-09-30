import type { Metadata } from "next";

import SupportPageClient from "@/components/support/SupportPageClient";
import { createMetadata } from "@/lib/seo";

export const metadata: Metadata = createMetadata({
  title: "Podpora | Support",
  description:
    "Nahlaste problém nebo požádejte o změnu ve svém projektu. Report a problem or request a change for your project.",
  path: "/support",
});

export default function SupportPage() {
  return <SupportPageClient />;
}
