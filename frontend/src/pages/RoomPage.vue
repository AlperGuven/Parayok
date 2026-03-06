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
    router.push("/");
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
    await api.post(`/api/rooms/${room.value.uuid}/issues/${selectedIssue.value.id}/start-voting`);
    await fetchRoom();
  } catch (e) {
    console.error("Failed to start voting:", e);
  }
}

async function revealVotes() {
  if (!selectedIssue.value || !room.value) return;
  try {
    const response = await api.post(`/api/rooms/${room.value.uuid}/issues/${selectedIssue.value.id}/reveal`);
    revealedVotes.value = response.data.votes || [];
    await fetchRoom();
  } catch (e) {
    console.error("Failed to reveal votes:", e);
  }
}

async function resetVoting() {
  if (!selectedIssue.value || !room.value) return;
  try {
    await api.post(`/api/rooms/${room.value.uuid}/issues/${selectedIssue.value.id}/reset`);
    currentVote.value = null;
    await fetchRoom();
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
  <div class="min-h-screen bg-black-50">
    <div v-if="loading" class="flex items-center justify-center min-h-screen">
      <div class="text-gray-500">Loading room...</div>
    </div>

    <div v-else-if="error" class="flex flex-col items-center justify-center min-h-screen">
      <div class="text-red-600 mb-4">{{ error }}</div>
      <button @click="router.push('/dashboard')" class="text-yellow-600 hover:text-yellow-700">
        Back to Dashboard
      </button>
    </div>

    <div v-else class="flex h-screen">
      <aside class="w-80 bg-[#041628] border-r border-gray-200 flex flex-col">
        <div class="p-4 border-b border-gray-200">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="font-bold text-white-900">{{ room?.name }}</h2>
              <p class="text-xs text-white-500 mt-1">Creator: {{ room?.creator_name }}</p>
            </div>
            <button @click="copyRoomLink" class="text-sm text-white-200 hover:text-blue-200">Copy Link</button>
          </div>
        </div>

        <div class="flex-1 overflow-y-auto p-4">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-medium text-white-700">Issues</h3>
            <button
              @click="showAddIssue = true"
              class="text-sm text-[#FFF902] hover:text-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
              :disabled="room?.status === 'completed'"
            >
              + Add
            </button>
          </div>

          <div v-if="showAddIssue" class="mb-4">
            <input
              v-model="newIssueUrl"
              type="text"
              placeholder="Paste Jira URL..."
              class="w-full px-3 py-2 text-sm border border-gray-300 rounded mb-2 text-black"
              @keyup.enter="addIssue"
            />
            <div class="flex gap-2">
              <button
                @click="addIssue"
                class="flex-1 px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700"
              >
                Add
              </button>
              <button @click="showAddIssue = false" class="px-3 py-1 text-sm text-gray-600 hover:text-gray-700">
                Cancel
              </button>
            </div>
          </div>

          <div class="space-y-2">
            <div
              v-for="issue in room?.issues"
              :key="issue.id"
              :class="[
                'p-3 rounded-lg cursor-pointer transition-colors',
                selectedIssue?.id === issue.id ? 'bg-[#111827] border border-[#00fbff]' : 'hover:bg-gray-900',
              ]"
              @click="selectedIssue = issue"
            >
              <div class="flex items-center justify-between">
                <span class="font-medium text-sm text-white">{{ issue.jira_issue_key }}</span>
                <span v-if="issue.final_score" class="text-lg font-bold text-green-400">
                  {{ issue.final_score }}
                </span>
              </div>
              <p class="text-xs text-gray-400 truncate">{{ issue.summary }}</p>
            </div>
          </div>
        </div>

        <div class="p-4 border-t border-[#061733]">
          <h3 class="text-sm font-medium text-gray-300 mb-3">Participants ({{ room?.participants?.length }})</h3>
          <div class="space-y-2 max-h-48 overflow-y-auto pr-1 custom-scrollbar">
            <div v-for="participant in room?.participants" :key="participant.id" class="flex items-center gap-3">
              <div
                class="w-8 h-8 rounded-full bg-[#111827] border border-[#00fbff] flex items-center justify-center text-xs font-medium text-white shrink-0"
              >
                {{ participant.display_name.charAt(0) }}
              </div>
              <span class="text-sm text-gray-300 truncate">{{ participant.display_name }}</span>
            </div>
          </div>
        </div>
      </aside>

      <main class="flex-1 flex flex-col overflow-hidden estimation-part-bg relative">
        <div v-if="selectedIssue" class="flex-1 overflow-y-auto p-6">
          <div class="flex justify-between items-start mb-6">
            <div class="flex-1 mr-4">
              <h2 class="text-2xl font-semibold text-white mb-8">
                {{ selectedIssue.jira_issue_key }}: {{ selectedIssue.summary }}
              </h2>
              <div
                v-if="selectedIssue.description"
                class="text-gray-300 text-base whitespace-pre-wrap bg-gray-800 p-4 rounded-lg leading-relaxed"
              >
                {{ selectedIssue.description }}
              </div>
            </div>
            <button
              @click="router.push('/dashboard')"
              class="px-4 py-2 bg-[#FFF902] text-[#061733] rounded hover:bg-yellow-600 transition-colors shrink-0"
            >
              Go to Dashboard
            </button>
          </div>

          <div class="mb-8">
            <div class="flex items-center justify-between mb-4">
              <h2 class="text-lg font-semibold text-white">Estimation</h2>
              <div class="flex gap-2">
                <button
                  v-if="selectedIssue.status === 'pending'"
                  @click="startVoting"
                  class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
                  :disabled="room?.status === 'completed'"
                >
                  Start Voting
                </button>
                <button
                  v-if="selectedIssue.status === 'voting'"
                  @click="revealVotes"
                  class="px-4 py-2 neon-button rounded-lg hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed"
                  :disabled="room?.status === 'completed'"
                >
                  Reveal Votes
                </button>
                <button
                  v-if="selectedIssue.status === 'revealed'"
                  @click="resetVoting"
                  class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 disabled:opacity-50 disabled:cursor-not-allowed"
                  :disabled="room?.status === 'completed'"
                >
                  Reset
                </button>
              </div>
            </div>

            <div v-if="selectedIssue.status === 'voting'" class="mb-6">
              <p class="text-sm text-gray-400 mb-4">Select your estimate:</p>
              <div class="flex flex-wrap gap-3">
                <button
                  v-for="card in cards"
                  :key="card"
                  @click="castVote(card)"
                  :disabled="room?.status === 'completed'"
                  :class="[
                    'w-16 h-24 rounded-lg flex items-center justify-center text-2xl font-bold transition-all disabled:opacity-50 disabled:cursor-not-allowed',
                    currentVote === card
                      ? 'bg-blue-600 text-white scale-105'
                      : 'bg-white text-black border-2 border-gray-200 hover:border-blue-400 hover:scale-105',
                  ]"
                >
                  {{ card }}
                </button>
              </div>
            </div>

            <div v-if="selectedIssue.status === 'revealed'" class="mt-8">
              <div class="flex justify-center mb-12">
                <div class="bg-gray-800 rounded-xl p-6 text-center shadow-lg border border-gray-700 min-w-[150px]">
                  <p class="text-gray-400 text-lg mb-2">Final Score</p>
                  <p class="text-5xl font-bold text-green-400">
                    {{ selectedIssue.final_score }}
                  </p>
                </div>
              </div>

              <div class="flex flex-wrap gap-6 justify-center">
                <div v-for="card in cards" :key="card" class="flex flex-col items-center gap-3">
                  <div
                    class="w-16 h-24 rounded-lg flex items-center justify-center text-2xl font-bold bg-white text-black border-2 border-gray-200 shadow-md"
                    :class="{ 'opacity-50': getVotesForCard(card).length === 0 }"
                  >
                    {{ card }}
                  </div>

                  <div class="flex -space-x-2 min-h-[32px]">
                    <div
                      v-for="vote in getVotesForCard(card)"
                      :key="vote.user_id"
                      class="w-8 h-8 rounded-full bg-[#00485c] border-2 border-[#00fbff] flex items-center justify-center text-xs font-medium text-white relative group"
                      :title="vote.display_name"
                    >
                      {{ vote.display_name.charAt(0) }}
                      <div
                        class="absolute bottom-full mb-2 hidden group-hover:block bg-black text-white text-xs px-2 py-1 rounded whitespace-nowrap z-10"
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

        <div v-else class="flex-1 flex items-center justify-center text-gray-500">
          Select an issue to start estimating
        </div>

        <button
          v-if="room?.status !== 'completed'"
          @click="finishRoom"
          class="absolute bottom-6 right-6 px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 shadow-lg font-medium"
        >
          Done with voting
        </button>

        <button
          v-if="room?.status === 'completed' && isCreator"
          @click="reopenRoom"
          class="absolute bottom-6 right-6 px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 shadow-lg font-medium"
        >
          Re-open Room
        </button>
      </main>
    </div>
  </div>
</template>
