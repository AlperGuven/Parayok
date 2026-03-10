<script setup>
import { onMounted } from "vue";
import { useRouter } from "vue-router";
import api from "../services/api";
import { useAuthStore } from "../stores/authStore";

const router = useRouter();
const authStore = useAuthStore();

onMounted(async () => {
  const urlParams = new URLSearchParams(window.location.search);
  const code = urlParams.get("code");
  const state = urlParams.get("state");

  console.log("Callback page loaded", { code: !!code, state });

  if (code) {
    try {
      await api.get("/sanctum/csrf-cookie");

      const response = await api.post("/api/auth/jira/callback", { code, state });

      if (response.data.user && response.data.token) {
        authStore.setAuth(response.data.user, response.data.token);
      } else if (response.data.user) {
        // Fallback for session auth if token is missing (shouldn't happen with new backend)
        console.log("Setting user in store (no token):", response.data.user);
        authStore.user = response.data.user;
      }

      await new Promise((resolve) => setTimeout(resolve, 500));

      await router.push("/dashboard");
    } catch (error) {
      console.error("Auth error:", error);
      console.error("Error response:", error.response?.data);
      if (error.response?.data?.redirect) {
        router.push(error.response.data.redirect);
      } else {
        router.push("/");
      }
    }
  } else {
    console.log("No code, redirecting to home");
    router.push("/");
  }
});
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-[#041628]">
    <div class="art-deco-card p-12 text-center rounded-none shadow-glow-gold max-w-sm w-full mx-4">
      <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-[#fdfc04] mx-auto mb-6"></div>
      <p class="text-[#fdfc04] font-display text-xl tracking-widest uppercase mb-2">Signing you in...</p>
      <p class="text-xs text-gray-400 font-sans uppercase tracking-wider">Please wait</p>

      <!-- Corner decorations -->
      <div class="absolute top-0 left-0 w-4 h-4 border-t-2 border-l-2 border-[#fdfc04]"></div>
      <div class="absolute bottom-0 right-0 w-4 h-4 border-b-2 border-r-2 border-[#fdfc04]"></div>
    </div>
  </div>
</template>
