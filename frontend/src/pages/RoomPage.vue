<script setup>
import { ref, onMounted, onUnmounted, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuthStore } from "@/stores/authStore";
import { useEcho } from "@/composables/useEcho";
import api from "@/services/api";

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const { connect, channel, isConnected } = useEcho();

const room = ref(null);
const selectedIssue = ref(null);
const loading = ref(true);
const error = ref("");
const currentVote = ref(null);
const showAddIssue = ref(false);
const newIssueUrl = ref("");
const votedUsers = ref([]);
const revealedVotes = ref([]);

const cards = ["1", "2", "3", "5", "8", "13", "21", "?", "☕"];

onMounted(async () => {
  await authStore.fetchUser();
  if (!authStore.isAuthenticated) {
    router.push({ path: "/", query: { redirect: route.fullPath } });
    return;
  }
  await fetchRoom();
  setupEcho();
});

onUnmounted(() => {
  if (window.echoInstance) {
    window.echoInstance.disconnect();
  }
});

function setupEcho() {
  if (!room.value) return;

  const echoInstance = connect();
  window.echoInstance = echoInstance;

  const roomChannel = channel(room.value.id);

  roomChannel.listen("vote.cast", (data) => {
    if (data.issue_id === selectedIssue.value?.id) {
      if (data.has_voted && !votedUsers.value.find((u) => u.user_id === data.user_id)) {
        votedUsers.value.push({
          user_id: data.user_id,
          display_name: data.display_name,
          has_voted: true,
        });
      }
    }
  });

  roomChannel.listen("voting.started", (data) => {
    if (selectedIssue.value && data.issue_id === selectedIssue.value.id) {
      selectedIssue.value.status = "voting";
      votedUsers.value = [];
    }
  });

  roomChannel.listen("votes.revealed", (data) => {
    if (selectedIssue.value && data.issue_id === selectedIssue.value.id) {
      selectedIssue.value.status = "revealed";
      selectedIssue.value.final_score = data.final_score;
      revealedVotes.value = data.votes || [];
    }
  });

  roomChannel.listen("voting.reset", (data) => {
    if (selectedIssue.value && data.issue_id === selectedIssue.value.id) {
      selectedIssue.value.status = "pending";
      selectedIssue.value.final_score = null;
      votedUsers.value = [];
      revealedVotes.value = [];
      currentVote.value = null;
    }
  });

  roomChannel.listen("issue.added", (data) => {
    room.value.issues.push({
      id: data.id,
      jira_issue_key: data.jira_issue_key,
      summary: data.summary,
      jira_url: data.jira_url,
      status: "pending",
      final_score: null,
    });
  });

  roomChannel.listen("participant.joined", (data) => {
    const exists = room.value.participants.find((p) => p.user_id === data.user_id);
    if (!exists) {
      room.value.participants.push({
        id: Date.now(),
        user_id: data.user_id,
        display_name: data.display_name,
        avatar_url: data.avatar_url,
        role: data.role,
        is_online: true,
      });
    }
  });
}

async function fetchRoom() {
  try {
    const response = await api.get(`/api/rooms/${route.params.uuid}`);
    room.value = response.data;
    if (room.value.issues.length > 0) {
      selectedIssue.value = room.value.issues[0];
    }
  } catch (e) {
    error.value = e.response?.data?.message || "Failed to load room";
  } finally {
    loading.value = false;
  }
}

async function castVote(value) {
  if (!selectedIssue.value || !room.value) return;
  currentVote.value = value;
  try {
    await api.post(`/api/rooms/${room.value.uuid}/issues/${selectedIssue.value.id}/vote`, { value });
  } catch (e) {
    console.error("Failed to cast vote:", e);
  }
}

