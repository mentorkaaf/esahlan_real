import type { NextConfig } from 'next';

const nextConfig: NextConfig = {
  output: 'standalone',
  images: {
    remotePatterns: [
      { protocol: 'https', hostname: 'esahlan.com' },
      { protocol: 'https', hostname: 'api.esahlan.com' },
    ],
  },
};

export default nextConfig;
