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
      path: '/forgot-password',
      name: 'forgot-password',
      component: () => import('@/pages/ForgotPasswordPage.vue'),
      meta: { guest: true },
    },
    {
      path: '/reset-password',
      name: 'reset-password',
      component: () => import('@/pages/ResetPasswordPage.vue'),
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
          path: 'dlq',
          name: 'dlq',
          component: () => import('@/pages/DlqPage.vue'),
        },
        {
          path: 'pipelines',
          name: 'pipelines',
          component: () => import('@/pages/PipelineListPage.vue'),
        },
        {
          path: 'pipelines/:templateName/instances/:id',
          name: 'pipelines-detail',
          component: () => import('@/pages/PipelineDetailPage.vue'),
          props: true,
        },
        {
          path: 'endpoint-profiles',
          name: 'endpoint-profiles',
          component: () => import('@/pages/EndpointProfileListPage.vue'),
        },
        {
          path: 'endpoint-profiles/new',
          name: 'endpoint-profiles-create',
          component: () => import('@/pages/EndpointProfileFormPage.vue'),
        },
        {
          path: 'endpoint-profiles/:id/edit',
          name: 'endpoint-profiles-edit',
          component: () => import('@/pages/EndpointProfileFormPage.vue'),
          props: true,
        },
        {
          path: 'endpoint-profiles/:id',
          name: 'endpoint-profiles-detail',
          component: () => import('@/pages/EndpointProfileDetailPage.vue'),
          props: true,
        },
        {
          path: 'secrets',
          name: 'secrets',
          component: () => import('@/pages/SecretsPage.vue'),
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
          path: 'audit-logs',
          name: 'audit-logs',
          component: () => import('@/pages/AuditLogsPage.vue'),
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

// After a deploy, hashed chunk filenames change. A tab still running the old
 // bundle will fail to lazy-load routes (404 HTML). Force a full navigation so
 // the browser picks up the new HTML/manifest.
router.onError((error, to) => {
  const message = String(error?.message ?? error ?? '')
  const isChunkLoadError =
    message.includes('Failed to fetch dynamically imported module') ||
    message.includes('error loading dynamically imported module') ||
    message.includes('Importing a module script failed') ||
    message.includes('Unable to preload CSS')

  if (isChunkLoadError) {
    const target = to?.fullPath || window.location.pathname
    window.location.assign(target)
  }
})

export default router
