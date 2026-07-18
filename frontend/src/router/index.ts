import { createRouter, createWebHistory } from 'vue-router'

import { useAuthStore } from '@/stores/auth'
import { useTenantStore } from '@/stores/tenant'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/pages/LoginPage.vue'),
      meta: { guest: true },
    },
    {
      path: '/',
      component: () => import('@/layouts/AppLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          redirect: { name: 'dashboard' },
        },
        {
          path: 'dashboard',
          name: 'dashboard',
          component: () => import('@/pages/DashboardPage.vue'),
        },
        {
          path: 'tasks',
          name: 'tasks',
          component: () => import('@/pages/TaskListPage.vue'),
        },
        {
          path: 'tasks/new',
          name: 'tasks-create',
          component: () => import('@/pages/TaskWizardPage.vue'),
        },
        {
          path: 'tasks/:id',
          name: 'tasks-detail',
          component: () => import('@/pages/TaskDetailPage.vue'),
          props: true,
        },
        {
          path: 'tasks/:id/edit',
          name: 'tasks-edit',
          component: () => import('@/pages/TaskWizardPage.vue'),
          props: true,
        },
        {
          path: 'endpoint-profiles',
          name: 'endpoint-profiles',
          component: () => import('@/pages/EndpointProfileListPage.vue'),
        },
        {
          path: 'endpoint-profiles/:id',
          name: 'endpoint-profiles-detail',
          component: () => import('@/pages/EndpointProfileDetailPage.vue'),
          props: true,
        },
        {
          path: 'runs',
          name: 'runs',
          component: () => import('@/pages/RunListPage.vue'),
        },
        {
          path: 'runs/:id',
          name: 'runs-detail',
          component: () => import('@/pages/RunDetailPage.vue'),
          props: true,
        },
        {
          path: 'environments',
          name: 'environments',
          component: () => import('@/pages/EnvironmentsPage.vue'),
        },
        {
          path: 'api-keys',
          name: 'api-keys',
          component: () => import('@/pages/ApiKeysPage.vue'),
        },
        {
          path: 'members',
          name: 'members',
          component: () => import('@/pages/MembersPage.vue'),
        },
        {
          path: 'settings',
          name: 'settings',
          component: () => import('@/pages/SettingsPage.vue'),
        },
        {
          path: 'platform-health',
          name: 'platform-health',
          component: () => import('@/pages/PlatformHealthPage.vue'),
        },
      ],
    },
    {
      path: '/:pathMatch(.*)*',
      redirect: '/',
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (!auth.initialized) {
    await auth.fetchUser()
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.guest && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }

  if (to.meta.requiresAuth && auth.isAuthenticated) {
    const tenant = useTenantStore()
    if (tenant.tenants.length === 0 && !tenant.loading) {
      await tenant.fetchTenants()
    }
  }

  return true
})

export default router
