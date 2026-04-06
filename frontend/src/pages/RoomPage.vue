<script setup>
import { ref, onMounted, onUnmounted, computed, triggerRef, watch, nextTick } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuthStore } from "@/stores/authStore";
import { useEcho } from "@/composables/useEcho";
import roomService from "@/services/RoomService";
import iceBreakerQuestions from "@/assets/questions/questions.json";

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
const revealedVotes = ref([]);
const isCopied = ref(false);
const isEditingScore = ref(false);
const editedScore = ref("");
const isSavingToJira = ref(false);
const isSavedToJira = ref(false);
const currentIceBreaker = ref("");
const issuesListRef = ref(null);

const scrollToBottom = async () => {
  await nextTick();
  if (issuesListRef.value) {
    issuesListRef.value.scrollTop = issuesListRef.value.scrollHeight;
  }
};

const cards = ["1", "2", "3", "5", "8", "13", "21", "?", "☕"];

async function pickRandomIceBreaker() {
  if (iceBreakerQuestions.length > 0 && room.value && isCreator.value) {
    const randomIndex = Math.floor(Math.random() * iceBreakerQuestions.length);
    const newQuestion = iceBreakerQuestions[randomIndex];
    currentIceBreaker.value = newQuestion;

    try {
      await roomService.updateIceBreaker(room.value.uuid, newQuestion);
    } catch (e) {
      console.error("Failed to sync ice breaker:", e);
    }
  }
}

onMounted(async () => {
  await authStore.fetchUser();
  if (!authStore.isAuthenticated) {
    router.push({ path: "/", query: { redirect: route.fullPath } });
    return;
  }
  await fetchRoom();

  if (room.value) {
    if (room.value.current_ice_breaker) {
      currentIceBreaker.value = room.value.current_ice_breaker;
    } else if (isCreator.value) {
      await pickRandomIceBreaker();
    }
  }

  setupEcho();
});

onUnmounted(() => {
  if (window.echoInstance) {
    if (room.value) {
      window.echoInstance.leave("room." + room.value.id);
    }
    // Do not disconnect global echo instance as it might be used elsewhere
    // window.echoInstance.disconnect();
  }
});

