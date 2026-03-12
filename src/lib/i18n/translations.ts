export type Locale = "cs" | "en";

export const translations = {
  cs: {
    nav: {
      home: "Domů",
      projects: "Projekty",
      services: "Služby",
      about: "O mně",
      contact: "Kontakt",
    },
    hero: {
      badge: "BeryCode Portfolio",
      title1: "Moderní weby,",
      title2: "interaktivní aplikace",
      title3: "a nápady převedené do reality.",
      description:
        "Jsem Ondra Beránek, full-stack web developer zaměřený na tvorbu moderních webových aplikací, developer nástrojů, interaktivních projektů a praktických řešení pomocí Next.js, Reactu, TypeScriptu a Pythonu.",
      viewProjects: "Zobrazit projekty",
      contactMe: "Kontaktovat mě",
      activeStack: "Aktivní stack",
      activeStackDesc: "technologie, se kterými rád stavím",
      focus: "Zaměření",
      focusText:
        "Webové aplikace, developer nástroje, experimenty a praktické digitální produkty.",
      live: "Live",
    },
    featuredProjects: {
      eyebrow: "Projekty",
      title: "Vybraná práce",
      description:
        "Výběr projektů zaměřených na webové aplikace, developer nástroje, interaktivní rozhraní a software řešící konkrétní potřeby.",
      viewProject: "Zobrazit projekt →",
    },
    aboutPreview: {
      eyebrow: "O mně",
      title: "Tvořím užitečné věci pomocí kódu",
      description:
        "Zaměřuji se na webové aplikace, čisté rozhraní a projekty, které propojují vývoj, experiment a praktické využití.",
      p1: "Pracuji napříč frontendem i backendem a baví mě moderní React ekosystém, TypeScript, Python a tvorba workflow, která řeší reálné problémy.",
      p2: "V mém portfoliu se objevují interaktivní nástroje, self-hosted platformy, 3D experimenty i utility orientované na praktické použití a čistou uživatelskou zkušenost.",
      p3: "Mým cílem není jen něco nakódovat, ale postavit digitální produkt, který je technicky solidní, dobře použitelný a má vlastní charakter.",
    },
    services: {
      eyebrow: "Služby",
      title: "Co mohu nabídnout",
      description:
        "Pomáhám s tvorbou moderních webů, webových aplikací, redesignem i technickým řešením digitálních projektů.",
      items: [
        {
          title: "Webové stránky",
          description:
            "Firemní weby, portfolia a prezentační stránky s důrazem na výkon, čistý design a dobrou strukturu.",
        },
        {
          title: "Webové aplikace",
          description:
            "Tvorba interaktivních a funkčních aplikací v moderním stacku jako Next.js, React a TypeScript.",
        },
        {
          title: "Redesign a úpravy",
          description:
            "Vylepšení existujících projektů po vizuální i technické stránce, od UX až po čistotu kódu.",
        },
        {
          title: "Technická konzultace",
          description:
            "Pomoc s architekturou, nasazením, optimalizací a výběrem vhodného technologického řešení.",
        },
      ],
    },
    aboutPage: {
      eyebrow: "O mně",
      title: "O mně",
      description:
        "Vyvíjím moderní webové aplikace, interaktivní nástroje a praktická digitální řešení se zaměřením na použitelnost, výkon a čistou implementaci.",
    },
    contactPage: {
      eyebrow: "Kontakt",
      title: "Ozvi se",
      description:
        "Pokud chceš probrat projekt, spolupráci nebo zajímavý nápad, ozvi se.",
    },
    contactForm: {
      name: "Jméno",
      email: "Email",
      message: "Zpráva",
      namePlaceholder: "Tvoje jméno",
      emailPlaceholder: "tvuj@email.cz",
      messagePlaceholder: "Napiš mi něco o projektu nebo nápadu...",
      send: "Odeslat zprávu",
      sending: "Odesílání...",
      success: "Zpráva byla úspěšně odeslána.",
      configError: "Chybí EmailJS konfigurace v .env.local.",
      submitError: "Nepodařilo se odeslat zprávu. Zkus to prosím znovu.",
    },
    footer: {
      rights: "Všechna práva vyhrazena.",
      builtWith: "Postaveno pomocí Next.js, TypeScriptu a Tailwind CSS.",
    },
    notFound: {
      title: "Stránka nenalezena",
      description:
        "Tahle stránka se ztratila v digitální mlze. Pojďme tě vrátit někam užitečnějším.",
      backHome: "Zpět domů",
    },
    language: {
      cs: "CZ",
      en: "EN",
    },
  },
  en: {
    nav: {
      home: "Home",
      projects: "Projects",
      services: "Services",
      about: "About",
      contact: "Contact",
    },
    hero: {
      badge: "BeryCode Portfolio",
      title1: "Modern websites,",
      title2: "interactive applications",
      title3: "and ideas turned into reality.",
      description:
        "I’m Ondra Beránek, a full-stack web developer focused on building modern web applications, developer tools, interactive projects and practical solutions using Next.js, React, TypeScript and Python.",
      viewProjects: "View Projects",
      contactMe: "Contact Me",
      activeStack: "Active stack",
      activeStackDesc: "technologies I enjoy building with",
      focus: "Focus",
      focusText:
        "Web apps, dev tools, experiments and practical digital products.",
      live: "Live",
    },
    featuredProjects: {
      eyebrow: "Projects",
      title: "Featured work",
      description:
        "A selection of projects focused on web applications, developer tools, interactive interfaces and software built to solve specific needs.",
      viewProject: "View project →",
    },
    aboutPreview: {
      eyebrow: "About",
      title: "Building useful things with code",
      description:
        "I focus on web applications, clean interfaces and projects that combine development, experimentation and real-world usefulness.",
      p1: "I work across both frontend and backend development and enjoy modern React ecosystems, TypeScript, Python and building workflows that solve real problems.",
      p2: "My portfolio includes interactive tools, self-hosted platforms, 3D experiments and utility-driven applications with a strong focus on practical use and clean user experience.",
      p3: "My goal is not just to code something, but to build digital products that are technically solid, usable and have their own character.",
    },
    services: {
      eyebrow: "Services",
      title: "What I can offer",
      description:
        "I help with modern websites, web applications, redesigns and technical solutions for digital projects.",
      items: [
        {
          title: "Websites",
          description:
            "Business websites, portfolios and presentation sites focused on performance, clean design and solid structure.",
        },
        {
          title: "Web Applications",
          description:
            "Building interactive and functional applications using modern technologies such as Next.js, React and TypeScript.",
        },
        {
          title: "Redesign and Improvements",
          description:
            "Improving existing projects visually and technically, from UX to cleaner code structure.",
        },
        {
          title: "Technical Consulting",
          description:
            "Help with architecture, deployment, optimization and choosing the right technical solution.",
        },
      ],
    },
    aboutPage: {
      eyebrow: "About",
      title: "About me",
      description:
        "I build modern web applications, interactive tools and practical digital solutions with a focus on usability, performance and clean implementation.",
    },
    contactPage: {
      eyebrow: "Contact",
      title: "Get in touch",
      description:
        "If you want to discuss a project, collaboration or an interesting idea, feel free to reach out.",
    },
    contactForm: {
      name: "Name",
      email: "Email",
      message: "Message",
      namePlaceholder: "Your name",
      emailPlaceholder: "your@email.com",
      messagePlaceholder: "Tell me a bit about your project or idea...",
      send: "Send message",
      sending: "Sending...",
      success: "Message sent successfully.",
      configError: "EmailJS configuration is missing in .env.local.",
      submitError: "Failed to send the message. Please try again.",
    },
    footer: {
      rights: "All rights reserved.",
      builtWith: "Built with Next.js, TypeScript and Tailwind CSS.",
    },
    notFound: {
      title: "Page not found",
      description:
        "This page wandered off into the digital fog. Let’s get you back somewhere useful.",
      backHome: "Back home",
    },
    language: {
      cs: "CZ",
      en: "EN",
    },
  },
} as const;