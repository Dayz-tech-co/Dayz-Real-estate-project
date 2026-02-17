import { createRouter, createWebHistory } from 'vue-router'

import HomeView from '@/pages/Home.vue'
import BlogView from '@/pages/Blog.vue'
import SingleBlogView from '@/pages/SingleBlog.vue'
import RegisterView from '@/views/auth/RegisterView.vue'
import LoginView from '@/views/auth/LoginView.vue'
import VerifyAccountView from '@/views/auth/VerifyAccountView.vue'
import ForgotPasswordView from '@/views/auth/ForgotPasswordView.vue'
import ResetPasswordView from '@/views/auth/ResetPasswordView.vue'
import UserDashboard from '@/views/dashboard/UserDashboard.vue'
import AgentDashboard from '@/views/dashboard/AgentDashboard.vue'
import AgentMarket from '@/views/dashboard/AgentMarket.vue'
import ComparisonView from '@/views/dashboard/ComparisonView.vue'
import SavedProperties from '@/views/dashboard/SavedProperties.vue'
import SettingsView from '@/views/dashboard/Settings.vue'
import PropertyDetailsView from '@/views/dashboard/PropertyDetails.vue'

const routes = [
  { path: '/', name: 'home', component: HomeView },
  { path: '/blog', name: 'blog', component: BlogView },
  { path: '/blog/', redirect: '/blog' },
  { path: '/blogs', redirect: '/blog' },
  { path: '/blog/:slug', name: 'single-blog', component: SingleBlogView },
  {
    path: '/property/:id',
    name: 'property-details',
    component: PropertyDetailsView,
    meta: { requiresAuth: true }
  },
  { path: '/register', name: 'register', component: RegisterView },
  { path: '/login', name: 'login', component: LoginView },
  {
    path: '/verify-account',
    name: 'verify-account',
    component: VerifyAccountView,
    meta: { requiresAuth: true, role: 'user' }
  },
  { path: '/forgot-password', name: 'forgot-password', component: ForgotPasswordView },
  { path: '/reset-password', name: 'reset-password', component: ResetPasswordView },
  {
    path: '/dashboard/user',
    name: 'user-dashboard',
    component: UserDashboard,
    meta: { requiresAuth: true, role: 'user' }
  },
  {
    path: '/dashboard/agent',
    name: 'agent-dashboard',
    component: AgentDashboard,
    meta: { requiresAuth: true, role: 'agent' }
  },
  {
    path: '/marketplace/agent',
    name: 'agent-market',
    component: AgentMarket,
    meta: { requiresAuth: true, role: 'agent' }
  },
  {
    path: '/dashboard/compare',
    name: 'comparison',
    component: ComparisonView,
    meta: { requiresAuth: true }
  },
  {
    path: '/saved',
    name: 'saved-properties',
    component: SavedProperties,
    meta: { requiresAuth: true, role: 'user' }
  },
  {
    path: '/settings/user',
    name: 'settings-user',
    component: SettingsView,
    meta: { requiresAuth: true, role: 'user' }
  },
  {
    path: '/settings/agent',
    name: 'settings-agent',
    component: SettingsView,
    meta: { requiresAuth: true, role: 'agent' }
  },
  {
    path: '/settings',
    name: 'settings',
    redirect: () => {
      const role = localStorage.getItem('USER_ROLE')
      return role === 'agent' ? '/settings/agent' : '/settings/user'
    }
  },
  { path: '/intel', redirect: '/blog' }
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior() {
    return { top: 0 }
  }
})

router.beforeEach((to) => {
  if (!to.meta.requiresAuth) return true

  const token = localStorage.getItem('AUTH_TOKEN')
  const role = localStorage.getItem('USER_ROLE')

  if (!token) return { path: '/login' }
  if (to.meta.role && to.meta.role !== role) {
    if (to.path.startsWith('/settings/')) {
      return role === 'agent' ? { path: '/settings/agent' } : { path: '/settings/user' }
    }
    return role === 'agent' ? { path: '/dashboard/agent' } : { path: '/dashboard/user' }
  }

  return true
})

export default router
