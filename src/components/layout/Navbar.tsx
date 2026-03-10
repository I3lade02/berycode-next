import Link from "next/link";
import Container from "./Container";

const links = [
    { href: "/", label: "Home" },
    { href: "/projects", label: "Projects" },
    { href: "/about", label: "About" },
    { href: "/contact", label: "Contact" },
];

export default function Navbar() {
    return (
        <header className="sticky top-0 z-50 border-b border-zin-200/80 bg-white/80 backdrop-blur">
            <Container className="flex h-16 items-center justify-between">
                <Link
                    href="/"
                    className="text-lg font-semibold tracking-tight text-zinc-900"
                >
                    BeryCode
                </Link>

                <nav className="flex items-center gap-6">
                    {links.map((link) => (
                        <Link
                            key={link.href}
                            href={link.href}
                            className="text-sm font-medium text-zinc-600 transition hover:text-zinc-900"
                        >
                            {link.label}
                        </Link>
                    ))}
                </nav>
            </Container>
        </header>
    );
}