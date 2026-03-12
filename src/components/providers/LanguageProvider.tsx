"use client";

import {
  createContext,
  useContext,
  useEffect,
  useMemo,
  useState,
} from "react";
import { translations, type Locale } from "@/lib/i18n/translations";

type LanguageContextType = {
  locale: Locale;
  setLocale: (locale: Locale) => void;
  t: (typeof translations)[Locale];
};

const LanguageContext = createContext<LanguageContextType | null>(null);

type Props = {
  children: React.ReactNode;
};

export default function LanguageProvider({ children }: Props) {
  const [locale, setLocaleState] = useState<Locale>("cs");

  useEffect(() => {
    const saved = localStorage.getItem("berycode-locale") as Locale | null;
    if (saved === "cs" || saved === "en") {
      setLocaleState(saved);
      document.documentElement.lang = saved;
    } else {
      document.documentElement.lang = "cs";
    }
  }, []);

  const setLocale = (nextLocale: Locale) => {
    setLocaleState(nextLocale);
    localStorage.setItem("berycode-locale", nextLocale);
    document.documentElement.lang = nextLocale;
  };

  const value = useMemo(
    () => ({
      locale,
      setLocale,
      t: translations[locale],
    }),
    [locale],
  );

  return (
    <LanguageContext.Provider value={value}>
      {children}
    </LanguageContext.Provider>
  );
}

export function useLanguage() {
  const context = useContext(LanguageContext);

  if (!context) {
    throw new Error("useLanguage must be used within LanguageProvider");
  }

  return context;
}