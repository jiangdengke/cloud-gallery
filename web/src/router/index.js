import { createRouter, createWebHistory } from 'vue-router'

// 前端路由（前端业务代码）
// - /：游客浏览
// - /admin：管理后台（本地需存在 api_key 才允许进入）
// - /s/:token：分享页

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

// 简单的路由守卫：仅做“是否保存过 api_key”的前端拦截
// 注意：真正的权限校验仍由后端接口完成
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