async function startVoting() {
  if (!selectedIssue.value || !room.value) return;
  try {
    const issueId = selectedIssue.value.id; // Store current issue ID
    await api.post(`/api/rooms/${room.value.uuid}/issues/${issueId}/start-voting`);
    await fetchRoom();
    
    // Restore selected issue
    const updatedIssue = room.value.issues.find(i => i.id === issueId);
    if (updatedIssue) {
      selectedIssue.value = updatedIssue;
    }
  } catch (e) {
    console.error("Failed to start voting:", e);
  }
}

async function revealVotes() {
  if (!selectedIssue.value || !room.value) return;
  try {
    const issueId = selectedIssue.value.id; // Store current issue ID
    const response = await api.post(`/api/rooms/${room.value.uuid}/issues/${issueId}/reveal`);
    revealedVotes.value = response.data.votes || [];
    await fetchRoom();
    
    // Restore selected issue
    const updatedIssue = room.value.issues.find(i => i.id === issueId);
    if (updatedIssue) {
      selectedIssue.value = updatedIssue;
    }
  } catch (e) {
    console.error("Failed to reveal votes:", e);
  }
}

async function resetVoting() {
  if (!selectedIssue.value || !room.value) return;
  try {
    const issueId = selectedIssue.value.id; // Store current issue ID
    await api.post(`/api/rooms/${room.value.uuid}/issues/${issueId}/reset`);
    currentVote.value = null;
    await fetchRoom();
    
    // Restore selected issue
    const updatedIssue = room.value.issues.find(i => i.id === issueId);
    if (updatedIssue) {
      selectedIssue.value = updatedIssue;
    }
  } catch (e) {
    console.error("Failed to reset voting:", e);
  }
}

async function addIssue() {
  if (!newIssueUrl.value.trim() || !room.value) return;
  try {
    await api.post(`/api/rooms/${room.value.uuid}/issues/from-url`, { url: newIssueUrl.value });
    showAddIssue.value = false;
    newIssueUrl.value = "";
    await fetchRoom();
  } catch (e) {
    error.value = e.response?.data?.message || "Failed to add issue";
  }
}

async function finishRoom() {
  if (!room.value) return;
  try {
    await api.post(`/api/rooms/${room.value.uuid}/complete`);
    router.push("/dashboard");
  } catch (e) {
    console.error("Failed to finish room:", e);
  }
}

function copyRoomLink() {
  if (!room.value) return;
  const link = `${window.location.origin}/room/${room.value.uuid}`;
  navigator.clipboard.writeText(link);
}

function getVotesForCard(card) {
  return revealedVotes.value.filter((v) => v.value === card);
}

const isCreator = computed(() => {
  return room.value && authStore.user && room.value.created_by === authStore.user.id;
});

const isGuest = computed(() => {
  return authStore.user && authStore.user.is_guest;
});

async function reopenRoom() {
  if (!room.value) return;
  try {
    await api.post(`/api/rooms/${room.value.uuid}/reopen`);
    await fetchRoom();
  } catch (e) {
    console.error("Failed to reopen room:", e);
  }
}
</script>

