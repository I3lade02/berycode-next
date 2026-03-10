import { piCloudProject } from "./picloud";
import { rubikCubeProject } from "./rubik-cube";

export const projects = [piCloudProject, rubikCubeProject];

export function getFeaturedProjects() {
    return projects.filter((project) => project.featured);
}

export function getProjectBySlug(slug: string) {
    return projects.find((project) => project.slug === slug);
}