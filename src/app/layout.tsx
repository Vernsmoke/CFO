import type { Metadata } from "next";
import "./globals.css";
import "./design-system.css";
import "./workspace-polish.css";
import "./university-theme.css";
export const metadata: Metadata = {
  title: { default: "Christian Formation · La Salette", template: "%s · Christian Formation" },
  description:
    "A shared space for faith, formation, and community at the University of La Salette, Inc.",
};
export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="en" data-scroll-behavior="smooth">
      <body>{children}</body>
    </html>
  );
}