<template>
  <div class="min-h-screen bg-[#041628]">
    <div v-if="loading" class="flex items-center justify-center min-h-screen">
      <div class="text-[#fdfc04] font-display tracking-widest animate-pulse">LOADING ROOM...</div>
    </div>

    <div v-else-if="error" class="flex flex-col items-center justify-center min-h-screen">
      <div class="text-red-500 mb-4 font-sans">{{ error }}</div>
      <button
        @click="router.push('/dashboard')"
        class="text-[#fdfc04] hover:text-white transition-colors font-sans uppercase tracking-wider"
      >
        BACK TO DASHBOARD
      </button>
    </div>

    <div v-else class="flex h-screen">
      <aside class="w-80 bg-black border-r border-[#fdfc04] flex flex-col relative z-10 shadow-glow-gold">
        <div class="p-6 border-b border-[#fdfc04] border-opacity-30">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="font-display font-bold text-xl text-[#fdfc04] tracking-widest uppercase">{{ room?.name }}</h2>
              <p class="text-xs text-gray-400 mt-2 font-sans uppercase tracking-wider">
                CREATOR: <span class="text-white">{{ room?.creator_name }}</span>
              </p>
            </div>
            <button
              @click="copyRoomLink"
              class="text-xs text-gray-400 hover:text-[#fdfc04] transition-colors uppercase tracking-wider"
            >
              COPY LINK
            </button>
          </div>
        </div>

        <div class="flex-1 overflow-y-auto p-6 custom-scrollbar">
          <div class="flex items-center justify-between mb-6">
            <h3 class="text-sm font-bold text-[#fdfc04] uppercase tracking-widest">ISSUES</h3>
            <button
              @click="showAddIssue = true"
              class="text-md text-[#00fbff] hover:text-white disabled:opacity-50 disabled:cursor-not-allowed uppercase tracking-wider font-bold"
              :disabled="room?.status === 'completed'"
              v-if="!isGuest"
            >
              + ADD
            </button>
          </div>

          <div v-if="showAddIssue" class="mb-6 art-deco-card p-4">
            <input
              v-model="newIssueUrl"
              type="text"
              placeholder="PASTE JIRA URL..."
              class="w-full art-deco-input mb-4 text-sm"
              @keyup.enter="addIssue"
            />
            <div class="flex gap-2">
              <button @click="addIssue" class="flex-1 py-2 art-deco-button primary text-xs font-bold">ADD</button>
              <button
                @click="showAddIssue = false"
                class="px-3 py-2 text-xs text-gray-400 hover:text-white uppercase tracking-wider"
              >
                CANCEL
              </button>
            </div>
          </div>

          <div class="space-y-3">
            <div
              v-for="issue in room?.issues"
              :key="issue.id"
              :class="[
                'p-4 border transition-all duration-300 relative group',
                selectedIssue?.id === issue.id
                  ? 'bg-black border-[#fdfc04] shadow-[0_0_10px_rgba(253,252,4,0.2)]'
                  : 'bg-transparent border-gray-800 hover:border-[#fdfc04] hover:border-opacity-50',
              ]"
              @click="selectedIssue = issue"
            >
              <div class="flex items-center justify-between mb-1">
                <span class="font-display font-bold text-sm text-white tracking-wide">{{ issue.jira_issue_key }}</span>
                <span v-if="issue.final_score" class="text-lg font-bold text-[#00fbff] font-display">
                  {{ issue.final_score }}
                </span>
              </div>
              <p class="text-xs text-gray-400 truncate font-sans">{{ issue.summary }}</p>

              <!-- Selection indicator -->
              <div v-if="selectedIssue?.id === issue.id" class="absolute left-0 top-0 bottom-0 w-1 bg-[#fdfc04]"></div>
            </div>
          </div>
        </div>

        <div class="p-6 border-t border-[#fdfc04] border-opacity-30 bg-black">
          <h3 class="text-sm font-bold text-[#fdfc04] mb-4 uppercase tracking-widest">
            PARTICIPANTS ({{ room?.participants?.length }})
          </h3>
          <div class="space-y-3 max-h-48 overflow-y-auto pr-1 custom-scrollbar">
            <div v-for="participant in room?.participants" :key="participant.id" class="flex items-center gap-3">
              <div
                class="w-8 h-8 flex items-center justify-center text-xs font-bold text-[#041628] bg-[#00fbff] border border-[#00fbff] shadow-[0_0_10px_rgba(0,251,255,0.3)] transform rotate-45"
              >
                <span class="transform -rotate-45">{{ participant.display_name.charAt(0) }}</span>
              </div>
              <span class="text-sm text-gray-300 truncate font-sans tracking-wide uppercase">{{
                participant.display_name
              }}</span>
            </div>
          </div>
        </div>
      </aside>

      <main class="flex-1 flex flex-col overflow-hidden bg-[#041628] relative">
        <!-- Background Pattern -->
        <div
          class="absolute inset-0 opacity-5 pointer-events-none"
          style="
            background-image: repeating-linear-gradient(45deg, #fdfc04 0, #fdfc04 1px, transparent 0, transparent 50%);
            background-size: 20px 20px;
          "
        ></div>

        <div v-if="selectedIssue" class="flex-1 overflow-y-auto p-8 relative z-10 custom-scrollbar">
          <div class="flex justify-between items-start mb-8 border-b border-[#fdfc04] border-opacity-30 pb-6">
            <div class="flex-1 mr-8">
              <h2 class="text-3xl font-display font-bold text-white mb-4 tracking-widest uppercase">
                <span class="text-[#fdfc04] mr-2">{{ selectedIssue.jira_issue_key }}:</span> {{ selectedIssue.summary }}
              </h2>
              <div
                v-if="selectedIssue.description"
                class="text-gray-300 text-lg font-sans whitespace-pre-wrap bg-black border border-gray-800 p-6 relative leading-relaxed"
              >
                <!-- Corner decorations for description -->
                <div class="absolute top-0 left-0 w-3 h-3 border-t border-l border-[#fdfc04] opacity-50"></div>
                <div class="absolute bottom-0 right-0 w-3 h-3 border-b border-r border-[#fdfc04] opacity-50"></div>
                {{ selectedIssue.description }}
              </div>
            </div>
            <button
              @click="router.push('/dashboard')"
              class="px-4 py-2 text-xs font-bold text-gray-400 hover:text-[#fdfc04] border border-gray-700 hover:border-[#fdfc04] transition-all uppercase tracking-widest"
            >
              EXIT ROOM
            </button>
          </div>

          <div class="mb-12">
            <div class="flex items-center justify-between mb-8">
              <h3 class="text-xl font-display font-bold text-[#fdfc04] uppercase tracking-widest">Estimation</h3>
              <div class="flex gap-4" v-if="!isGuest">
                <button
                  v-if="selectedIssue.status === 'pending'"
                  @click="startVoting"
                  class="px-6 py-2 art-deco-button primary font-bold tracking-widest text-sm disabled:opacity-50"
                  :disabled="room?.status === 'completed'"
                >
                  START VOTING
                </button>
                <button
                  v-if="selectedIssue.status === 'voting'"
                  @click="revealVotes"
                  class="px-6 py-2 art-deco-button font-bold tracking-widest text-sm disabled:opacity-50"
                  :disabled="room?.status === 'completed'"
                >
                  REVEAL VOTES
                </button>
                <button
                  v-if="selectedIssue.status === 'revealed'"
                  @click="resetVoting"
                  class="px-6 py-2 border border-gray-600 text-gray-400 hover:text-white hover:border-white transition-colors font-bold tracking-widest text-sm uppercase disabled:opacity-50"
                  :disabled="room?.status === 'completed'"
                >
                  RESET
                </button>
              </div>
            </div>

            <div v-if="selectedIssue.status === 'voting'" class="mb-8">
              <p class="text-sm text-gray-400 mb-6 font-sans uppercase tracking-wider">SELECT YOUR ESTIMATE</p>
              <div class="flex flex-wrap gap-4 justify-center">
                <button
                  v-for="card in cards"
                  :key="card"
                  @click="castVote(card)"
                  :disabled="room?.status === 'completed'"
                  :class="[
                    'w-20 h-32 flex items-center justify-center text-3xl font-display font-bold transition-all duration-300 relative group disabled:opacity-50',
                    currentVote === card
                      ? 'text-[#041628] bg-[#fdfc04] shadow-[0_0_20px_rgba(253,252,4,0.4)] -translate-y-2'
                      : 'text-[#fdfc04] bg-black border border-[#fdfc04] hover:-translate-y-1 hover:shadow-[0_0_15px_rgba(253,252,4,0.2)]',
                  ]"
                >
                  {{ card }}
                  <!-- Card decoration -->
                  <div
                    v-if="currentVote !== card"
                    class="absolute top-1 left-1 w-2 h-2 border-t border-l border-[#fdfc04] opacity-50"
                  ></div>
                  <div
                    v-if="currentVote !== card"
                    class="absolute bottom-1 right-1 w-2 h-2 border-b border-r border-[#fdfc04] opacity-50"
                  ></div>
                </button>
              </div>
            </div>

            <div v-if="selectedIssue.status === 'revealed'" class="mt-8">
              <div class="flex justify-center mb-16">
                <div class="art-deco-card p-10 text-center min-w-[200px]">
                  <p class="text-gray-400 text-sm mb-4 uppercase tracking-widest font-sans">FINAL SCORE</p>
                  <p class="text-7xl font-bold text-[#00fbff] font-display drop-shadow-[0_0_10px_rgba(0,251,255,0.5)]">
                    {{ selectedIssue.final_score }}
                  </p>
                </div>
              </div>

              <div class="flex flex-wrap gap-8 justify-center">
                <div v-for="card in cards" :key="card" class="flex flex-col items-center gap-4">
                  <div
                    class="w-16 h-24 flex items-center justify-center text-2xl font-display font-bold bg-black border border-[#fdfc04] text-[#fdfc04]"
                    :class="{ 'opacity-30 border-gray-800 text-gray-800': getVotesForCard(card).length === 0 }"
                  >
                    {{ card }}
                  </div>

                  <div class="flex -space-x-3 min-h-[32px]">
                    <div
                      v-for="vote in getVotesForCard(card)"
                      :key="vote.user_id"
                      class="w-10 h-10 flex items-center justify-center text-xs font-bold text-[#041628] bg-[#00fbff] border-2 border-black shadow-lg transform rotate-45 relative group z-10 hover:z-20 hover:scale-110 transition-transform"
                    >
                      <span class="transform -rotate-45">{{ vote.display_name.charAt(0) }}</span>
                      <!-- Tooltip -->
                      <div
                        class="absolute bottom-full mb-2 hidden group-hover:block bg-black border border-[#fdfc04] text-[#fdfc04] text-xs px-2 py-1 whitespace-nowrap transform -rotate-45 origin-bottom-left z-30 uppercase tracking-wider"
                      >
                        {{ vote.display_name }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-else class="flex-1 flex flex-col items-center justify-center text-gray-500 relative z-10">
          <div class="w-16 h-16 border border-gray-700 rotate-45 flex items-center justify-center mb-4">
            <div class="w-12 h-12 border border-gray-700"></div>
          </div>
          <p class="font-display tracking-widest uppercase mb-8">SELECT AN ISSUE TO START</p>
          
          <button
            @click="router.push('/dashboard')"
            class="px-6 py-3 border border-gray-700 text-gray-400 hover:text-[#fdfc04] hover:border-[#fdfc04] transition-all uppercase tracking-widest font-sans font-bold"
          >
            EXIT ROOM
          </button>
        </div>

        <button
          v-if="room?.status !== 'completed' && !isGuest"
          @click="finishRoom"
          class="absolute bottom-8 right-8 px-8 py-4 bg-red-900/80 border border-red-500 text-red-100 hover:bg-red-800 hover:text-white shadow-lg font-bold tracking-widest uppercase font-display transition-all z-20"
        >
          DONE WITH VOTING
        </button>

        <button
          v-if="room?.status === 'completed' && isCreator"
          @click="reopenRoom"
          class="absolute bottom-8 right-8 px-8 py-4 bg-green-900/80 border border-green-500 text-green-100 hover:bg-green-800 hover:text-white shadow-lg font-bold tracking-widest uppercase font-display transition-all z-20"
        >
          RE-OPEN ROOM
        </button>
      </main>
    </div>
  </div>
</template>
