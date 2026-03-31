<script setup>
import { ref, onMounted } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "../stores/authStore";
import dashboardService from "../services/DashboardService";
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
const showProfileModal = ref(false);
const newProfileName = ref("");
const isUpdatingProfile = ref(false);

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
    const data = await dashboardService.getRooms();
    rooms.value = data;
  } catch (error) {
    console.error("fetchRooms: Failed to fetch rooms:", error);
    if (error.response?.status === 401) {
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
  await dashboardService.logout();
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
    await dashboardService.deleteRoom(roomToDelete.value.uuid);
    // Optimistically update UI
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
  showProfileModal.value = false;
  roomToDelete.value = null;
  roomInfo.value = null;
  activeMenuRoomId.value = null;
}

function openProfileModal() {
  newProfileName.value = authStore.user?.display_name || "";
  showProfileModal.value = true;
}

async function updateProfile() {
  if (!newProfileName.value.trim() || isUpdatingProfile.value) return;

  isUpdatingProfile.value = true;
  try {
    const data = await dashboardService.updateProfile(newProfileName.value.trim());
    authStore.user = data.user;
    closeModals();
  } catch (error) {
    console.error("Failed to update profile:", error);
  } finally {
    isUpdatingProfile.value = false;
  }
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
        <div class="flex items-center gap-6">
          <button
            v-if="authStore.user"
            @click="openProfileModal"
            class="group flex items-center gap-2 text-[#fdfc04] hover:text-white transition-colors"
          >
            <span class="font-display tracking-widest uppercase">{{ authStore.user.display_name }}</span>
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="h-4 w-4 opacity-50 group-hover:opacity-100"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
              />
            </svg>
          </button>
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
    <!-- Profile Edit Modal -->
    <div
      v-if="showProfileModal"
      class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4"
    >
      <div class="art-deco-card p-8 max-w-md w-full relative">
        <h3 class="text-2xl font-display font-bold text-[#fdfc04] mb-6 uppercase tracking-widest">Edit Profile</h3>

        <div class="mb-8">
          <label class="block text-xs text-gray-500 uppercase tracking-widest mb-2">DISPLAY NAME</label>
          <input
            v-model="newProfileName"
            type="text"
            class="w-full art-deco-input text-lg"
            @keyup.enter="updateProfile"
            autofocus
          />
        </div>

        <div class="flex justify-end gap-4">
          <button
            @click="closeModals"
            class="px-6 py-2 border border-gray-600 text-gray-400 hover:text-white hover:border-white uppercase tracking-widest font-bold text-sm"
          >
            CANCEL
          </button>
          <button
            @click="updateProfile"
            :disabled="isUpdatingProfile || !newProfileName.trim()"
            class="px-6 py-2 art-deco-button primary uppercase tracking-widest font-bold text-sm disabled:opacity-50"
          >
            {{ isUpdatingProfile ? "SAVING..." : "SAVE" }}
          </button>
        </div>

        <!-- Corner decorations -->
        <div class="absolute top-0 left-0 w-4 h-4 border-t-2 border-l-2 border-[#fdfc04]"></div>
        <div class="absolute bottom-0 right-0 w-4 h-4 border-b-2 border-r-2 border-[#fdfc04]"></div>
      </div>
    </div>
  </div>
</template>
