import type { Locale } from "./translations";
import type { LocalizedText } from "@/types/project";

export function getLocalizedText(
    value: LocalizedText | undefined,
    locale: Locale,
) {
    if (!value) return "";
    return value[locale];
}