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
    <header class="bg-black border-b border-[#00485c] shadow-[0_0_20px_#00485c33]">
      <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
        <div class="flex items-center">
          <img :src="logo" alt="Parayok" class="h-24" />
        </div>
        <div class="flex items-center gap-4">
          <span v-if="authStore.user" class="text-[#fdfc04]">{{ authStore.user.display_name }}</span>
          <button @click="logout" class="text-sm text-gray-400 hover:text-white transition-colors">Logout</button>
        </div>
      </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-8">
      <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-[#fdfc04]">My Rooms</h2>
        <button
          @click="createRoom"
          class="px-4 py-2 neon-button rounded-lg hover:opacity-90 transition-opacity font-medium text-[#041628]"
        >
          Create Room
        </button>
      </div>

      <div v-if="loading" class="text-center py-8 text-gray-500">Loading...</div>

      <div v-else-if="rooms.length === 0" class="text-center py-8">
        <p class="text-gray-400 mb-4">No rooms yet</p>
        <button
          @click="createRoom"
          class="px-4 py-2 neon-button rounded-lg hover:opacity-90 transition-opacity font-medium text-[#041628]"
        >
          Create your first room
        </button>
      </div>

      <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        <div
          v-for="room in rooms"
          :key="room.id"
          class="bg-black border border-[#00485c] shadow-[0_0_20px_#00485c33] p-6 rounded-lg hover:shadow-[0_0_30px_#00485c66] transition-all cursor-pointer group"
          @click="joinRoom(room.uuid)"
        >
          <div class="flex items-center justify-between mb-2">
            <h3 class="font-semibold text-[#fdfc04] group-hover:text-white transition-colors">{{ room.name }}</h3>
            <span
              :class="{
                'bg-yellow-900/50 text-[#fdfc04] border-[#fdfc04]': room.status === 'active',
                'bg-gray-800/50 text-gray-400 border-gray-500': room.status === 'waiting',
                'bg-blue-900/50 text-blue-400 border-blue-500': room.status === 'completed',
              }"
              class="px-2 py-1 text-xs rounded-full border"
            >
              {{ room.status }}
            </span>
          </div>
          <p class="text-sm text-gray-400">{{ room.participant_count }} participants</p>
        </div>
      </div>
    </main>
  </div>
</template>