function setupEcho() {
  if (!room.value) return;

  const echoInstance = connect();
  window.echoInstance = echoInstance;

  const channelName = "room." + room.value.id;

  // Clean up existing listeners if any
  echoInstance.leave(channelName);

  const roomChannel = echoInstance.join(channelName);

  roomChannel
    .here((users) => {
      if (room.value && room.value.participants) {
        // Reset online status first
        room.value.participants.forEach((p) => (p.is_online = false));

        users.forEach((user) => {
          // Use loose equality for ID matching
          const participant = room.value.participants.find((p) => p.user_id == user.id);
          if (participant) {
            participant.is_online = true;
          } else {
            // New participant found in 'here' list (should ideally be in DB, but add if missing)
            room.value.participants.push({
              id: Date.now() + Math.random(), // Unique temp ID
              user_id: user.id,
              display_name: user.display_name,
              avatar_url: user.avatar_url,
              role: user.role,
              is_online: true,
            });
          }
        });
        triggerRef(room);
      }
    })
    .joining((user) => {
      if (!room.value || !room.value.participants) return;

      const participantIndex = room.value.participants.findIndex((p) => p.user_id == user.id);

      if (participantIndex !== -1) {
        // Update existing participant
        room.value.participants[participantIndex].is_online = true;
        room.value.participants[participantIndex].display_name = user.display_name; // Update name if changed
      } else {
        // Add new participant
        room.value.participants.push({
          id: Date.now(),
          user_id: user.id,
          display_name: user.display_name,
          avatar_url: user.avatar_url,
          role: user.role,
          is_online: true,
        });
      }
      triggerRef(room);
    })
    .leaving((user) => {
      if (!room.value || !room.value.participants) return;

      // Remove from list or mark offline
      // For now, let's remove them to keep the list clean
      room.value.participants = room.value.participants.filter((p) => p.user_id != user.id);
      triggerRef(room);
    })
    .listen(".vote.cast", (data) => {
      if (data.issue_id == selectedIssue.value?.id) {
        if (!selectedIssue.value.voters) {
          selectedIssue.value.voters = [];
        }

        // Remove existing vote if user changed their mind
        const existingIndex = selectedIssue.value.voters.findIndex((u) => u.user_id == data.user_id);
        if (existingIndex !== -1) {
          selectedIssue.value.voters.splice(existingIndex, 1);
        }

        if (data.has_voted) {
          selectedIssue.value.voters.push({
            user_id: data.user_id,
            display_name: data.display_name,
            avatar_url: data.avatar_url,
            has_voted: true,
          });
        }
        triggerRef(selectedIssue);
      }
    })
    .listen(".voting.started", (data) => {
      // Automatically switch to the issue being voted on if not selected
      if (!selectedIssue.value || selectedIssue.value.id != data.issue_id) {
        const issueToSelect = room.value.issues.find((i) => i.id == data.issue_id);
        if (issueToSelect) {
          selectedIssue.value = issueToSelect;
        }
      }

      if (selectedIssue.value && selectedIssue.value.id == data.issue_id) {
        selectedIssue.value.status = "voting";
        selectedIssue.value.voters = [];
        revealedVotes.value = [];
        currentVote.value = null;
        triggerRef(selectedIssue);
      }
    })
    .listen(".votes.revealed", (data) => {
      if (selectedIssue.value && data.issue_id == selectedIssue.value.id) {
        selectedIssue.value.status = "revealed";
        // Parse float and check if it's a whole number to remove decimal
        let score = data.final_score;
        if (score !== null) {
          const parsed = parseFloat(score);
          score = Number.isInteger(parsed) ? parsed : parsed.toFixed(1);
        }
        selectedIssue.value.final_score = score;
        revealedVotes.value = data.votes || [];
        isEditingScore.value = false;
      }
    })
    .listen(".voting.reset", (data) => {
      if (selectedIssue.value && data.issue_id == selectedIssue.value.id) {
        selectedIssue.value.status = "pending";
        selectedIssue.value.final_score = null;
        selectedIssue.value.voters = [];
        revealedVotes.value = [];
        currentVote.value = null;
        isEditingScore.value = false;
        triggerRef(selectedIssue);
      }
    })
    .listen(".issue.added", (data) => {
      console.log("Issue Added Event:", data); // DEBUG
      if (!room.value || !room.value.issues) return;

      const exists = room.value.issues.find((i) => i.id == data.id);
      if (!exists) {
        room.value.issues.push({
          id: data.id,
          jira_issue_key: data.jira_issue_key,
          summary: data.summary,
          description: data.description, // Ensure this is present
          jira_url: data.jira_url,
          status: "pending",
          final_score: null,
        });
        triggerRef(room);
        scrollToBottom();
      }
    })
    .listen(".room.deleted", () => {
      alert("This room has been deleted by the moderator.");
      exitRoom();
    })
    .listen(".ice.breaker.updated", (data) => {
      if (data.ice_breaker) {
        currentIceBreaker.value = data.ice_breaker;
      }
    });
}

async function fetchRoom() {
  try {
    const data = await roomService.getRoom(route.params.uuid);
    room.value = data;

    if (room.value.issues.length > 0) {
      // Find if any issue is currently in voting status
      const activeIssue = room.value.issues.find((i) => i.status === "voting");

      // Format final scores for all issues
      room.value.issues.forEach((issue) => {
        if (issue.final_score !== null) {
          const parsed = parseFloat(issue.final_score);
          issue.final_score = Number.isInteger(parsed) ? parsed : parsed.toFixed(1);
        }
      });

      if (activeIssue) {
        selectedIssue.value = activeIssue;
      } else {
        selectedIssue.value = room.value.issues[0];
      }
    } else {
      // Empty room logic - make sure ice breaker is set if available
      if (room.value.current_ice_breaker) {
        currentIceBreaker.value = room.value.current_ice_breaker;
      }
    }
  } catch (e) {
    // If user is not a participant, try to join
    if (e.response?.status === 403 && e.response?.data?.message === "You are not a participant of this room") {
      try {
        await roomService.joinRoom(route.params.uuid);
        // Retry fetching room after joining
        const data = await roomService.getRoom(route.params.uuid);
        room.value = data;
        if (room.value.issues.length > 0) {
          room.value.issues.forEach((issue) => {
            if (issue.final_score !== null) {
              const parsed = parseFloat(issue.final_score);
              issue.final_score = Number.isInteger(parsed) ? parsed : parsed.toFixed(1);
            }
          });

          const activeIssue = room.value.issues.find((i) => i.status === "voting");
          if (activeIssue) {
            selectedIssue.value = activeIssue;
          } else {
            selectedIssue.value = room.value.issues[0];
          }
        } else {
          if (room.value.current_ice_breaker) {
            currentIceBreaker.value = room.value.current_ice_breaker;
          }
        }
        setupEcho();
        return;
      } catch (joinError) {
        console.error("Failed to join room:", joinError);
        error.value = "Failed to join room";
      }
    } else {
      error.value = e.response?.data?.message || "Failed to load room";
    }
  } finally {
    loading.value = false;
  }
}

