import { createI18n } from 'vue-i18n'

import enAuth from './locales/en/auth.json'
import enCommon from './locales/en/common.json'
import enDashboard from './locales/en/dashboard.json'
import enDlq from './locales/en/dlq.json'
import enEndpointProfiles from './locales/en/endpointProfiles.json'
import enEnvironments from './locales/en/environments.json'
import enPipelines from './locales/en/pipelines.json'
import enRuns from './locales/en/runs.json'
import enSecrets from './locales/en/secrets.json'
import enSettings from './locales/en/settings.json'
import enTasks from './locales/en/tasks.json'
import enValidation from './locales/en/validation.json'
import ptAuth from './locales/pt-BR/auth.json'
import ptCommon from './locales/pt-BR/common.json'
import ptDashboard from './locales/pt-BR/dashboard.json'
import ptDlq from './locales/pt-BR/dlq.json'
import ptEndpointProfiles from './locales/pt-BR/endpointProfiles.json'
import ptEnvironments from './locales/pt-BR/environments.json'
import ptPipelines from './locales/pt-BR/pipelines.json'
import ptRuns from './locales/pt-BR/runs.json'
import ptSecrets from './locales/pt-BR/secrets.json'
import ptSettings from './locales/pt-BR/settings.json'
import ptTasks from './locales/pt-BR/tasks.json'
import ptValidation from './locales/pt-BR/validation.json'

export const SUPPORTED_LOCALES = ['en', 'pt-BR'] as const
export type SupportedLocale = (typeof SUPPORTED_LOCALES)[number]

export const DEFAULT_LOCALE: SupportedLocale = 'en'
export const FALLBACK_LOCALE: SupportedLocale = 'en'

const messages = {
  en: {
    common: enCommon,
    auth: enAuth,
    dashboard: enDashboard,
    tasks: enTasks,
    dlq: enDlq,
    pipelines: enPipelines,
    endpointProfiles: enEndpointProfiles,
    environments: enEnvironments,
    runs: enRuns,
    secrets: enSecrets,
    settings: enSettings,
    validation: enValidation,
  },
  'pt-BR': {
    common: ptCommon,
    auth: ptAuth,
    dashboard: ptDashboard,
    tasks: ptTasks,
    dlq: ptDlq,
    pipelines: ptPipelines,
    endpointProfiles: ptEndpointProfiles,
    environments: ptEnvironments,
    runs: ptRuns,
    secrets: ptSecrets,
    settings: ptSettings,
    validation: ptValidation,
  },
}

export function resolveInitialLocale(): SupportedLocale {
  const stored = localStorage.getItem('locale')
  if (stored && SUPPORTED_LOCALES.includes(stored as SupportedLocale)) {
    return stored as SupportedLocale
  }

  const browser = navigator.language
  if (browser.startsWith('pt')) {
    return 'pt-BR'
  }

  return DEFAULT_LOCALE
}

export function updateDocumentLang(locale: SupportedLocale): void {
  document.documentElement.lang = locale
}

export const i18n = createI18n({
  legacy: false,
  locale: resolveInitialLocale(),
  fallbackLocale: FALLBACK_LOCALE,
  messages,
})

export function setLocale(locale: SupportedLocale): void {
  i18n.global.locale.value = locale
  localStorage.setItem('locale', locale)
  updateDocumentLang(locale)
}

export default i18n
