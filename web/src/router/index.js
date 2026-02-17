import { createRouter, createWebHistory } from 'vue-router'

const routes = [
  {
    path: '/',
    name: 'Home',
    component: () => import('../views/Home.vue')
  },
  {
    path: '/s/:token',
    name: 'Share',
    component: () => import('../views/Share.vue')
  },
  {
    path: '/admin',
    name: 'Admin',
    component: () => import('../views/Admin.vue'),
    meta: { requiresAuth: true }
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

// 简单的路由守卫
router.beforeEach((to, from, next) => {
  if (to.meta.requiresAuth) {
    const hasKey = localStorage.getItem('api_key');
    if (!hasKey) {
      return next('/');
    }
  }
  next();
})

export default router
