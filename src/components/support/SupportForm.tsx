"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { Suspense, useCallback, useEffect, useRef, useState } from "react";
import { zodResolver } from "@hookform/resolvers/zod";
import { useFieldArray, useForm, useWatch } from "react-hook-form";
import { CircleCheck, LoaderCircle, Plus, TriangleAlert } from "lucide-react";

import Input from "@/components/ui/Input";
import Select from "@/components/ui/Select";
import SupportIssueCard, {
  issueAnchor,
  issueTitleId,
} from "@/components/support/SupportIssueCard";
import { useLanguage } from "@/components/providers/LanguageProvider";
import type { Locale } from "@/lib/i18n/translations";
import { cn } from "@/lib/utils";
import {
  createIdempotencyKey,
  fetchSupportProjects,
  findProject,
  submitSupportRequest,
  type SupportProject,
} from "@/lib/support/api";
import {
  CONTACT_FIELDS,
  countCharacters,
  emptyIssue,
  ISSUE_FIELDS,
  SUPPORT_LIMITS,
  supportSchema,
  type ContactField,
  type IssueField,
  type SupportFormInput,
  type SupportFormValues,
} from "@/lib/support/schema";

type RootError =
  | { kind: "network" | "unavailable" | "conflict" | "rejected" }
  | { kind: "rate_limited"; minutes: number };

type Success = { reference: string; email: string; issueCount: number };

type SummaryEntry = { key: string; anchor: string; message: string };

// "unavailable" falls back to the free-text field the server still accepts.
type ProjectList =
  | { status: "loading" | "unavailable" }
  | { status: "ready"; projects: SupportProject[] };

const MAX_ISSUES = SUPPORT_LIMITS.issues.max;
const ADD_ISSUE_ID = "support-add-issue";
const fieldId = (field: ContactField) => `support-${field}`;
const errorId = (field: ContactField) => `support-${field}-error`;
const hintId = (field: ContactField) => `support-${field}-hint`;

function isContactField(value: string): value is ContactField {
  return (CONTACT_FIELDS as readonly string[]).includes(value);
}

/** Czech needs one/few/other noun forms (1 požadavek, 3 požadavky, 5 požadavků). */
function countNoun(
  nouns: Record<string, string>,
  locale: Locale,
  count: number,
) {
  return nouns[new Intl.PluralRules(locale).select(count)] ?? nouns.other;
}

/** Reads ?project= to preselect the project. The server still validates it. */
function ProjectPrefill({ onPrefill }: { onPrefill: (value: string) => void }) {
  const project = useSearchParams().get("project");

  useEffect(() => {
    if (project) onPrefill(project);
  }, [project, onPrefill]);

  return null;
}

