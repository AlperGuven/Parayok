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
      console.log("Getting CSRF token...");
      await api.get("/sanctum/csrf-cookie");
      console.log("CSRF token obtained, making API call...");

      const response = await api.post("/api/auth/jira/callback", { code, state });
      console.log("API response:", response.data);

      if (response.data.user && response.data.token) {
        console.log("Setting user and token in store:", response.data.user);
        authStore.setAuth(response.data.user, response.data.token);
      } else if (response.data.user) {
        // Fallback for session auth if token is missing (shouldn't happen with new backend)
        console.log("Setting user in store (no token):", response.data.user);
        authStore.user = response.data.user;
      }

      console.log("Waiting a bit...");
      await new Promise((resolve) => setTimeout(resolve, 500));

      await router.push("/dashboard");
      console.log("Redirecting to dashboard...");
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
  <div class="min-h-screen flex items-center justify-center bg-gray-50">
    <div class="text-center p-8 bg-white rounded-lg shadow-md">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
      <p class="text-gray-600">Signing you in...</p>
      <p class="text-xs text-gray-400 mt-2">Check console for details</p>
    </div>
  </div>
</template>
