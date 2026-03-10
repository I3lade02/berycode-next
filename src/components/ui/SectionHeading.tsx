type SectionHeadingProps = {
    eyebrow?: string;
    title: string;
    description?: string;
};

export default function SectionHeading({
    eyebrow,
    title,
    description
}: SectionHeadingProps) {
    return (
        <div className="max-w-2xl space-y-3">
            {eyebrow ? (
                <p className="text-sm font-semibold uppercase tracking-[0.2em] text-zinc-500">
                    {eyebrow}
                </p>
            ): null}

            <h2 className="text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">
                {title}
            </h2>

            {description ? (
                <p className="text-base leading-7 text-zinc-600 sm:text-lg">
                    {description}
                </p>
            ): null}
        </div>
    );
}