async function castVote(value) {
  if (!selectedIssue.value || !room.value) return;

  if (currentVote.value === value) {
    // Un-vote
    currentVote.value = null;
    try {
      await roomService.castVote(room.value.uuid, selectedIssue.value.id, null);
    } catch (e) {
      console.error("Failed to un-vote:", e);
    }
  } else {
    // Cast new vote
    currentVote.value = value;
    try {
      await roomService.castVote(room.value.uuid, selectedIssue.value.id, value);
    } catch (e) {
      console.error("Failed to cast vote:", e);
    }
  }
}

async function startVoting() {
  if (!selectedIssue.value || !room.value) return;
  try {
    const issueId = selectedIssue.value.id; // Store current issue ID
    await roomService.startVoting(room.value.uuid, issueId);
    await fetchRoom();

    // Restore selected issue
    const updatedIssue = room.value.issues.find((i) => i.id === issueId);
    if (updatedIssue) {
      selectedIssue.value = updatedIssue;
    }
    isEditingScore.value = false;
  } catch (e) {
    console.error("Failed to start voting:", e);
  }
}

async function revealVotes() {
  if (!selectedIssue.value || !room.value) return;
  try {
    const issueId = selectedIssue.value.id; // Store current issue ID
    const data = await roomService.revealVotes(room.value.uuid, issueId);
    revealedVotes.value = data.votes || [];
    await fetchRoom();

    // Restore selected issue
    const updatedIssue = room.value.issues.find((i) => i.id === issueId);
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
    await roomService.resetVoting(room.value.uuid, issueId);
    currentVote.value = null;
    isEditingScore.value = false;
    await fetchRoom();

    // Restore selected issue
    const updatedIssue = room.value.issues.find((i) => i.id === issueId);
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
    await roomService.addIssueFromUrl(room.value.uuid, newIssueUrl.value);
    showAddIssue.value = false;
    newIssueUrl.value = "";
    await fetchRoom();
    scrollToBottom();
  } catch (e) {
    error.value = e.response?.data?.message || "Failed to add issue";
  }
}

async function finishRoom() {
  if (!room.value) return;
  try {
    await roomService.completeRoom(room.value.uuid);
    router.push("/dashboard");
  } catch (e) {
    console.error("Failed to finish room:", e);
  }
}

function copyRoomLink() {
  if (!room.value) return;
  const link = `${window.location.origin}/room/${room.value.uuid}`;
  navigator.clipboard.writeText(link);
  isCopied.value = true;
  setTimeout(() => {
    isCopied.value = false;
  }, 2000);
}

function getVotesForCard(card) {
  return revealedVotes.value.filter((v) => v.value === card);
}

const isCreator = computed(() => {
  return room.value && authStore.user && room.value.created_by === authStore.user.id;
});

const isGuest = computed(() => {
  return !!(authStore.user && authStore.user.is_guest);
});

async function exitRoom() {
  if (room.value) {
    try {
      await roomService.leaveRoom(room.value.uuid);
    } catch (e) {
      console.error("Failed to leave room:", e);
    }
  }

  if (isGuest.value) {
    authStore.logout();
    router.push("/");
  } else {
    router.push("/dashboard");
  }
}

