<script setup>
import { ref, onMounted } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "../stores/authStore";
import api from "../services/api";
import logo from "@/assets/images/parayok.png";

const router = useRouter();
const authStore = useAuthStore();

const rooms = ref([]);
const loading = ref(true);

onMounted(async () => {
  console.log("Dashboard: fetching user...");
  await authStore.fetchUser();
  console.log("Dashboard: user fetched", authStore.user);
  console.log("Dashboard: isAuthenticated", authStore.isAuthenticated);

  if (!authStore.isAuthenticated) {
    console.log("Dashboard: not authenticated, redirecting to login");
    router.push("/");
    return;
  }

  console.log("Dashboard: authenticated, fetching rooms...");
  await fetchRooms();

  console.log("Dashboard: rooms fetched, rendering...");
});

async function fetchRooms() {
  try {
    console.log("fetchRooms: making API call...");
    const response = await api.get("/api/rooms");
    console.log("fetchRooms: response:", response.data);
    rooms.value = response.data;
  } catch (error) {
    console.error("fetchRooms: Failed to fetch rooms:", error);
    if (error.response?.status === 401) {
      console.log("fetchRooms: 401 - clearing user and redirecting");
      authStore.logout();
      router.push("/");
      return;
    }
  } finally {
    loading.value = false;
  }
}

function createRoom() {
  router.push("/room/create");
}

function joinRoom(uuid) {
  router.push(`/room/${uuid}`);
}

async function logout() {
  await api.post("/api/auth/logout");
  authStore.logout();
  router.push("/");
}
</script>

<template>
  <div class="min-h-screen app-background">
    <header class="bg-black border-b border-[#fdfc04] shadow-glow-gold relative z-10">
      <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
        <div class="flex items-center">
          <img :src="logo" alt="Parayok" class="h-24" />
        </div>
        <div class="flex items-center gap-4">
          <span v-if="authStore.user" class="text-[#fdfc04] font-display tracking-widest uppercase">{{
            authStore.user.display_name
          }}</span>
          <button
            @click="logout"
            class="text-sm text-gray-400 hover:text-white transition-colors font-sans uppercase tracking-wider"
          >
            Logout
          </button>
        </div>
      </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-12">
      <div class="flex items-center justify-between mb-8 border-b border-[#fdfc04] pb-4">
        <h2 class="text-3xl font-display font-bold text-[#fdfc04] tracking-widest uppercase">My Rooms</h2>
        <button @click="createRoom" class="px-6 py-3 art-deco-button primary font-bold tracking-widest">
          CREATE ROOM
        </button>
      </div>

      <div v-if="loading" class="text-center py-8 text-[#fdfc04] font-display tracking-widest animate-pulse">
        LOADING...
      </div>

      <div v-else-if="rooms.length === 0" class="text-center py-16 art-deco-card p-8">
        <p class="text-gray-400 mb-6 font-sans text-lg">No rooms yet</p>
        <button @click="createRoom" class="px-6 py-3 art-deco-button primary font-bold tracking-widest">
          CREATE YOUR FIRST ROOM
        </button>
      </div>

      <div v-else class="grid gap-8 md:grid-cols-2 lg:grid-cols-3">
        <div
          v-for="room in rooms"
          :key="room.id"
          class="art-deco-card p-6 cursor-pointer group"
          @click="joinRoom(room.uuid)"
        >
          <div class="flex items-center justify-between mb-4 border-b border-[#fdfc04] pb-2 border-opacity-30">
            <h3
              class="font-display font-bold text-xl text-[#fdfc04] group-hover:text-white transition-colors tracking-wide truncate pr-2"
            >
              {{ room.name }}
            </h3>
            <span
              :class="{
                'bg-yellow-900/50 text-[#fdfc04] border-[#fdfc04]': room.status === 'active',
                'bg-gray-800/50 text-gray-400 border-gray-500': room.status === 'waiting',
                'bg-blue-900/50 text-blue-400 border-blue-500': room.status === 'completed',
              }"
              class="px-2 py-1 text-xs font-sans uppercase tracking-wider border"
            >
              {{ room.status }}
            </span>
          </div>
          <p class="text-sm text-gray-400 font-sans">{{ room.participant_count }} participants</p>

          <!-- Corner decorations -->
          <div class="absolute top-0 left-0 w-2 h-2 border-t border-l border-[#fdfc04]"></div>
          <div class="absolute bottom-0 right-0 w-2 h-2 border-b border-r border-[#fdfc04]"></div>
        </div>
      </div>
    </main>
  </div>
</template>
