type SectionHeadingProps = {
  eyebrow?: string;
  title: string;
  description?: string;
};

export default function SectionHeading({
  eyebrow,
  title,
  description,
}: SectionHeadingProps) {
  return (
    <div className="max-w-2xl space-y-4">
      {eyebrow ? (
        <div className="inline-flex items-center gap-2 rounded-full border border-zinc-200 bg-white/80 px-4 py-2 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/80">
          <span className="h-2 w-2 rounded-full bg-cyan-400" />
          <p className="text-xs font-semibold tracking-[0.22em] text-zinc-500 uppercase dark:text-zinc-400">
            {eyebrow}
          </p>
        </div>
      ) : null}

      <h2 className="text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl dark:text-zinc-100">
        {title}
      </h2>

      {description ? (
        <p className="text-base leading-7 text-zinc-600 sm:text-lg dark:text-zinc-400">
          {description}
        </p>
      ) : null}
    </div>
  );
}
