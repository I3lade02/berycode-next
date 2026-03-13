import type { Project } from "@/types/project";

export const piCloudProject: Project = {
  slug: "picloud",
  title: {
    cs: "PiCloud",
    en: "PiCloud",
  },
  description: {
    cs: "Osobní cloudová platforma běžící na Raspberry Pi s vlastním webovým rozhraním.",
    en: "Personal cloud storage platform running on Raspberry Pi with a custom web interface.",
  },
  longDescription: {
    cs: "Self-hosted cloud platforma určená pro lokální ukládání a správu souborů, postavená na Raspberry Pi hardwaru a vlastním webovém rozhraní.",
    en: "A self-hosted cloud platform designed for local storage and file access, built around Raspberry Pi hardware and a custom web interface.",
  },
  tech: ["React", "Node.js", "Tailwind CSS", "Raspberry Pi"],
  image: "/images/projects/picloud.jpg",
  featured: true,
  github: "https://github.com/yourname/yourrepo",
};