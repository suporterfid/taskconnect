import { createPinia } from 'pinia'
import { createApp } from 'vue'

import App from './App.vue'
import i18n, { updateDocumentLang } from './i18n'
import router from './router'
import { initializeTheme, THEME_CONTROLLER_KEY } from './theme'
import './style.css'

const themeController = initializeTheme()

const app = createApp(App)

app.use(createPinia())
app.use(i18n)
app.use(router)
app.provide(THEME_CONTROLLER_KEY, themeController)

updateDocumentLang(i18n.global.locale.value)

app.mount('#app')
