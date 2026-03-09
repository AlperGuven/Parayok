<script setup lang="ts">
import { ref } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "@/stores/authStore";
import api from "@/services/api";

const router = useRouter();
const authStore = useAuthStore();

const roomName = ref("");
const loading = ref(false);
const error = ref("");

async function createRoom() {
  if (!roomName.value.trim()) {
    error.value = "Room name is required";
    return;
  }

  loading.value = true;
  error.value = "";

  try {
    const response = await api.post("/api/rooms", { name: roomName.value });
    router.push(`/room/${response.data.uuid}`);
  } catch (e: any) {
    error.value = e.response?.data?.message || "Failed to create room";
  } finally {
    loading.value = false;
  }
}

function goBack() {
  router.push("/dashboard");
}
</script>

<template>
  <div class="min-h-screen bg-[#041628] flex items-center justify-center">
    <div class="art-deco-card p-12 rounded-none max-w-md w-full mx-4">
      <button
        @click="goBack"
        class="text-sm text-gray-400 hover:text-[#fdfc04] mb-8 transition-colors font-sans uppercase tracking-wider flex items-center gap-2"
      >
        <span>←</span> BACK TO DASHBOARD
      </button>

      <h1 class="text-3xl font-bold text-[#fdfc04] mb-8 font-display tracking-widest uppercase text-center border-b border-[#fdfc04] pb-4">
        Create New Room
      </h1>

      <form @submit.prevent="createRoom">
        <div class="mb-8">
          <label class="block text-xs font-bold text-[#fdfc04] mb-2 uppercase tracking-widest">
            Room Name
          </label>
          <input
            v-model="roomName"
            type="text"
            placeholder="E.G., SPRINT 12 ESTIMATION"
            class="w-full art-deco-input py-3 text-lg"
          />
        </div>

        <div v-if="error" class="mb-6 text-sm text-red-500 font-sans border border-red-500 p-2 bg-red-900/20 text-center">
          {{ error }}
        </div>

        <button
          type="submit"
          :disabled="loading"
          class="w-full py-4 art-deco-button primary font-bold tracking-widest disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ loading ? 'CREATING...' : 'CREATE ROOM' }}
        </button>
      </form>
      
      <!-- Corner decorations -->
      <div class="absolute top-0 left-0 w-4 h-4 border-t-2 border-l-2 border-[#fdfc04]"></div>
      <div class="absolute bottom-0 right-0 w-4 h-4 border-b-2 border-r-2 border-[#fdfc04]"></div>
    </div>
  </div>
</template>
