import type { Locale } from "@/lib/i18n/translations";
import type { SupportFormValues } from "./schema";

// Same-origin PHP endpoint (backend/web/api/support/tickets.php). In `next dev`
// it is proxied to the local PHP server, see next.config.ts.
export const SUPPORT_TICKETS_URL = "/api/support/tickets.php";

// Up to 10 issues can make a large request; allow for slow connections.
const REQUEST_TIMEOUT_MS = 30_000;

export type SubmitResult =
  | { kind: "success"; reference: string }
  | {
      kind: "validation";
      fields: Record<string, string>;
      suggestions: string[];
    }
  | { kind: "rate_limited"; retryAfterSeconds: number }
  | { kind: "conflict" }
  | { kind: "rejected" }
  | { kind: "unavailable" }
  | { kind: "network" };

/** Random key identifying one submission attempt, reused for browser retries. */
export function createIdempotencyKey() {
  if (typeof crypto.randomUUID === "function") {
    return crypto.randomUUID();
  }

  const bytes = new Uint8Array(16);
  crypto.getRandomValues(bytes);

  return Array.from(bytes, (byte) => byte.toString(16).padStart(2, "0")).join(
    "",
  );
}

type ResponseBody = {
  ok?: boolean;
  reference?: unknown;
  error?: unknown;
  fields?: unknown;
  suggestions?: unknown;
  retryAfter?: unknown;
};

function stringRecord(value: unknown): Record<string, string> {
  if (!value || typeof value !== "object") return {};

  return Object.fromEntries(
    Object.entries(value).filter(
      (entry): entry is [string, string] => typeof entry[1] === "string",
    ),
  );
}

export async function submitSupportRequest(
  values: SupportFormValues,
  locale: Locale,
  idempotencyKey: string,
): Promise<SubmitResult> {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS);

  let response: Response;

  try {
    response = await fetch(SUPPORT_TICKETS_URL, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      credentials: "same-origin",
      body: JSON.stringify({
        name: values.name,
        email: values.email,
        project: values.project,
        issues: values.issues.map((issue) => ({
          requestType: issue.requestType,
          priority: issue.priority,
          subject: issue.subject,
          description: issue.description,
        })),
        website: values.website ?? "",
        locale,
        idempotencyKey,
      }),
      signal: controller.signal,
    });
  } catch {
    return { kind: "network" };
  } finally {
    clearTimeout(timeout);
  }

  let body: ResponseBody = {};

  try {
    body = (await response.json()) as ResponseBody;
  } catch {
    // Non-JSON answer (proxy error page etc.) is handled by status below.
  }

  if (response.ok && body.ok === true && typeof body.reference === "string") {
    return { kind: "success", reference: body.reference };
  }

  switch (response.status) {
    case 422:
      if (body.error === "validation_failed") {
        return {
          kind: "validation",
          fields: stringRecord(body.fields),
          suggestions: Array.isArray(body.suggestions)
            ? body.suggestions.filter(
                (item): item is string => typeof item === "string",
              )
            : [],
        };
      }

      return { kind: "rejected" };
    case 429:
      return {
        kind: "rate_limited",
        retryAfterSeconds:
          typeof body.retryAfter === "number" ? body.retryAfter : 600,
      };
    case 409:
      return { kind: "conflict" };
    case 400:
    case 403:
    case 413:
    case 415:
      return { kind: "rejected" };
    default:
      // 5xx, a missing endpoint, or a 2xx without a reference: not saved.
      return { kind: "unavailable" };
  }
}
