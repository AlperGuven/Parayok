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
  <div class="min-h-screen app-background flex items-center justify-center">
    <div class="max-w-md w-full p-8 bg-black border border-[#00485c] shadow-[0_0_20px_#00485c33] rounded-lg">
      <button @click="goBack" class="text-sm text-gray-400 hover:text-white mb-4 transition-colors">
        ← Back to Dashboard
      </button>

      <h1 class="text-2xl font-bold text-[#fdfc04] mb-6">Create New Room</h1>

      <form @submit.prevent="createRoom">
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-400 mb-2"> Room Name </label>
          <input
            v-model="roomName"
            type="text"
            placeholder="e.g., Sprint 12 Estimation"
            class="w-full px-4 py-2 border border-gray-600 rounded-lg focus:ring-2 focus:ring-[#00fbff] focus:border-[#00fbff] bg-white text-black"
          />
        </div>

        <div v-if="error" class="mb-4 text-sm text-red-500">
          {{ error }}
        </div>

        <button
          type="submit"
          :disabled="loading"
          class="w-full py-2 neon-button rounded-lg hover:opacity-90 transition-opacity font-medium text-[#041628] disabled:opacity-50"
        >
          {{ loading ? "Creating..." : "Create Room" }}
        </button>
      </form>
    </div>
  </div>
</template>
