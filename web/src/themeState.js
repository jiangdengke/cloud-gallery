import { ref } from 'vue';

// 尝试从本地存储读取主题，默认为 true (暗色)
const storedTheme = localStorage.getItem('theme_mode');
export const isDark = ref(storedTheme === null ? true : storedTheme === 'dark');

export const toggleTheme = () => {
  isDark.value = !isDark.value;
  localStorage.setItem('theme_mode', isDark.value ? 'dark' : 'light');
  updateBodyClass();
};

const updateBodyClass = () => {
  if (isDark.value) {
    document.body.classList.add('dark-mode');
  } else {
    document.body.classList.remove('dark-mode');
  }
};

// 立即初始化
updateBodyClass();