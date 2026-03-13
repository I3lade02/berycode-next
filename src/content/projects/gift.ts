import type { Project } from "@/types/project";

export const giftFormatProject: Project = {
    slug: "gift",
    title: {
        cs: "Gift Formatter",
        en: "Gift Formatter",
    },
    description: {
        cs: "Jednoduchý nástroj pro přetváření klasického textu z .docx do podoby .gift pro tvorbu testů do systému Moodle",
        en: "Simple tool for recreating basic text from .docx to .gift format for creating exam test for Moodle system"
    },
    longDescription: {
        cs: "Nástroj pro tvorbu testů pro systém Moodle vytvořený v jazyce Python s pomocí knihovny PySide6 pro GUI, jednoduchý nástroj pro usnadnění práce s možností exportu, kopírování, náhledu testu. Zvýraznění jako v syntaxi",
        en: "Tool for exam tests creation for Moodle system created in Python with PySide6 library for GUI, simple tool for making work easier with exporting, copying or test preview. Syntax highlighting",
    },
    tech: ["Python", "PySide6"],
    image: "/images/projects/gift.jpg",
    featured: true,
    github: "https://github.com/I3lade02/testMaker",
};