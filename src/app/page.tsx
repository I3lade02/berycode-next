export default function HomePage() {
  return (
    <main className="mx-auto flex min-h-screen max-w-6xl flex-col px-6 py-24">
      <section className="space-y-6">
        <p className="text-sm font-medium uppercase tracking-[0.2em] text-zinc-500">
          BeryCode
        </p>

        <h1 className="max-w-3xl text-4xl font-bold tracking-tight text-zinc-900 sm:text-6xl">
          Ondra Beránek
        </h1>

        <p className="max-w-2xl text-lg leading-8 text-zinc-600">
          FrontEnd develope building modern web apps, mobile apps, interactive tools and
          experimental projects with Next.js, TypeScript, Python and creative frontend technologies.
        </p>
      </section>
    </main>
  );
}