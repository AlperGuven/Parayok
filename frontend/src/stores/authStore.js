import { defineStore } from "pinia";
import { ref, computed } from "vue";
import api from "../services/api";

export const useAuthStore = defineStore("auth", () => {
  const user = ref(JSON.parse(localStorage.getItem("user")) || null);
  const token = ref(localStorage.getItem("token") || null);
  const loading = ref(false);

  const isAuthenticated = computed(() => !!token.value);

  async function fetchUser() {
    if (!token.value) return;

    loading.value = true;
    try {
      console.log("authStore: fetching user from API...");
      const response = await api.get("/api/user");
      console.log("authStore: user response:", response.data);
      user.value = response.data;
      localStorage.setItem("user", JSON.stringify(response.data));
    } catch (error) {
      console.error("authStore: failed to fetch user:", error);
      // If unauthorized, clear everything
      if (error.response?.status === 401) {
        logout();
      }
    } finally {
      loading.value = false;
    }
  }

  function setAuth(userData, authToken) {
    console.log("authStore: setting auth:", userData);
    user.value = userData;
    token.value = authToken;
    localStorage.setItem("user", JSON.stringify(userData));
    localStorage.setItem("token", authToken);
  }

  function logout() {
    user.value = null;
    token.value = null;
    localStorage.removeItem("user");
    localStorage.removeItem("token");
  }

  return {
    user,
    token,
    loading,
    isAuthenticated,
    fetchUser,
    setAuth,
    logout,
  };
});
