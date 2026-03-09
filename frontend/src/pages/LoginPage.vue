<script setup lang="ts">
import { useRouter } from "vue-router";
import api from "@/services/api";
import logo from "@/assets/images/parayok.png";

const router = useRouter();

async function loginWithJira() {
  try {
    await api.get("/sanctum/csrf-cookie");
    window.location.href = "/auth/jira";
  } catch (error) {
    console.error("Login error:", error);
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-[#041628]">
    <div class="art-deco-card p-12 rounded-none text-center max-w-md w-full mx-4">
      <div class="mb-8 flex justify-center">
        <img :src="logo" alt="Parayok" class="h-48 mx-auto mb-4" />
      </div>

      <h1 class="text-4xl font-bold text-[#fdfc04] mb-4 font-display tracking-widest uppercase">Parayok</h1>

      <p class="text-gray-400 mb-8 font-sans text-lg">Estimate Jira issues with your team in real-time</p>

      <button
        @click="loginWithJira"
        class="art-deco-button primary block w-full py-3 px-4 font-bold text-center tracking-widest uppercase"
      >
        LOGIN WITH JIRA
      </button>
    </div>
  </div>
</template>
