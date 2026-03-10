import type { Metadata } from "next";
import { siteConfig } from "@/lib/metadata";

type SeoInput = {
    title: string;
    description: string;
    path?: string;
};

export function createMetadata({
    title,
    description,
    path = "",
}: SeoInput): Metadata {
    const url = `${siteConfig.url}${path}`;

    return {
        title,
        description,
        alternates: {
            canonical: url,
        },
        openGraph: {
            title,
            description,
            url,
            siteName: siteConfig.name,
            type: "website",
        },
        twitter: {
            card: "summary_large_image",
            title,
            description,
        },
    };
}