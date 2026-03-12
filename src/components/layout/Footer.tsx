"use client";

import Container from "./Container";
import { useLanguage } from "@/components/providers/LanguageProvider";

export default function Footer() {
  const { t } = useLanguage();

  return (
    <footer className="border-t border-zinc-200">
      <Container className="flex flex-col gap-3 py-8 text-sm text-zinc-500 sm:flex-row sm:items-center sm:justify-between">
        <p>
          © {new Date().getFullYear()} BeryCode. {t.footer.rights}
        </p>
        <p>{t.footer.builtWith}</p>
      </Container>
    </footer>
  );
}