"use client";

import { Trash2 } from "lucide-react";
import type { FieldErrors, UseFormRegister } from "react-hook-form";

import Input from "@/components/ui/Input";
import Textarea from "@/components/ui/Textarea";
import { useLanguage } from "@/components/providers/LanguageProvider";
import { cn } from "@/lib/utils";
import {
  PRIORITIES,
  REQUEST_TYPES,
  SUPPORT_LIMITS,
  type IssueField,
  type IssueInput,
  type SupportFormInput,
} from "@/lib/support/schema";

export const issueFieldId = (index: number, field: IssueField) =>
  `support-issues-${index}-${field}`;
export const issueTitleId = (index: number) => `support-issue-${index}-title`;

/** Error-summary links point at the first radio of a radio group. */
export function issueAnchor(index: number, field: IssueField) {
  if (field === "requestType")
    return `${issueFieldId(index, field)}-${REQUEST_TYPES[0]}`;
  if (field === "priority")
    return `${issueFieldId(index, field)}-${PRIORITIES[0]}`;
  return issueFieldId(index, field);
}

type Props = {
  index: number;
  register: UseFormRegister<SupportFormInput>;
  errors: FieldErrors<IssueInput> | undefined;
  descriptionLength: number;
  canRemove: boolean;
  onRemove: () => void;
  errorMessage: (field: IssueField, code: string | undefined) => string;
};

const radioCard =
  "flex cursor-pointer items-start gap-3 rounded-xl border border-zinc-200 bg-white/85 p-4 shadow-sm transition hover:border-zinc-400 has-checked:border-zinc-900 has-checked:bg-white has-focus-visible:ring-4 has-focus-visible:ring-zinc-900/10";

export default function SupportIssueCard({
  index,
  register,
  errors,
  descriptionLength,
  canRemove,
  onRemove,
  errorMessage,
}: Props) {
  const { t, locale } = useLanguage();
  const f = t.supportForm;
  const number = String(index + 1);
  const id = (field: IssueField) => issueFieldId(index, field);
  const errorId = (field: IssueField) => `${id(field)}-error`;
  const describedBy = (field: IssueField, hint?: string) =>
    [hint, errors?.[field] ? errorId(field) : null].filter(Boolean).join(" ") ||
    undefined;

  const fieldError = (field: IssueField) =>
    errors?.[field] ? (
      <p id={errorId(field)} className="text-sm text-red-600">
        {errorMessage(field, errors[field]?.message)}
      </p>
    ) : null;

  return (
    <fieldset
      aria-labelledby={issueTitleId(index)}
      className="space-y-5 rounded-2xl border border-zinc-200/80 bg-white/60 p-4 sm:p-5"
    >
      <div className="flex items-center justify-between gap-3">
        <h4
          id={issueTitleId(index)}
          tabIndex={-1}
          className="text-base font-semibold text-zinc-900 outline-none"
        >
          {f.issueTitle.replace("{n}", number)}
        </h4>

        {canRemove ? (
          <button
            type="button"
            onClick={onRemove}
            aria-label={f.removeIssueLabel.replace("{n}", number)}
            className="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm font-medium text-zinc-500 transition hover:bg-red-50 hover:text-red-700 focus-visible:ring-4 focus-visible:ring-red-200"
          >
            <Trash2 className="h-4 w-4" aria-hidden />
            {f.removeIssue}
          </button>
        ) : null}
      </div>

      <fieldset
        className="space-y-3"
        aria-describedby={
          errors?.requestType ? errorId("requestType") : undefined
        }
      >
        <legend className="text-sm font-medium text-zinc-800">
          {f.requestType}
        </legend>
        <div className="grid gap-3 sm:grid-cols-3">
          {REQUEST_TYPES.map((type) => (
            <label key={type} className={radioCard}>
              <input
                id={`${id("requestType")}-${type}`}
                type="radio"
                value={type}
                className="mt-1 h-4 w-4 shrink-0 accent-zinc-900"
                {...register(`issues.${index}.requestType`)}
              />
              <span className="space-y-1">
                <span className="block text-sm font-semibold text-zinc-900">
                  {f.requestTypes[type].label}
                </span>
                <span className="block text-xs text-zinc-500">
                  {f.requestTypes[type].hint}
                </span>
              </span>
            </label>
          ))}
        </div>
        {fieldError("requestType")}
      </fieldset>

      <fieldset
        className="space-y-3"
        aria-describedby={errors?.priority ? errorId("priority") : undefined}
      >
        <legend className="text-sm font-medium text-zinc-800">
          {f.priority}
        </legend>
        <div className="grid gap-3 sm:grid-cols-2">
          {PRIORITIES.map((priority) => (
            <label key={priority} className={radioCard}>
              <input
                id={`${id("priority")}-${priority}`}
                type="radio"
                value={priority}
                className="mt-1 h-4 w-4 shrink-0 accent-zinc-900"
                {...register(`issues.${index}.priority`)}
              />
              <span className="space-y-1">
                <span className="block text-sm font-semibold text-zinc-900">
                  {f.priorities[priority].label}
                </span>
                <span className="block text-xs text-zinc-500">
                  {f.priorities[priority].hint}
                </span>
              </span>
            </label>
          ))}
        </div>
        {fieldError("priority")}
      </fieldset>

      <div className="space-y-2">
        <label
          htmlFor={id("subject")}
          className="text-sm font-medium text-zinc-800"
        >
          {f.subject}
        </label>
        <Input
          id={id("subject")}
          maxLength={SUPPORT_LIMITS.subject.max}
          placeholder={f.subjectPlaceholder}
          aria-required="true"
          aria-invalid={errors?.subject ? true : undefined}
          aria-describedby={describedBy("subject")}
          className={cn(errors?.subject && "border-red-400")}
          {...register(`issues.${index}.subject`)}
        />
        {fieldError("subject")}
      </div>

      <div className="space-y-2">
        <label
          htmlFor={id("description")}
          className="text-sm font-medium text-zinc-800"
        >
          {f.description}
        </label>
        <Textarea
          id={id("description")}
          rows={6}
          maxLength={SUPPORT_LIMITS.description.max}
          placeholder={f.descriptionPlaceholder}
          aria-required="true"
          aria-invalid={errors?.description ? true : undefined}
          aria-describedby={describedBy(
            "description",
            `${id("description")}-counter`,
          )}
          className={cn("min-h-40", errors?.description && "border-red-400")}
          {...register(`issues.${index}.description`)}
        />
        <p
          id={`${id("description")}-counter`}
          className={cn(
            "text-right text-xs",
            descriptionLength > SUPPORT_LIMITS.description.max
              ? "text-red-600"
              : "text-zinc-500",
          )}
        >
          {f.descriptionCounter
            .replace("{count}", descriptionLength.toLocaleString(locale))
            .replace(
              "{max}",
              SUPPORT_LIMITS.description.max.toLocaleString(locale),
            )}
        </p>
        {fieldError("description")}
      </div>
    </fieldset>
  );
}
