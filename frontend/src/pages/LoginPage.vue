<script setup lang="ts">
import { useRouter } from 'vue-router'
import api from '@/services/api'
import logo from '@/assets/images/parayok.png'

const router = useRouter()

async function loginWithJira() {
  try {
    await api.get('/sanctum/csrf-cookie')
    window.location.href = '/auth/jira'
  } catch (error) {
    console.error('Login error:', error)
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full p-8 neon-card rounded-lg">
      <div class="text-center">
        <img :src="logo" alt="Parayok" class="h-48 mx-auto mb-4" />
        <p class="text-gray-400 mb-8">
          Estimate Jira issues with your team in real-time
        </p>
      </div>
      
      <button
        @click="loginWithJira"
        class="w-full flex items-center justify-center gap-3 px-4 py-3 neon-button rounded-lg hover:opacity-90 transition-opacity font-medium"
      >
        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor">
          <path d="M11.571 11.513H0a5.218 5.218 0 0 0 5.232 5.215h2.13v2.057A5.215 5.215 0 0 0 12.575 24V12.518a1.005 1.005 0 0 0-1.005-1.005zm5.723-5.756H5.736a5.215 5.215 0 0 0 5.215 5.214h2.129v2.058a5.218 5.218 0 0 0 5.215 5.214V6.758a1.001 1.001 0 0 0-1.001-1.001zM23.013 0H11.455a5.215 5.215 0 0 0 5.215 5.215h2.129v2.057A5.215 5.215 0 0 0 24 12.483V1.005A1.005 1.005 0 0 0 23.013 0z"/>
        </svg>
        Login with Jira
      </button>
    </div>
  </div>
</template>
