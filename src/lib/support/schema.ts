import { z } from "zod";

// Mirrors backend TicketValidator. The server is authoritative; these checks
// only give faster feedback. Error messages are codes translated in the UI.
export const SUPPORT_LIMITS = {
  name: { min: 2, max: 100 },
  email: { max: 254 },
  project: { max: 100 },
  subject: { min: 5, max: 150 },
  description: { min: 20, max: 5000 },
  issues: { max: 10 },
} as const;

export const REQUEST_TYPES = ["bug", "change_request", "other"] as const;
export const PRIORITIES = ["normal", "high"] as const;

export type RequestType = (typeof REQUEST_TYPES)[number];
export type Priority = (typeof PRIORITIES)[number];

export const CONTACT_FIELDS = ["name", "email", "project"] as const;
export const ISSUE_FIELDS = [
  "requestType",
  "priority",
  "subject",
  "description",
] as const;

export type ContactField = (typeof CONTACT_FIELDS)[number];
export type IssueField = (typeof ISSUE_FIELDS)[number];

/** Counts characters (code points) like the server's mb_strlen. */
export function countCharacters(value: string) {
  return Array.from(value).length;
}

const singleLine = (value: string) => value.replace(/\s+/g, " ").trim();

function boundedText(
  min: number,
  max: number,
  normalize: (value: string) => string,
) {
  return z.string().superRefine((raw, ctx) => {
    const length = countCharacters(normalize(raw));

    if (length === 0) {
      ctx.addIssue({ code: "custom", message: "required" });
    } else if (length < min) {
      ctx.addIssue({ code: "custom", message: "too_short" });
    } else if (length > max) {
      ctx.addIssue({ code: "custom", message: "too_long" });
    }
  });
}

const emailFormat = z.email();

export const issueSchema = z.object({
  requestType: z.enum(REQUEST_TYPES, { message: "required" }),
  priority: z.enum(PRIORITIES, { message: "required" }),
  subject: boundedText(
    SUPPORT_LIMITS.subject.min,
    SUPPORT_LIMITS.subject.max,
    singleLine,
  ),
  description: boundedText(
    SUPPORT_LIMITS.description.min,
    SUPPORT_LIMITS.description.max,
    (value) => value.trim(),
  ),
});

export const supportSchema = z.object({
  name: boundedText(
    SUPPORT_LIMITS.name.min,
    SUPPORT_LIMITS.name.max,
    singleLine,
  ),
  email: z.string().superRefine((raw, ctx) => {
    const value = raw.trim();

    if (value === "") {
      ctx.addIssue({ code: "custom", message: "required" });
    } else if (value.length > SUPPORT_LIMITS.email.max) {
      ctx.addIssue({ code: "custom", message: "too_long" });
    } else if (!emailFormat.safeParse(value).success) {
      ctx.addIssue({ code: "custom", message: "invalid" });
    }
  }),
  project: boundedText(1, SUPPORT_LIMITS.project.max, singleLine),
  issues: z
    .array(issueSchema)
    .min(1, { message: "required" })
    .max(SUPPORT_LIMITS.issues.max, { message: "too_many" }),
  // Honeypot: must stay empty.
  website: z.string().optional(),
});

export type SupportFormInput = z.input<typeof supportSchema>;
export type SupportFormValues = z.output<typeof supportSchema>;
export type IssueInput = z.input<typeof issueSchema>;

export const emptyIssue: IssueInput = {
  // No default type: the customer has to choose one.
  requestType: undefined as unknown as RequestType,
  priority: "normal",
  subject: "",
  description: "",
};
