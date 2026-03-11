import { piCloudProject } from "./picloud";
import { rubikCubeProject } from "./rubik-cube";
import { giftFormatProject } from "./gift";

export const projects = [piCloudProject, rubikCubeProject, giftFormatProject];

export function getFeaturedProjects() {
    return projects.filter((project) => project.featured);
}

export function getProjectBySlug(slug: string) {
    return projects.find((project) => project.slug === slug);
}

