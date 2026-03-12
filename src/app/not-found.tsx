import Link from "next/link";
import Container from "@/components/layout/Container";

export default function NotFoundPage() {
  return (
    <main className="py-24">
      <Container className="space-y-6 text-center">
        <p className="text-sm font-semibold tracking-[0.2em] text-zinc-500 uppercase dark:text-zinc-400">
          404
        </p>
        <h1 className="text-4xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">
          Page not found
        </h1>
        <p className="mx-auto max-w-xl text-zinc-600 dark:text-zinc-400">
          This page wandered off into the digital fog. Let’s get you back to
          somewhere useful.
        </p>
        <Link
          href="/"
          className="inline-flex rounded-xl bg-zinc-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-zinc-800"
          prefetch={false}
        >
          Back home
        </Link>
      </Container>
    </main>
  );
}