export default function SupportForm() {
  const { t, locale } = useLanguage();
  const f = t.supportForm;

  const [success, setSuccess] = useState<Success | null>(null);
  const [rootError, setRootError] = useState<RootError | null>(null);
  const [suggestions, setSuggestions] = useState<string[]>([]);
  const [projectList, setProjectList] = useState<ProjectList>({
    status: "loading",
  });
  const [prefill, setPrefill] = useState<string | null>(null);
  const [showSummary, setShowSummary] = useState(false);
  // Bumped after a failed submit so focus moves to the summary only then.
  const [summaryFocusRequest, setSummaryFocusRequest] = useState(0);
  // Screen-reader announcement when an issue is added or removed.
  const [announcement, setAnnouncement] = useState("");

  // One key per distinct submission: reused when the same content is retried,
  // replaced as soon as the content changes or after a success.
  const attempt = useRef<{ key: string; fingerprint: string } | null>(null);
  const pendingFocusId = useRef<string | null>(null);
  const summaryRef = useRef<HTMLDivElement>(null);
  const rootErrorRef = useRef<HTMLDivElement>(null);
  const successHeadingRef = useRef<HTMLHeadingElement>(null);

  const {
    register,
    handleSubmit,
    setError,
    setValue,
    getValues,
    reset,
    control,
    formState: { errors, isSubmitting },
  } = useForm<SupportFormInput, unknown, SupportFormValues>({
    resolver: zodResolver(supportSchema),
    shouldFocusError: false,
    defaultValues: {
      name: "",
      email: "",
      project: "",
      issues: [{ ...emptyIssue }],
      website: "",
    },
  });

  const { fields, append, remove } = useFieldArray({
    control,
    name: "issues",
  });
  const watchedIssues = useWatch({ control, name: "issues" }) ?? [];
  const selectedProject = useWatch({ control, name: "project" });
  const projectSelect = projectList.status !== "unavailable";

  const prefillProject = useCallback((value: string) => {
    const clean = value
      .replace(/\s+/g, " ")
      .trim()
      .slice(0, SUPPORT_LIMITS.project.max);

    setPrefill(clean || null);
  }, []);

  useEffect(() => {
    let ignore = false;

    fetchSupportProjects().then((projects) => {
      if (ignore) return;

      setProjectList(
        projects && projects.length > 0
          ? { status: "ready", projects }
          : { status: "unavailable" },
      );
    });

    return () => {
      ignore = true;
    };
  }, []);

  // Applies ?project= once it is known whether the list or the text field shows.
  useEffect(() => {
    if (!prefill || projectList.status === "loading" || getValues("project")) {
      return;
    }

    if (projectList.status === "ready") {
      const project = findProject(projectList.projects, prefill);

      if (project) setValue("project", project.code);
    } else {
      setValue("project", prefill);
    }
  }, [prefill, projectList, getValues, setValue]);

  useEffect(() => {
    if (summaryFocusRequest > 0) summaryRef.current?.focus();
  }, [summaryFocusRequest]);

  useEffect(() => {
    if (rootError) rootErrorRef.current?.focus();
  }, [rootError]);

  useEffect(() => {
    if (success) successHeadingRef.current?.focus();
  }, [success]);

  // After removing an issue, move focus to the previous issue's heading.
  useEffect(() => {
    if (pendingFocusId.current) {
      document.getElementById(pendingFocusId.current)?.focus();
      pendingFocusId.current = null;
    }
  }, [fields.length]);

  function contactMessage(field: ContactField) {
    let code = errors[field]?.message;

    // An empty list choice reads "choose your project", not "enter its name".
    if (field === "project" && code === "required" && projectSelect) {
      code = "not_selected";
    }
    const messages = f.errors[field] as Record<string, string>;

    return (code && messages[code]) || f.errors.generic;
  }

  function issueMessage(field: IssueField, code: string | undefined) {
    const messages = f.errors[field] as Record<string, string>;

    return (code && messages[code]) || f.errors.generic;
  }

  const issuesListCode = errors.issues?.message ?? errors.issues?.root?.message;
  const issuesListMessage = issuesListCode
    ? ((f.errors.issues as Record<string, string>)[issuesListCode] ??
      f.errors.generic)
    : null;

  const summaryEntries: SummaryEntry[] = [];

  for (const field of CONTACT_FIELDS) {
    if (errors[field]) {
      summaryEntries.push({
        key: field,
        anchor: fieldId(field),
        message: contactMessage(field),
      });
    }
  }

  if (issuesListMessage) {
    summaryEntries.push({
      key: "issues",
      anchor: ADD_ISSUE_ID,
      message: issuesListMessage,
    });
  }

  fields.forEach((_, index) => {
    for (const field of ISSUE_FIELDS) {
      const error = errors.issues?.[index]?.[field];

      if (error) {
        summaryEntries.push({
          key: `${index}-${field}`,
          anchor: issueAnchor(index, field),
          message:
            f.summaryIssuePrefix.replace("{n}", String(index + 1)) +
            issueMessage(field, error.message),
        });
      }
    }
  });

  const summaryVisible = showSummary && summaryEntries.length > 0;

  function describedBy(field: ContactField, hasHint = false) {
    return (
      [hasHint ? hintId(field) : null, errors[field] ? errorId(field) : null]
        .filter(Boolean)
        .join(" ") || undefined
    );
  }

  function addIssue() {
    if (fields.length >= MAX_ISSUES) return;

    const index = fields.length;
    append({ ...emptyIssue }, { focusName: `issues.${index}.subject` });
    setAnnouncement(f.issueAdded.replace("{n}", String(index + 1)));
  }

  function removeIssue(index: number) {
    if (fields.length <= 1) return;

    remove(index);
    pendingFocusId.current = issueTitleId(Math.max(0, index - 1));
    setAnnouncement(f.issueRemoved.replace("{n}", String(index + 1)));
  }

  async function onSubmit(values: SupportFormValues) {
    setRootError(null);
    setSuggestions([]);
    setShowSummary(false);

    const fingerprint = JSON.stringify([
      values.name,
      values.email,
      values.project,
      values.issues,
    ]);

    if (attempt.current?.fingerprint !== fingerprint) {
      attempt.current = { key: createIdempotencyKey(), fingerprint };
    }

    const result = await submitSupportRequest(
      values,
      locale,
      attempt.current.key,
    );

    switch (result.kind) {
      case "success":
        attempt.current = null;
        setSuccess({
          reference: result.reference,
          email: values.email.trim(),
          issueCount: values.issues.length,
        });
        return;
      case "validation": {
        let fieldErrors = 0;

        for (const [key, code] of Object.entries(result.fields)) {
          const issueMatch =
            /^issues\.(\d+)\.(requestType|priority|subject|description)$/.exec(
              key,
            );

          if (isContactField(key)) {
            setError(key, { type: "server", message: code });
          } else if (key === "issues") {
            setError("issues", { type: "server", message: code });
          } else if (issueMatch && Number(issueMatch[1]) < fields.length) {
            setError(
              `issues.${Number(issueMatch[1])}.${issueMatch[2] as IssueField}`,
              { type: "server", message: code },
            );
          } else {
            continue;
          }

          fieldErrors++;
        }

        setSuggestions(result.suggestions);

        if (fieldErrors > 0) {
          setShowSummary(true);
          setSummaryFocusRequest((count) => count + 1);
        } else {
          setRootError({ kind: "rejected" });
        }
        return;
      }
      case "rate_limited":
        setRootError({
          kind: "rate_limited",
          minutes: Math.max(1, Math.ceil(result.retryAfterSeconds / 60)),
        });
        return;
      default:
        setRootError({ kind: result.kind });
    }
  }

  function onInvalid() {
    setRootError(null);
    setShowSummary(true);
    setSummaryFocusRequest((count) => count + 1);
  }

  function startOver() {
    reset();
    setSuccess(null);
    setSuggestions([]);
    setRootError(null);
    setShowSummary(false);
    setAnnouncement("");
  }

  function applySuggestion(name: string) {
    const listed =
      projectList.status === "ready"
        ? findProject(projectList.projects, name)
        : undefined;

    setValue("project", listed?.code ?? name, { shouldValidate: true });
    setSuggestions([]);
  }

  if (success) {
    return (
      <div
        className="glass-card soft-shadow space-y-6 rounded-[1.75rem] p-6 sm:p-8"
        role="status"
      >
        <CircleCheck className="h-10 w-10 text-emerald-600" aria-hidden />

        <div className="space-y-3">
          <h2
            ref={successHeadingRef}
            tabIndex={-1}
            className="text-2xl font-semibold tracking-tight text-zinc-900 outline-none"
          >
            {f.successTitle}
          </h2>

          <p className="text-sm font-medium text-zinc-500">
            {f.successReference}
          </p>
          <p className="font-mono text-3xl font-semibold tracking-tight text-zinc-900">
            {success.reference}
          </p>
          <p className="text-sm text-zinc-600">
            {f.successIssues
              .replace("{count}", String(success.issueCount))
              .replace(
                "{noun}",
                countNoun(f.issueNouns, locale, success.issueCount),
              )}
          </p>
        </div>

        <p className="leading-7 text-zinc-600">
          {f.successBody.replace("{email}", success.email)}
        </p>

        <button
          type="button"
          onClick={startOver}
          className="inline-flex rounded-xl border border-zinc-200 bg-white px-5 py-3 text-sm font-semibold text-zinc-900 shadow-sm transition hover:bg-zinc-100 focus-visible:ring-4 focus-visible:ring-zinc-900/15"
        >
          {f.newRequest}
        </button>
      </div>
    );
  }

  const submitLabel =
    fields.length === 1
      ? f.submit
      : f.submitMany
          .replace("{count}", String(fields.length))
          .replace("{noun}", countNoun(f.issueNouns, locale, fields.length));

  return (
    <form
      onSubmit={(event) => handleSubmit(onSubmit, onInvalid)(event)}
      className="glass-card soft-shadow relative space-y-8 rounded-[1.75rem] p-6 sm:p-8"
      aria-busy={isSubmitting}
      noValidate
    >
      <Suspense fallback={null}>
        <ProjectPrefill onPrefill={prefillProject} />
      </Suspense>

      <p className="text-sm text-zinc-500">{f.requiredNote}</p>

      {summaryVisible ? (
        <div
          ref={summaryRef}
          tabIndex={-1}
          role="alert"
          aria-labelledby="support-error-summary-title"
          className="rounded-2xl border border-red-200 bg-red-50/80 p-4 outline-none focus-visible:ring-4 focus-visible:ring-red-200"
        >
          <p
            id="support-error-summary-title"
            className="text-sm font-semibold text-red-800"
          >
            {f.summaryTitle}
          </p>
          <ul className="mt-2 list-disc space-y-1 pl-5 text-sm text-red-700">
            {summaryEntries.map((entry) => (
              <li key={entry.key}>
                <a
                  href={`#${entry.anchor}`}
                  className="underline underline-offset-2 hover:text-red-900"
                >
                  {entry.message}
                </a>
              </li>
            ))}
          </ul>
        </div>
      ) : null}

      <section aria-labelledby="support-contact-title" className="space-y-6">
        <h3
          id="support-contact-title"
          className="text-lg font-semibold tracking-tight text-zinc-900"
        >
          {f.contactSection}
        </h3>

        <div className="grid gap-6 sm:grid-cols-2">
          <div className="space-y-2">
            <label
              htmlFor={fieldId("name")}
              className="text-sm font-medium text-zinc-800"
            >
              {f.name}
            </label>
            <Input
              id={fieldId("name")}
              autoComplete="name"
              maxLength={SUPPORT_LIMITS.name.max}
              placeholder={f.namePlaceholder}
              aria-required="true"
              aria-invalid={errors.name ? true : undefined}
              aria-describedby={describedBy("name")}
              className={cn(errors.name && "border-red-400")}
              {...register("name")}
            />
            {errors.name ? (
              <p id={errorId("name")} className="text-sm text-red-600">
                {contactMessage("name")}
              </p>
            ) : null}
          </div>

          <div className="space-y-2">
            <label
              htmlFor={fieldId("email")}
              className="text-sm font-medium text-zinc-800"
            >
              {f.email}
            </label>
            <Input
              id={fieldId("email")}
              type="email"
              inputMode="email"
              autoComplete="email"
              maxLength={SUPPORT_LIMITS.email.max}
              placeholder={f.emailPlaceholder}
              aria-required="true"
              aria-invalid={errors.email ? true : undefined}
              aria-describedby={describedBy("email", true)}
              className={cn(errors.email && "border-red-400")}
              {...register("email")}
            />
            <p id={hintId("email")} className="text-xs text-zinc-500">
              {f.emailHint}
            </p>
            {errors.email ? (
              <p id={errorId("email")} className="text-sm text-red-600">
                {contactMessage("email")}
              </p>
            ) : null}
          </div>
        </div>

        <div className="space-y-2">
          <label
            htmlFor={fieldId("project")}
            className="text-sm font-medium text-zinc-800"
          >
            {f.project}
          </label>
          {projectSelect ? (
            <Select
              id={fieldId("project")}
              aria-required="true"
              aria-busy={projectList.status === "loading" || undefined}
              aria-invalid={errors.project ? true : undefined}
              aria-describedby={describedBy("project")}
              className={cn(
                !selectedProject && "text-zinc-400",
                errors.project && "border-red-400",
              )}
              {...register("project")}
            >
              <option value="">
                {projectList.status === "loading"
                  ? f.projectLoading
                  : f.projectSelectPlaceholder}
              </option>
              {projectList.status === "ready"
                ? projectList.projects.map((project) => (
                    <option key={project.code} value={project.code}>
                      {project.name}
                    </option>
                  ))
                : null}
            </Select>
          ) : (
            <>
              <Input
                id={fieldId("project")}
                autoComplete="off"
                maxLength={SUPPORT_LIMITS.project.max}
                placeholder={f.projectPlaceholder}
                aria-required="true"
                aria-invalid={errors.project ? true : undefined}
                aria-describedby={describedBy("project", true)}
                className={cn(errors.project && "border-red-400")}
                {...register("project")}
              />
              <p id={hintId("project")} className="text-xs text-zinc-500">
                {f.projectHint}
              </p>
            </>
          )}
          {errors.project ? (
            <p id={errorId("project")} className="text-sm text-red-600">
              {contactMessage("project")}
            </p>
          ) : null}
          {suggestions.length > 0 ? (
            <div className="flex flex-wrap items-center gap-2 text-sm text-zinc-600">
              <span>{f.suggestionsLabel}</span>
              {suggestions.map((name) => (
                <button
                  key={name}
                  type="button"
                  onClick={() => applySuggestion(name)}
                  className="rounded-full border border-zinc-200 bg-white px-3 py-1 font-medium text-zinc-800 shadow-sm transition hover:border-zinc-400 focus-visible:ring-4 focus-visible:ring-zinc-900/10"
                >
                  {name}
                </button>
              ))}
            </div>
          ) : null}
        </div>
      </section>

      <section aria-labelledby="support-issues-title" className="space-y-4">
        <div className="space-y-1">
          <h3
            id="support-issues-title"
            className="text-lg font-semibold tracking-tight text-zinc-900"
          >
            {f.issuesSection}
          </h3>
          <p className="text-sm text-zinc-500">{f.issuesHint}</p>
        </div>

        {fields.map((field, index) => (
          <SupportIssueCard
            key={field.id}
            index={index}
            register={register}
            errors={errors.issues?.[index]}
            descriptionLength={countCharacters(
              watchedIssues[index]?.description ?? "",
            )}
            canRemove={fields.length > 1}
            onRemove={() => removeIssue(index)}
            errorMessage={issueMessage}
          />
        ))}

        {issuesListMessage ? (
          <p className="text-sm text-red-600">{issuesListMessage}</p>
        ) : null}

        <div className="flex flex-wrap items-center gap-3">
          <button
            id={ADD_ISSUE_ID}
            type="button"
            onClick={addIssue}
            disabled={fields.length >= MAX_ISSUES}
            aria-describedby="support-issue-count"
            className="inline-flex items-center gap-2 rounded-xl border border-dashed border-zinc-300 bg-white/70 px-4 py-2.5 text-sm font-semibold text-zinc-800 transition hover:border-zinc-500 hover:bg-white focus-visible:ring-4 focus-visible:ring-zinc-900/10 disabled:cursor-not-allowed disabled:opacity-50"
          >
            <Plus className="h-4 w-4" aria-hidden />
            {f.addIssue}
          </button>
          <p id="support-issue-count" className="text-xs text-zinc-500">
            {fields.length >= MAX_ISSUES
              ? f.maxIssuesReached
              : `${fields.length} / ${MAX_ISSUES}`}
          </p>
        </div>
      </section>

      {/* Honeypot: hidden from people and assistive technology. */}
      <div
        aria-hidden="true"
        className="absolute -left-[9999px] h-px w-px overflow-hidden"
      >
        <label htmlFor="support-website">{f.honeypot}</label>
        <input
          id="support-website"
          type="text"
          tabIndex={-1}
          autoComplete="off"
          {...register("website")}
        />
      </div>

      {rootError ? (
        <div
          ref={rootErrorRef}
          tabIndex={-1}
          role="alert"
          className="flex gap-3 rounded-2xl border border-red-200 bg-red-50/80 p-4 text-sm text-red-700 outline-none focus-visible:ring-4 focus-visible:ring-red-200"
        >
          <TriangleAlert className="mt-0.5 h-4 w-4 shrink-0" aria-hidden />
          <p>
            {rootError.kind === "rate_limited"
              ? f.rootErrors.rate_limited.replace(
                  "{minutes}",
                  String(rootError.minutes),
                )
              : f.rootErrors[rootError.kind]}{" "}
            {rootError.kind === "unavailable" ? (
              <Link
                href="/contact"
                prefetch={false}
                className="font-semibold underline underline-offset-2"
              >
                {f.contactLink}
              </Link>
            ) : null}
          </p>
        </div>
      ) : null}

      <button
        type="submit"
        disabled={isSubmitting}
        className="inline-flex items-center gap-2 rounded-xl border border-zinc-900 bg-zinc-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-zinc-800 focus-visible:ring-4 focus-visible:ring-zinc-900/20 disabled:cursor-not-allowed disabled:opacity-60"
      >
        {isSubmitting ? (
          <LoaderCircle className="h-4 w-4 animate-spin" aria-hidden />
        ) : null}
        {isSubmitting ? f.submitting : submitLabel}
      </button>

      <p className="sr-only" aria-live="polite">
        {announcement}
      </p>
    </form>
  );
}
