import type { NextConfig } from "next";
import { PHASE_DEVELOPMENT_SERVER } from "next/constants";

const nextConfig: NextConfig = {
  output: "export",
  trailingSlash: true,
  images: {
    unoptimized: true,
  },
};

// The support API is PHP next to the static export (backend/web). During
// `next dev` proxy it to the local PHP server started by `npm run support:dev`.
export default function config(phase: string): NextConfig {
  if (phase !== PHASE_DEVELOPMENT_SERVER) {
    return nextConfig;
  }

  const supportApiOrigin =
    process.env.SUPPORT_API_DEV_ORIGIN ?? "http://127.0.0.1:8080";

  return {
    ...nextConfig,
    async rewrites() {
      return [
        {
          source: "/api/support/:path*",
          destination: `${supportApiOrigin}/api/support/:path*`,
        },
      ];
    },
  };
}
