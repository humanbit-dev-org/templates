/** @type {import('next').NextConfig} */
const nextConfig = {
  webpack: (config, { dev }) => {
    if (dev) {
      config.watchOptions = {
        poll: 1000,  // Enable polling, checks for changes every second
        aggregateTimeout: 300,  // Delay rebuild after the first change
        ignored: /node_modules/,  // Ignore node_modules to avoid unnecessary recompilations
      };
    }
    return config;
  },
};

export default nextConfig;
