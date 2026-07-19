import type { NextConfig } from 'next';

const nextConfig: NextConfig = {
  images: {
    remotePatterns: [
      { protocol: 'https', hostname: 'esahlan.com' },
      { protocol: 'https', hostname: 'api.esahlan.com' },
    ],
  },
  async rewrites() {
    return [
      {
        source: '/api/:path*',
        destination: 'https://esahlan.com/api/:path*',
      },
    ];
  },
};

export default nextConfig;
