import { createRouter, createWebHistory } from 'vue-router'
import LoginPage from '@/pages/LoginPage.vue'
import DashboardPage from '@/pages/DashboardPage.vue'
import CreateRoomPage from '@/pages/CreateRoomPage.vue'
import RoomPage from '@/pages/RoomPage.vue'
import CallbackPage from '@/pages/CallbackPage.vue'
import { useAuthStore } from '@/stores/authStore'

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
      meta: { requiresAuth: true, guestBlocked: true },
    },
    {
      path: '/room/create',
      name: 'create-room',
      component: CreateRoomPage,
      meta: { requiresAuth: true, guestBlocked: true },
    },
    {
      path: '/room/:uuid',
      name: 'room',
      component: RoomPage,
      meta: { requiresAuth: true },
    },
  ],
})

router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore();
  
  // Try to restore session if token exists but user is null
  if (!authStore.user && localStorage.getItem('token')) {
    await authStore.fetchUser();
  }

  const isAuthenticated = authStore.isAuthenticated;
  const isGuest = authStore.user?.is_guest;

  if (to.meta.requiresAuth && !isAuthenticated) {
    next({ name: 'login', query: { redirect: to.fullPath } });
    return;
  }

  if (to.meta.guestBlocked && isGuest) {
    // If guest tries to access dashboard or create room, redirect to login (or maybe stay on room page?)
    // Best is to logout and go to login page
    authStore.logout();
    next({ name: 'login' });
    return;
  }

  next();
});

export default router
