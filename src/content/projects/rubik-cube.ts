import type { Project } from "@/types/project";

export const rubikCubeProject: Project = {
  slug: "rubik-cube-solver",
  title: {
    cs: "Rubik Cube Solver",
    en: "Rubik Cube Solver",
  },
  description: {
    cs: "Interaktivní řešič Rubikovy kostky s vizuálním 3D zobrazením jednotlivých kroků.",
    en: "Interactive Rubik's Cube solver with visual 3D step-by-step guidance.",
  },
  longDescription: {
    cs: "Webová aplikace pro řešení Rubikovy kostky, která kombinuje interaktivní zadání, výpočet řešení a vizuální 3D prezentaci kroků pro lepší uživatelský zážitek.",
    en: "A browser-based Rubik's Cube solver combining interactive input, solving logic and visual 3D step guidance for a better user experience.",
  },
  tech: ["Next.js", "TypeScript", "Three.js", "Tailwind CSS"],
  image: "/images/projects/rubik-cube.jpg",
  featured: true,
  github: "https://github.com/yourname/yourrepo",
};