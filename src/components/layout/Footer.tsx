import Container from "./Container";

export default function Footer() {
  return (
    <footer className="border-t border-zinc-200 dark:border-zinc-800">
      <Container className="flex flex-col gap-3 py-8 text-sm text-zinc-500 sm:flex-row sm:items-center sm:justify-between dark:text-zinc-400">
        <p>© {new Date().getFullYear()} BeryCode. All rights reserved.</p>
        <p>Built with Next.js, TypeScript and Tailwind CSS.</p>
      </Container>
    </footer>
  );
}