async function saveFinalScore(scoreToSave) {
  if (!selectedIssue.value || !room.value || scoreToSave === null || scoreToSave === undefined || scoreToSave === "")
    return;

  isSavingToJira.value = true;

  try {
    const issueId = selectedIssue.value.id;
    await roomService.updateFinalScore(room.value.uuid, issueId, scoreToSave);
    isEditingScore.value = false;

    // Show saved state
    isSavingToJira.value = false;
    isSavedToJira.value = true;
    setTimeout(() => {
      isSavedToJira.value = false;
    }, 1500);
  } catch (e) {
    console.error("Failed to update score:", e);
    isSavingToJira.value = false;
  }
}

function startEditingScore() {
  editedScore.value = selectedIssue.value.final_score;
  isEditingScore.value = true;
}
async function reopenRoom() {
  if (!room.value) return;
  try {
    await roomService.reopenRoom(room.value.uuid);
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
        @click="exitRoom"
        class="text-[#fdfc04] hover:text-white transition-colors font-sans uppercase tracking-wider"
      >
        {{ isGuest ? "EXIT" : "BACK TO DASHBOARD" }}
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
              {{ isCopied ? "COPIED!" : "COPY LINK" }}
            </button>
          </div>
        </div>

        <div class="flex-1 flex flex-col min-h-0">
          <div class="p-6 pb-4">
            <div class="flex items-center justify-between">
              <h3 class="text-sm font-bold text-[#fdfc04] uppercase tracking-widest">ISSUES</h3>
              <button
                @click="showAddIssue = true"
                class="text-md text-[#00fbff] hover:text-white disabled:opacity-50 disabled:cursor-not-allowed uppercase tracking-wider font-bold"
                :disabled="room?.status === 'completed'"
                v-if="isCreator"
              >
                + ADD
              </button>
            </div>

            <div v-if="showAddIssue" class="mt-6 art-deco-card p-4">
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
          </div>

          <div class="flex-1 overflow-y-auto px-6 pb-6 custom-scrollbar" ref="issuesListRef">
            <div class="space-y-3">
              <div
                v-for="issue in room?.issues"
                :key="issue.id"
                :class="[
                  'p-4 border transition-all duration-300 relative group cursor-pointer',
                  selectedIssue?.id === issue.id
                    ? 'bg-black border-[#fdfc04] shadow-[0_0_10px_rgba(253,252,4,0.2)]'
                    : 'bg-transparent border-gray-800 hover:border-[#fdfc04] hover:border-opacity-50',
                ]"
                @click="selectedIssue = issue"
              >
                <div class="flex items-center justify-between mb-1">
                  <span class="font-display font-bold text-sm text-white tracking-wide">{{
                    issue.jira_issue_key
                  }}</span>
                  <span v-if="issue.final_score" class="text-lg font-bold text-[#00fbff] font-display">
                    {{ issue.final_score }}
                  </span>
                </div>
                <p class="text-xs text-gray-400 truncate font-sans">{{ issue.summary }}</p>

                <!-- Selection indicator -->
                <div
                  v-if="selectedIssue?.id === issue.id"
                  class="absolute left-0 top-0 bottom-0 w-1 bg-[#fdfc04]"
                ></div>
              </div>
            </div>
          </div>
        </div>

        <div class="p-6 pe-0 border-t border-[#fdfc04] border-opacity-30 bg-black">
          <h3 class="text-sm font-bold text-[#fdfc04] mb-4 uppercase tracking-widest">
            PARTICIPANTS ({{ room?.participants?.length }})
          </h3>
          <div class="space-y-3 max-h-48 overflow-y-auto pr-1 custom-scrollbar">
            <div
              v-for="participant in room?.participants"
              :key="participant.id"
              class="flex items-center gap-3 ml-1 mt-1"
            >
              <div
                class="w-8 h-8 rounded-full border-2 border-[#fdfc04] bg-[#00fbff] flex items-center justify-center text-[#041628] font-bold text-xs relative overflow-hidden"
              >
                <img v-if="participant.avatar_url" :src="participant.avatar_url" class="w-full h-full object-cover" />
                <span v-else>{{ participant.display_name.charAt(0).toUpperCase() }}</span>
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
            <div class="flex-1">
              <h2
                class="text-2xl font-display font-bold text-white mb-4 tracking-widest uppercase flex items-center gap-3"
              >
                <a
                  :href="selectedIssue.jira_url"
                  target="_blank"
                  class="text-[#fdfc04] hover:text-white transition-colors flex items-center gap-2 group"
                  title="Open in Jira"
                >
                  <span>{{ selectedIssue.jira_issue_key }}:</span>
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-5 w-5 opacity-50 group-hover:opacity-100"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
                    />
                  </svg>
                </a>
                <span class="flex-1">{{ selectedIssue.summary }}</span>
              </h2>
              <div
                v-if="selectedIssue.description"
                class="text-gray-300 text-lg font-sans whitespace-pre-wrap bg-black border border-gray-800 p-8 relative leading-relaxed max-h-[500px] overflow-y-auto custom-scrollbar"
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
              <div class="flex items-center gap-4">
                <h3 class="text-xl font-display font-bold text-[#fdfc04] uppercase tracking-widest">Estimations</h3>
                <div v-if="selectedIssue.status === 'voting'" class="flex -space-x-2">
                  <div
                    v-for="voter in selectedIssue.voters"
                    :key="voter.user_id"
                    class="w-8 h-8 rounded-full border-2 border-[#fdfc04] bg-[#00fbff] flex items-center justify-center text-[#041628] font-bold text-xs relative group"
                    :title="voter.display_name"
                  >
                    <img
                      v-if="voter.avatar_url"
                      :src="voter.avatar_url"
                      class="w-full h-full rounded-full object-cover"
                    />
                    <span v-else>{{ voter.display_name.charAt(0).toUpperCase() }}</span>

                    <!-- Tooltip -->
                    <div
                      class="absolute bottom-full mb-2 hidden group-hover:block bg-black border border-[#fdfc04] text-[#fdfc04] text-xs px-2 py-1 whitespace-nowrap z-30 uppercase tracking-wider"
                    >
                      {{ voter.display_name }} voted!
                    </div>
                  </div>
                </div>
              </div>

              <div class="flex gap-4" v-if="isCreator">
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
                <div class="art-deco-card p-10 text-center min-w-[200px] relative group">
                  <p class="text-gray-400 text-sm mb-4 uppercase tracking-widest font-sans">
                    {{ isCreator ? "SET FINAL SCORE" : "SUGGESTED SCORE" }}
                  </p>

                  <div v-if="!isEditingScore">
                    <p
                      class="text-7xl font-bold text-[#00fbff] font-display drop-shadow-[0_0_10px_rgba(0,251,255,0.5)]"
                    >
                      {{ selectedIssue.final_score || "?" }}
                    </p>
                    <div class="mt-6 flex justify-center gap-3">
                      <button
                        v-if="isCreator && room?.status !== 'completed'"
                        @click="startEditingScore"
                        class="px-4 py-2 border border-gray-500 text-gray-400 hover:text-white hover:border-white text-xs font-bold tracking-widest transition-colors uppercase disabled:opacity-50"
                        :disabled="isSavingToJira || isSavedToJira"
                      >
                        EDIT
                      </button>
                      <button
                        v-if="isCreator && room?.status !== 'completed'"
                        @click="saveFinalScore(selectedIssue.final_score)"
                        class="px-4 py-2 border text-xs font-bold tracking-widest transition-colors uppercase min-w-[140px] flex items-center justify-center gap-2"
                        :class="[
                          isSavedToJira
                            ? 'border-green-500 bg-green-500 text-white'
                            : 'border-[#00fbff] text-[#00fbff] hover:bg-[#00fbff] hover:text-[#041628]',
                          { 'opacity-50 cursor-not-allowed': isSavingToJira },
                        ]"
                        :disabled="isSavingToJira || isSavedToJira"
                      >
                        <span
                          v-if="isSavingToJira"
                          class="inline-block w-4 h-4 border-2 border-t-transparent border-current rounded-full animate-spin"
                        ></span>
                        <svg
                          v-else-if="isSavedToJira"
                          xmlns="http://www.w3.org/2000/svg"
                          class="h-4 w-4"
                          fill="none"
                          viewBox="0 0 24 24"
                          stroke="currentColor"
                        >
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        {{ isSavedToJira ? "SAVED" : isSavingToJira ? "SAVING..." : "SAVE TO JIRA" }}
                      </button>
                    </div>
                  </div>

                  <div v-else class="flex flex-col items-center gap-4 mt-2">
                    <input
                      v-model="editedScore"
                      type="number"
                      step="1"
                      min="0"
                      class="w-32 text-center text-5xl font-bold text-[#00fbff] font-display bg-black border border-[#fdfc04] focus:outline-none focus:shadow-[0_0_10px_rgba(253,252,4,0.3)] p-2 hide-arrows"
                      @keyup.enter="saveFinalScore(editedScore)"
                      @keyup.esc="isEditingScore = false"
                      autofocus
                    />
                    <div class="flex gap-2">
                      <button
                        @click="saveFinalScore(editedScore)"
                        class="text-xs font-bold tracking-widest flex items-center justify-center gap-2 px-3 py-2 border rounded-sm transition-colors min-w-[120px]"
                        :class="[
                          isSavedToJira
                            ? 'border-green-500 bg-green-500 text-white'
                            : 'border-[#00fbff] text-[#00fbff] hover:bg-[#00fbff] hover:text-[#041628]',
                          { 'opacity-50 cursor-not-allowed': isSavingToJira },
                        ]"
                        :disabled="isSavingToJira || isSavedToJira"
                      >
                        <span
                          v-if="isSavingToJira"
                          class="inline-block w-4 h-4 border-2 border-t-transparent border-current rounded-full animate-spin"
                        ></span>
                        <svg
                          v-else-if="isSavedToJira"
                          xmlns="http://www.w3.org/2000/svg"
                          class="h-4 w-4"
                          fill="none"
                          viewBox="0 0 24 24"
                          stroke="currentColor"
                        >
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        {{ isSavedToJira ? "SAVED" : isSavingToJira ? "SAVING..." : "SAVE TO JIRA" }}
                      </button>
                      <button
                        @click="isEditingScore = false"
                        class="text-xs text-gray-500 hover:text-gray-300 tracking-widest px-3 py-2 disabled:opacity-50"
                        :disabled="isSavingToJira || isSavedToJira"
                      >
                        CANCEL
                      </button>
                    </div>
                  </div>
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

        <div v-else class="flex-1 flex flex-col items-center justify-center text-gray-500 relative z-10 p-8">
          <div v-if="room?.issues?.length === 0" class="flex flex-col items-center text-center max-w-2xl">
            <div class="mb-12">
              <h3 class="text-[#00fbff] font-display text-xl tracking-widest uppercase mb-4 opacity-80">ICE BREAKER</h3>
              <p class="text-3xl text-white font-sans leading-relaxed mb-8 relative px-12">
                <span class="absolute left-0 top-0 text-5xl text-[#fdfc04] opacity-30 font-serif">"</span>
                {{ currentIceBreaker }}
                <span class="absolute right-0 bottom-[-20px] text-5xl text-[#fdfc04] opacity-30 font-serif">"</span>
              </p>
              <button
                v-if="isCreator"
                @click="pickRandomIceBreaker"
                class="px-6 py-2 border border-[#00fbff] text-[#00fbff] hover:bg-[#00fbff] hover:text-[#041628] transition-colors uppercase tracking-widest text-xs font-bold"
              >
                NEXT QUESTION
              </button>
            </div>

            <div class="w-full h-px bg-gradient-to-r from-transparent via-gray-700 to-transparent my-8"></div>

            <p class="font-display tracking-widest uppercase mb-4 text-gray-400">WAITING FOR ISSUES TO BE ADDED...</p>
            <p v-if="isCreator" class="text-sm font-sans text-gray-500">Use the "+ ADD" button on the left to start.</p>
          </div>

          <div v-else class="flex flex-col items-center">
            <div class="w-16 h-16 border border-gray-700 rotate-45 flex items-center justify-center mb-4">
              <div class="w-12 h-12 border border-gray-700"></div>
            </div>
            <p class="font-display tracking-widest uppercase mb-8">SELECT AN ISSUE TO START</p>
          </div>

          <button
            @click="router.push('/dashboard')"
            class="mt-8 px-6 py-3 border border-gray-700 text-gray-400 hover:text-[#fdfc04] hover:border-[#fdfc04] transition-all uppercase tracking-widest font-sans font-bold"
          >
            EXIT ROOM
          </button>
        </div>

        <button
          v-if="room?.status !== 'completed' && isCreator"
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
