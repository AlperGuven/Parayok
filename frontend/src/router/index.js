import { createRouter, createWebHistory } from 'vue-router'
import LoginPage from '@/pages/LoginPage.vue'
import DashboardPage from '@/pages/DashboardPage.vue'
import CreateRoomPage from '@/pages/CreateRoomPage.vue'
import RoomPage from '@/pages/RoomPage.vue'
import CallbackPage from '@/pages/CallbackPage.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      name: 'login',
      component: LoginPage,
    },
    {
      path: '/auth/jira/callback',
      name: 'callback',
      component: CallbackPage,
    },
    {
      path: '/dashboard',
      name: 'dashboard',
      component: DashboardPage,
    },
    {
      path: '/room/create',
      name: 'create-room',
      component: CreateRoomPage,
    },
    {
      path: '/room/:uuid',
      name: 'room',
      component: RoomPage,
    },
  ],
})

export default router
