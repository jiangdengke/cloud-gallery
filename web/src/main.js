import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import Antd from 'ant-design-vue'
import 'ant-design-vue/dist/reset.css'
// import './style.css' // 暂时注释掉默认样式，避免冲突，后续按需开启

const app = createApp(App)

app.use(router)
app.use(Antd)

app.mount('#app')