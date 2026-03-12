"use client";

import { useLanguage } from "@/components/providers/LanguageProvider";

export default function LanguageSwitcher() {
  const { locale, setLocale, t } = useLanguage();

  return (
    <div className="flex items-center rounded-full border border-zinc-200/80 bg-white/80 p-1 shadow-sm">
      <button
        type="button"
        onClick={() => setLocale("cs")}
        className={`rounded-full px-3 py-2 text-xs font-semibold transition ${
          locale === "cs"
            ? "bg-zinc-900 text-white"
            : "text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900"
        }`}
      >
        {t.language.cs}
      </button>

      <button
        type="button"
        onClick={() => setLocale("en")}
        className={`rounded-full px-3 py-2 text-xs font-semibold transition ${
          locale === "en"
            ? "bg-zinc-900 text-white"
            : "text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900"
        }`}
      >
        {t.language.en}
      </button>
    </div>
  );
}