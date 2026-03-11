<script setup lang="ts">
import { ref } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "@/stores/authStore";
import api from "@/services/api";
import logo from "@/assets/images/parayok.png";

const router = useRouter();
const authStore = useAuthStore();

const showGuestForm = ref(false);
const guestName = ref("");
const loading = ref(false);
const error = ref("");

async function loginWithJira() {
  try {
    await api.get("/sanctum/csrf-cookie");
    window.location.href = "/auth/jira";
  } catch (error) {
    console.error("Login error:", error);
  }
}

async function joinAsGuest() {
  if (!guestName.value.trim()) {
    error.value = "Name is required";
    return;
  }
  
  loading.value = true;
  error.value = "";
  
  try {
    await api.get("/sanctum/csrf-cookie");
    await authStore.loginAsGuest(guestName.value);
    // If there's a redirect query param, go there (e.g. room link), otherwise dashboard
    const redirect = router.currentRoute.value.query.redirect as string;
    router.push(redirect || "/dashboard");
  } catch (e: any) {
    error.value = "Failed to join as guest";
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-[#041628]">
    <div class="art-deco-card p-12 rounded-none text-center max-w-md w-full mx-4 relative">
      <div class="mb-2 flex justify-center">
        <img :src="logo" alt="Parayok" class="h-48 mx-auto mb-4" />
      </div>

      <p class="text-gray-400 mb-8 font-sans text-lg">Estimate Jira issues with your team in real-time</p>

      <div v-if="!showGuestForm" class="space-y-4">
        <button
          @click="loginWithJira"
          class="art-deco-button primary block w-full py-3 px-4 font-bold text-center tracking-widest uppercase"
        >
          LOGIN WITH JIRA
        </button>
        
        <button
          @click="showGuestForm = true"
          class="w-full py-3 px-4 border border-[#fdfc04] text-[#fdfc04] hover:bg-[#fdfc04] hover:text-[#041628] transition-colors font-bold tracking-widest uppercase font-display"
        >
          JOIN AS GUEST
        </button>
      </div>

      <div v-else class="space-y-6 animate-fade-in">
        <div>
          <label class="block text-xs font-bold text-[#fdfc04] mb-2 uppercase tracking-widest text-left">
            YOUR NAME
          </label>
          <input
            v-model="guestName"
            type="text"
            placeholder="ENTER YOUR NAME"
            class="w-full art-deco-input py-3 text-lg"
            @keyup.enter="joinAsGuest"
            autoFocus
          />
        </div>
        
        <div v-if="error" class="text-red-500 text-sm font-sans">{{ error }}</div>

        <button
          @click="joinAsGuest"
          :disabled="loading"
          class="art-deco-button primary block w-full py-3 px-4 font-bold text-center tracking-widest uppercase disabled:opacity-50"
        >
          {{ loading ? 'JOINING...' : 'JOIN ROOM' }}
        </button>
        
        <button
          @click="showGuestForm = false"
          class="text-gray-500 hover:text-gray-300 text-xs uppercase tracking-widest"
        >
          CANCEL
        </button>
      </div>

      <!-- Corner decorations -->
      <div class="absolute top-0 left-0 w-4 h-4 border-t-2 border-l-2 border-[#fdfc04]"></div>
      <div class="absolute bottom-0 right-0 w-4 h-4 border-b-2 border-r-2 border-[#fdfc04]"></div>
    </div>
  </div>
</template>
