import { ref } from 'vue';

// 主题状态（前端业务代码）
// - 使用 localStorage 持久化深色/浅色偏好
// - 通过给 body 添加/移除 dark-mode class 控制 CSS 变量

// 尝试从本地存储读取主题，默认为 true（暗色）
const storedTheme = localStorage.getItem('theme_mode');
export const isDark = ref(storedTheme === null ? true : storedTheme === 'dark');

export const toggleTheme = () => {
  isDark.value = !isDark.value;
  localStorage.setItem('theme_mode', isDark.value ? 'dark' : 'light');
  updateBodyClass();
};

const updateBodyClass = () => {
  // 用 class 驱动主题（见 App.vue 的全局样式与 CSS 变量）
  if (isDark.value) {
    document.body.classList.add('dark-mode');
  } else {
    document.body.classList.remove('dark-mode');
  }
};

// 立即初始化
updateBodyClass();
