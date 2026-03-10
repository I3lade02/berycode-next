import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: {
    default: "Ondra Beránek | Web develope portfolio",
    template: "%s | Ondra Beránek",
  },
  description: "Portfolio of Ondra Beránek, a web and mobile phoen developer building modern web apps, interactive tools and experimental projects",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}