import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import Antd from 'ant-design-vue'
import 'ant-design-vue/dist/reset.css'
// import './style.css' // 暂时注释掉默认样式，避免冲突，后续按需开启

// 应用入口（前端业务代码）
const app = createApp(App)

// 路由 + UI 组件库
app.use(router)
app.use(Antd)

app.mount('#app')
