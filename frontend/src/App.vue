<script setup lang="ts">
import { onMounted, onUnmounted } from "vue";
import { RouterView } from "vue-router";
import { useAuthStore } from "@/stores/authStore";

const authStore = useAuthStore();

const checkSession = async () => {
  if (authStore.token) {
    try {
      await authStore.fetchUser();
    } catch (e) {
      // 401 is handled by authStore/api interceptor
    }
  }
};

onMounted(() => {
  // Validate session on app load if token exists
  checkSession();

  // Re-validate session when window regains focus
  window.addEventListener("focus", checkSession);
});

onUnmounted(() => {
  window.removeEventListener("focus", checkSession);
});
</script>

<template>
  <RouterView />
</template>
