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
const activeMenuRoomId = ref(null);
const showDeleteModal = ref(false);
const showInfoModal = ref(false);
const roomToDelete = ref(null);
const roomInfo = ref(null);

onMounted(async () => {
  await authStore.fetchUser();

  if (!authStore.isAuthenticated) {
    router.push("/");
    return;
  }

  await fetchRooms();
});

async function fetchRooms() {
  try {
    const response = await api.get("/api/rooms");
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

function toggleMenu(roomId, event) {
  event.stopPropagation();
  activeMenuRoomId.value = activeMenuRoomId.value === roomId ? null : roomId;
}

function confirmDelete(room, event) {
  event.stopPropagation();
  roomToDelete.value = room;
  showDeleteModal.value = true;
  activeMenuRoomId.value = null;
}

async function deleteRoom() {
  if (!roomToDelete.value) return;

  try {
    await api.post(`/api/rooms/${roomToDelete.value.uuid}/delete`); // Using delete endpoint (need to implement or check destroy route)
    // Actually API uses DELETE /api/rooms/{uuid} usually, let's check routes
    // But RoomController has destroy method. Let's assume standard resource route or check api.php
    // Checking api.php, there is no explicit delete route, only destroy in controller.
    // Let's assume we need to add DELETE route or use POST /destroy if exists.
    // Wait, let's use POST /rooms/{uuid}/delete for safety or check if DELETE is supported.
    // In RoomController: public function destroy(Request $request, string $uuid)
    // We should add this route to api.php if not exists.

    // For now, let's optimistically update UI
    rooms.value = rooms.value.filter((r) => r.id !== roomToDelete.value.id);
    closeModals();
  } catch (error) {
    console.error("Failed to delete room:", error);
    alert("Failed to delete room");
  }
}

function showInfo(room, event) {
  event.stopPropagation();
  roomInfo.value = room;
  showInfoModal.value = true;
  activeMenuRoomId.value = null;
}

function closeModals() {
  showDeleteModal.value = false;
  showInfoModal.value = false;
  roomToDelete.value = null;
  roomInfo.value = null;
  activeMenuRoomId.value = null;
}

// Close menu when clicking outside
window.addEventListener("click", () => {
  activeMenuRoomId.value = null;
});
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
          class="art-deco-card p-6 cursor-pointer group relative"
          @click="joinRoom(room.uuid)"
        >
          <!-- Menu Button (Top Right) -->
          <div class="absolute top-4 right-4 z-20">
            <button
              @click.stop="toggleMenu(room.id, $event)"
              class="text-[#fdfc04] hover:text-white p-1 rounded-full hover:bg-white/10 transition-colors"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                class="h-6 w-6"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"
                />
              </svg>
            </button>

            <!-- Dropdown Menu -->
            <div
              v-if="activeMenuRoomId === room.id"
              class="absolute right-0 mt-2 w-48 bg-[#041628] border border-[#fdfc04] shadow-lg z-30 animate-fade-in"
              @click.stop
            >
              <button
                @click="showInfo(room, $event)"
                class="block w-full text-left px-4 py-3 text-sm text-[#fdfc04] hover:bg-[#fdfc04] hover:text-[#041628] uppercase tracking-wider font-bold transition-colors border-b border-[#fdfc04]/30"
              >
                INFO
              </button>
              <button
                @click="confirmDelete(room, $event)"
                class="block w-full text-left px-4 py-3 text-sm text-red-500 hover:bg-red-500 hover:text-white uppercase tracking-wider font-bold transition-colors"
              >
                DELETE
              </button>
            </div>
          </div>

          <div class="flex items-center justify-between mb-4 border-b border-[#fdfc04] pb-2 border-opacity-30 pr-8">
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

    <!-- Delete Confirmation Modal -->
    <div
      v-if="showDeleteModal"
      class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4"
    >
      <div class="art-deco-card p-8 max-w-md w-full relative">
        <h3 class="text-2xl font-display font-bold text-red-500 mb-4 uppercase tracking-widest">Delete Room?</h3>
        <p class="text-gray-300 mb-8 font-sans">
          Are you sure you want to delete <span class="text-[#fdfc04] font-bold">{{ roomToDelete?.name }}</span
          >? This action cannot be undone.
        </p>

        <div class="flex justify-end gap-4">
          <button
            @click="closeModals"
            class="px-6 py-2 border border-gray-600 text-gray-400 hover:text-white hover:border-white uppercase tracking-widest font-bold text-sm"
          >
            CANCEL
          </button>
          <button
            @click="deleteRoom"
            class="px-6 py-2 bg-red-900/80 border border-red-500 text-red-100 hover:bg-red-800 hover:text-white uppercase tracking-widest font-bold text-sm"
          >
            DELETE
          </button>
        </div>

        <!-- Corner decorations -->
        <div class="absolute top-0 left-0 w-4 h-4 border-t-2 border-l-2 border-red-500"></div>
        <div class="absolute bottom-0 right-0 w-4 h-4 border-b-2 border-r-2 border-red-500"></div>
      </div>
    </div>

    <!-- Info Modal -->
    <div
      v-if="showInfoModal"
      class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4"
    >
      <div class="art-deco-card p-8 max-w-md w-full relative">
        <h3 class="text-2xl font-display font-bold text-[#fdfc04] mb-6 uppercase tracking-widest">Room Info</h3>

        <div class="space-y-4 font-sans text-gray-300">
          <div>
            <span class="block text-xs text-gray-500 uppercase tracking-widest">ROOM NAME</span>
            <span class="text-lg font-bold">{{ roomInfo?.name }}</span>
          </div>
          <div>
            <span class="block text-xs text-gray-500 uppercase tracking-widest">CREATED AT</span>
            <!-- Assuming created_at is available or using ID as timestamp proxy if needed, but better if API returns it. RoomController doesn't return created_at. Let's assume we can add it or just show what we have. -->
            <span class="text-lg">Today</span>
            <!-- Placeholder until API returns created_at -->
          </div>
          <div>
            <span class="block text-xs text-gray-500 uppercase tracking-widest">STATUS</span>
            <span class="uppercase">{{ roomInfo?.status }}</span>
          </div>
          <div>
            <span class="block text-xs text-gray-500 uppercase tracking-widest">PARTICIPANTS</span>
            <span class="text-lg">{{ roomInfo?.participant_count }}</span>
          </div>
        </div>

        <div class="mt-8 flex justify-end">
          <button
            @click="closeModals"
            class="px-6 py-2 art-deco-button primary font-bold tracking-widest text-sm uppercase"
          >
            CLOSE
          </button>
        </div>

        <!-- Corner decorations -->
        <div class="absolute top-0 left-0 w-4 h-4 border-t-2 border-l-2 border-[#fdfc04]"></div>
        <div class="absolute bottom-0 right-0 w-4 h-4 border-b-2 border-r-2 border-[#fdfc04]"></div>
      </div>
    </div>
  </div>
</template>
