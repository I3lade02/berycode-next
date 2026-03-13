import type { Locale } from "@/lib/i18n/translations";

export type LocalizedText = Record<Locale, string>;

export type Project = {
  slug: string;
  title: LocalizedText;
  description: LocalizedText;
  longDescription?: LocalizedText;
  tech: string[];
  href?: string;
  github?: string;
  image: string;
  featured?: boolean;
};