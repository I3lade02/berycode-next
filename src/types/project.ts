export type Project = {
    slug: string;
    title: string;
    description: string;
    longDescription?: string;
    tech: string[];
    href?: string;
    github?: string;
    image: string;
    featured?: boolean;
};