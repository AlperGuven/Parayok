import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import { resolve } from "path";

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      "@": resolve(__dirname, "src"),
    },
  },
  server: {
    port: 3000,
    proxy: {
      "/api": {
        target: "http://localhost:8000",
        changeOrigin: true,
        cookieDomainRewrite: "",
        configure: (proxy, options) => {
          proxy.on("proxyReq", (proxyReq, req, res) => {
            proxyReq.setHeader("Origin", "http://localhost:8000");
          });
        },
      },
      "/sanctum": {
        target: "http://localhost:8000",
        changeOrigin: true,
        cookieDomainRewrite: "",
        configure: (proxy, options) => {
          proxy.on("proxyReq", (proxyReq, req, res) => {
            proxyReq.setHeader("Origin", "http://localhost:8000");
          });
        },
      },
      "^/auth/jira$": {
        target: "http://localhost:8000",
        changeOrigin: true,
        cookieDomainRewrite: "",
        configure: (proxy, options) => {
          proxy.on("proxyReq", (proxyReq, req, res) => {
            proxyReq.setHeader("Origin", "http://localhost:8000");
          });
        },
      },
    },
    fs: {
      allow: [".."],
    },
    middlewareMode: false,
  },
  appType: "spa",
});
