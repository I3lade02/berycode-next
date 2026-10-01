export type Locale = "cs" | "en";

export const translations = {
  cs: {
    nav: {
      home: "Domů",
      projects: "Projekty",
      services: "Služby",
      about: "O mně",
      contact: "Kontakt",
      support: "Podpora",
    },

    language: {
      cs: "CZ",
      en: "EN",
    },

    hero: {
      badge: "BeryCode Portfolio",
      title1: "Moderní weby,",
      title2: "interaktivní aplikace",
      title3: "a nápady převedené do reality.",
      description:
        "Jsem Ondra Beránek, full-stack web developer zaměřený na tvorbu moderních webových aplikací, developer nástrojů, interaktivních projektů a praktických řešení pomocí Next.js, Reactu, TypeScriptu a Pythonu.",
      viewProjects: "Zobrazit projekty",
      contactMe: "Kontaktujte mě",
      activeStack: "Aktivní stack",
      activeStackDesc: "technologie, se kterými rád stavím",
      focus: "Zaměření",
      focusText:
        "Webové aplikace, developer nástroje, experimenty a praktické digitální produkty.",
      live: "Live",
      stackCards: [
        {
          title: "Next.js",
          description: "SEO-friendly webová architektura",
        },
        {
          title: "TypeScript",
          description: "škálovatelnější a bezpečnější vývoj",
        },
        {
          title: "Python",
          description: "automatizace a backend logika",
        },
        {
          title: "Tailwind CSS",
          description: "čistá a rychlá tvorba UI",
        },
      ],
    },

    featuredProjects: {
      eyebrow: "Projekty",
      title: "Vybraná práce",
      description:
        "Výběr projektů zaměřených na webové aplikace, developer nástroje, interaktivní rozhraní a software řešící konkrétní potřeby.",
      viewProject: "Zobrazit projekt →",
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

    aboutPreview: {
      eyebrow: "O mně",
      title: "Tvořím užitečné věci pomocí kódu",
      description:
        "Zaměřuji se na webové aplikace, čisté rozhraní a projekty, které propojují vývoj, experiment a praktické využití.",
      p1: "Pracuji napříč frontendem i backendem a baví mě moderní React ekosystém, TypeScript, Python a tvorba workflow, která řeší reálné problémy.",
      p2: "V mém portfoliu se objevují interaktivní nástroje, self-hosted platformy, 3D experimenty i utility orientované na praktické použití a čistou uživatelskou zkušenost.",
      p3: "Mým cílem není jen něco nakódovat, ale postavit digitální produkt, který je technicky solidní, dobře použitelný a má vlastní charakter.",
    },

    cta: {
      badge: "Kontakt",
      title: "Chceš spolu něco postavit?",
      description:
        "Ať už jde o portfolio, interní nástroj, interaktivní projekt nebo ambicióznější webovou aplikaci, rád se podívám na zajímavou spolupráci i nové nápady.",
      button: "Ozvat se",
    },

    projectsPage: {
      eyebrow: "Projekty",
      title: "Vybrané projekty",
      description:
        "Širší pohled na software, webové aplikace, experimenty a nástroje, které jsem navrhoval a stavěl s důrazem na funkčnost i čistý vizuál.",
      readMore: "Zjistit více →",
    },

    projectDetail: {
      badge: "Projekt",
      overview: "Přehled",
      overviewText:
        "Tento projekt odráží můj zájem o tvorbu praktického a vizuálně silného softwaru pomocí moderních technologií, čisté architektury a důrazu na použitelnost.",
      focusTitle: "Na co jsem se soustředil",
      focusPoints: [
        "čitelné a přirozené uživatelské rozhraní",
        "udržitelná struktura kódu",
        "responzivní frontend a moderní tooling",
        "funkčnost s prostorem pro další rozšiřování",
      ],
      techStack: "Použité technologie",
      github: "Zobrazit GitHub",
      visit: "Navštívit projekt",
    },

    aboutPage: {
      eyebrow: "O mně",
      title: "O mně",
      description:
        "Vyvíjím moderní webové aplikace, interaktivní nástroje a praktická digitální řešení se zaměřením na použitelnost, výkon a čistou implementaci.",
      p1: "Jsem Ondra Beránek, developer zaměřený na webové technologie, kreativní rozhraní a software projekty, které mají praktický smysl.",
      p2: "Moje práce často propojuje frontend s backend logikou, vlastním toolingem a experimenty s technologiemi jako Next.js, React, TypeScript, Python nebo Three.js.",
      p3: "Baví mě tvořit projekty, které jsou technicky zajímavé a zároveň skutečně použitelné, ať už jde o developer utility, interaktivní vizualizace nebo self-hosted systémy.",
      focusAreas: "Oblasti zaměření",
      areas: [
        "moderní webové aplikace",
        "interaktivní rozhraní",
        "developer nástroje a utility",
        "self-hosted a experimentální projekty",
      ],
    },

    contactPage: {
      eyebrow: "Kontakt",
      title: "Ozvi se",
      description:
        "Pokud chceš probrat projekt, spolupráci nebo zajímavý nápad, ozvi se. Tahle stránka je otevřená dobrým digitálním konverzacím.",
      collaboration: "Spolupráce",
      talkAbout: "Co můžeme probrat",
      items: [
        "webové stránky a portfolia",
        "interní nástroje a utility",
        "redesign existujících projektů",
        "interaktivní nebo experimentální webové aplikace",
      ],
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

    supportPage: {
      eyebrow: "Podpora",
      title: "Nahlaste problém nebo požádejte o změnu",
      description:
        "Něco na vašem projektu nefunguje, jak má, nebo potřebujete úpravu? Popište to ve formuláři – více chyb nebo změn můžete poslat najednou v jednom tiketu. Odpověď přijde na e-mail, který uvedete.",
      howItWorks: "Jak to funguje",
      steps: [
        "Vyplňte kontakt a vyberte svůj projekt.",
        "Každou chybu nebo změnu přidejte jako samostatný požadavek – až 10 v jednom tiketu.",
        "Po odeslání dostanete číslo tiketu. Odpověď přijde na zadaný e-mail.",
      ],
      projectHintTitle: "Nevidíte svůj projekt?",
      projectHint:
        "V seznamu jsou všechny projekty, které mají podporu. Pokud tam ten váš chybí, napište mi přes kontaktní formulář.",
      privacyNote:
        "Neposílejte prosím hesla, přístupové údaje ani jiné citlivé informace.",
    },

    supportForm: {
      requiredNote: "Všechna pole jsou povinná, pokud není uvedeno jinak.",
      name: "Jméno",
      namePlaceholder: "Jan Novák",
      email: "E-mail",
      emailPlaceholder: "jan@firma.cz",
      emailHint: "Na tuto adresu přijde odpověď.",
      project: "Projekt",
      projectPlaceholder: "Název nebo kód projektu",
      projectHint: "Např. název webu nebo kód z odkazu, který jste dostali.",
      projectSelectPlaceholder: "Vyberte projekt",
      projectLoading: "Načítám projekty…",
      subject: "Předmět",
      subjectPlaceholder: "Stručně, o co jde",
      description: "Popis",
      descriptionPlaceholder:
        "Co se stalo, kde to nastává, jak to zopakovat a co jste očekávali…",
      descriptionCounter: "{count} / {max} znaků",
      contactSection: "Kontakt a projekt",
      issuesSection: "Co potřebujete vyřešit",
      issuesHint:
        "Každou chybu nebo změnu popište jako samostatný požadavek. Do jednoho tiketu jich můžete přidat až 10.",
      issueTitle: "Požadavek {n}",
      removeIssue: "Odebrat",
      removeIssueLabel: "Odebrat požadavek {n}",
      addIssue: "Přidat další požadavek",
      maxIssuesReached: "Do jednoho tiketu lze přidat nejvýše 10 požadavků.",
      issueAdded: "Přidán požadavek {n}.",
      issueRemoved: "Požadavek {n} byl odebrán.",
      summaryIssuePrefix: "Požadavek {n}: ",
      issueNouns: {
        one: "požadavek",
        few: "požadavky",
        many: "požadavku",
        other: "požadavků",
      },
      requestType: "Typ",
      requestTypes: {
        bug: { label: "Chyba", hint: "Něco nefunguje, jak má" },
        change_request: {
          label: "Požadavek na změnu",
          hint: "Úprava nebo nová funkce",
        },
        other: { label: "Jiné", hint: "Dotaz nebo cokoli dalšího" },
      },
      priority: "Priorita",
      priorities: {
        normal: { label: "Normální", hint: "Běžný požadavek" },
        high: { label: "Vysoká", hint: "Blokuje provoz nebo zákazníky" },
      },
      honeypot: "Tohle pole nevyplňujte",
      submit: "Odeslat požadavek",
      submitMany: "Odeslat {count} {noun}",
      submitting: "Odesílám…",
      summaryTitle: "Formulář obsahuje chyby:",
      suggestionsLabel: "Měli jste na mysli:",
      errors: {
        name: {
          required: "Vyplňte své jméno.",
          too_short: "Jméno musí mít alespoň 2 znaky.",
          too_long: "Jméno může mít nejvýše 100 znaků.",
        },
        email: {
          required: "Vyplňte e-mail.",
          invalid: "Zadejte platnou e-mailovou adresu.",
          too_long: "E-mail může mít nejvýše 254 znaků.",
        },
        project: {
          required: "Uveďte název nebo kód projektu.",
          not_selected: "Vyberte svůj projekt.",
          too_long: "Název projektu může mít nejvýše 100 znaků.",
          project_not_found:
            "Tento projekt se nepodařilo najít. Zkontrolujte název nebo kód projektu.",
          project_ambiguous:
            "Zadání odpovídá více projektům. Použijte prosím přesný kód projektu.",
        },
        subject: {
          required: "Vyplňte předmět.",
          too_short: "Předmět musí mít alespoň 5 znaků.",
          too_long: "Předmět může mít nejvýše 150 znaků.",
        },
        description: {
          required: "Popište svůj požadavek.",
          too_short: "Popis musí mít alespoň 20 znaků.",
          too_long: "Popis může mít nejvýše 5 000 znaků.",
        },
        requestType: {
          required: "Vyberte typ požadavku.",
        },
        priority: {
          required: "Vyberte prioritu.",
        },
        issues: {
          required: "Přidejte alespoň jeden požadavek.",
          too_many: "Do jednoho tiketu lze přidat nejvýše 10 požadavků.",
          invalid: "Požadavky se nepodařilo zpracovat.",
        },
        generic: "Zkontrolujte prosím tuto hodnotu.",
      },
      rootErrors: {
        network:
          "Nepodařilo se ověřit, že požadavek dorazil. Zkontrolujte připojení a zkuste to znovu – opakované odeslání nevytvoří duplicitní tiket.",
        rate_limited:
          "Odeslali jste příliš mnoho požadavků. Zkuste to prosím znovu za {minutes} min.",
        unavailable:
          "Požadavek se teď nepodařilo uložit. Zkuste to prosím za chvíli znovu, případně použijte kontaktní formulář.",
        conflict:
          "Tento požadavek už byl odeslán s jiným obsahem. Obnovte stránku a odešlete ho znovu.",
        rejected: "Požadavek se nepodařilo odeslat.",
      },
      contactLink: "Kontaktní formulář",
      successTitle: "Tiket byl přijat",
      successReference: "Číslo vašeho tiketu",
      successIssues: "Obsahuje {count} {noun}.",
      successBody:
        "Tiket je uložený. Odpověď přijde na {email}. Při další komunikaci prosím uvádějte číslo tiketu.",
      newRequest: "Vytvořit nový tiket",
    },

    footer: {
      rights: "Všechna práva vyhrazena.",
      builtWith: "Postaveno pomocí Next.js, TypeScriptu a Tailwind CSS.",
    },

    notFound: {
      code: "404",
      title: "Stránka nenalezena",
      description:
        "Tahle stránka se ztratila v digitální mlze. Pojďme tě vrátit někam užitečnějším.",
      backHome: "Zpět domů",
    },
  },

  en: {
    nav: {
      home: "Home",
      projects: "Projects",
      services: "Services",
      about: "About",
      contact: "Contact",
      support: "Support",
    },

    language: {
      cs: "CZ",
      en: "EN",
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
      stackCards: [
        {
          title: "Next.js",
          description: "SEO-friendly web architecture",
        },
        {
          title: "TypeScript",
          description: "scalable and safer development",
        },
        {
          title: "Python",
          description: "automation and backend logic",
        },
        {
          title: "Tailwind CSS",
          description: "clean and fast UI building",
        },
      ],
    },

    featuredProjects: {
      eyebrow: "Projects",
      title: "Featured work",
      description:
        "A selection of projects focused on web applications, developer tools, interactive interfaces and software built to solve specific needs.",
      viewProject: "View project →",
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

    aboutPreview: {
      eyebrow: "About",
      title: "Building useful things with code",
      description:
        "I focus on web applications, clean interfaces and projects that combine development, experimentation and real-world usefulness.",
      p1: "I work across both frontend and backend development and enjoy modern React ecosystems, TypeScript, Python and building workflows that solve real problems.",
      p2: "My portfolio includes interactive tools, self-hosted platforms, 3D experiments and utility-driven applications with a strong focus on practical use and clean user experience.",
      p3: "My goal is not just to code something, but to build digital products that are technically solid, usable and have their own character.",
    },

    cta: {
      badge: "Contact",
      title: "Want to build something together?",
      description:
        "Whether it’s a portfolio, internal tool, interactive project or a more ambitious web application, I’m open to interesting collaborations and fresh ideas.",
      button: "Get in touch",
    },

    projectsPage: {
      eyebrow: "Projects",
      title: "Selected projects",
      description:
        "A broader look at software, web applications, experiments and tools I designed and built with a focus on functionality and clean visual execution.",
      readMore: "Read more →",
    },

    projectDetail: {
      badge: "Project",
      overview: "Overview",
      overviewText:
        "This project reflects my interest in building practical and visually strong software using modern technologies, clean architecture and a strong focus on usability.",
      focusTitle: "What I focused on",
      focusPoints: [
        "clear and natural user interface",
        "maintainable code structure",
        "responsive frontend and modern tooling",
        "functionality with room for future growth",
      ],
      techStack: "Tech stack",
      github: "View GitHub",
      visit: "Visit Project",
    },

    aboutPage: {
      eyebrow: "About",
      title: "About me",
      description:
        "I build modern web applications, interactive tools and practical digital solutions with a focus on usability, performance and clean implementation.",
      p1: "I’m Ondra Beránek, a developer focused on web technologies, creative interfaces and software projects with practical value.",
      p2: "My work often combines frontend development with backend logic, custom tooling and experiments using technologies such as Next.js, React, TypeScript, Python and Three.js.",
      p3: "I enjoy creating projects that are technically interesting and genuinely useful, whether that means developer utilities, interactive visualizations or self-hosted systems.",
      focusAreas: "Focus areas",
      areas: [
        "modern web applications",
        "interactive interfaces",
        "developer tools & utilities",
        "self-hosted and experimental projects",
      ],
    },

    contactPage: {
      eyebrow: "Contact",
      title: "Get in touch",
      description:
        "If you want to discuss a project, collaboration or an interesting idea, feel free to reach out. This page is open to good digital conversations.",
      collaboration: "Collaboration",
      talkAbout: "What we can talk about",
      items: [
        "websites and portfolios",
        "internal tools and utilities",
        "redesign of existing projects",
        "interactive or experimental web applications",
      ],
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

    supportPage: {
      eyebrow: "Support",
      title: "Report a problem or request a change",
      description:
        "Something on your project not working as it should, or need a change? Describe it in the form – you can send several bugs or changes at once in a single ticket. The reply will go to the email address you enter.",
      howItWorks: "How it works",
      steps: [
        "Enter your contact details and choose your project.",
        "Add each bug or change as a separate issue – up to 10 in one ticket.",
        "After sending you get a ticket number. The reply goes to the email address you provide.",
      ],
      projectHintTitle: "Can't find your project?",
      projectHint:
        "The list shows every project with support. If yours is missing, write to me through the contact form.",
      privacyNote:
        "Please don't send passwords, access credentials or other sensitive information.",
    },

    supportForm: {
      requiredNote: "All fields are required unless stated otherwise.",
      name: "Name",
      namePlaceholder: "Jane Smith",
      email: "Email",
      emailPlaceholder: "jane@company.com",
      emailHint: "Replies will be sent to this address.",
      project: "Project",
      projectPlaceholder: "Project name or code",
      projectHint:
        "For example the website name or the code from the link you received.",
      projectSelectPlaceholder: "Choose your project",
      projectLoading: "Loading projects…",
      subject: "Subject",
      subjectPlaceholder: "A short summary",
      description: "Description",
      descriptionPlaceholder:
        "What happened, where it happens, how to reproduce it and what you expected…",
      descriptionCounter: "{count} / {max} characters",
      contactSection: "Contact and project",
      issuesSection: "What do you need help with?",
      issuesHint:
        "Describe each bug or change as a separate issue. You can add up to 10 issues to one ticket.",
      issueTitle: "Issue {n}",
      removeIssue: "Remove",
      removeIssueLabel: "Remove issue {n}",
      addIssue: "Add another issue",
      maxIssuesReached: "A ticket can contain at most 10 issues.",
      issueAdded: "Issue {n} added.",
      issueRemoved: "Issue {n} removed.",
      summaryIssuePrefix: "Issue {n}: ",
      issueNouns: {
        one: "issue",
        few: "issues",
        many: "issues",
        other: "issues",
      },
      requestType: "Type",
      requestTypes: {
        bug: { label: "Bug", hint: "Something isn't working" },
        change_request: {
          label: "Change request",
          hint: "An adjustment or new feature",
        },
        other: { label: "Other", hint: "A question or anything else" },
      },
      priority: "Priority",
      priorities: {
        normal: { label: "Normal", hint: "A regular request" },
        high: { label: "High", hint: "Blocks operations or customers" },
      },
      honeypot: "Leave this field empty",
      submit: "Send request",
      submitMany: "Send {count} {noun}",
      submitting: "Sending…",
      summaryTitle: "Please fix the following:",
      suggestionsLabel: "Did you mean:",
      errors: {
        name: {
          required: "Enter your name.",
          too_short: "Name must be at least 2 characters.",
          too_long: "Name can be at most 100 characters.",
        },
        email: {
          required: "Enter your email.",
          invalid: "Enter a valid email address.",
          too_long: "Email can be at most 254 characters.",
        },
        project: {
          required: "Enter your project's name or code.",
          not_selected: "Choose your project.",
          too_long: "Project name can be at most 100 characters.",
          project_not_found:
            "We couldn't find this project. Check the project name or code.",
          project_ambiguous:
            "This matches more than one project. Please use the exact project code.",
        },
        subject: {
          required: "Enter a subject.",
          too_short: "Subject must be at least 5 characters.",
          too_long: "Subject can be at most 150 characters.",
        },
        description: {
          required: "Describe your request.",
          too_short: "Description must be at least 20 characters.",
          too_long: "Description can be at most 5,000 characters.",
        },
        requestType: {
          required: "Choose a request type.",
        },
        priority: {
          required: "Choose a priority.",
        },
        issues: {
          required: "Add at least one issue.",
          too_many: "A ticket can contain at most 10 issues.",
          invalid: "The issues couldn't be processed.",
        },
        generic: "Please check this value.",
      },
      rootErrors: {
        network:
          "We couldn't confirm that your request arrived. Check your connection and try again – resubmitting won't create a duplicate ticket.",
        rate_limited:
          "You've sent too many requests. Please try again in {minutes} min.",
        unavailable:
          "Your request couldn't be saved right now. Please try again shortly, or use the contact form.",
        conflict:
          "This request was already sent with different content. Reload the page and submit it again.",
        rejected: "Your request couldn't be sent.",
      },
      contactLink: "Contact form",
      successTitle: "Your ticket has been received",
      successReference: "Your ticket number",
      successIssues: "It contains {count} {noun}.",
      successBody:
        "Your ticket is saved. The reply will be sent to {email}. Please mention the ticket number in any follow-up.",
      newRequest: "Create a new ticket",
    },

    footer: {
      rights: "All rights reserved.",
      builtWith: "Built with Next.js, TypeScript and Tailwind CSS.",
    },

    notFound: {
      code: "404",
      title: "Page not found",
      description:
        "This page wandered off into the digital fog. Let’s get you back somewhere useful.",
      backHome: "Back home",
    },
  },
} as const;
