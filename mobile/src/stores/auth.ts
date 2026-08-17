import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import http from '@/services/http'

interface User {
  id: number
  name: string
  email: string
  avatar?: string
  company?: {
    id: number
    name: string
    currency?: { symbol: string; code: string; precision: number }
  }
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const token = ref<string | null>(null)

  const isAuthenticated = computed(() => !!token.value)
  const currentUser = computed(() => user.value)
  const company = computed(() => user.value?.company)
  const currency = computed(() => user.value?.company?.currency)

  function loadFromStorage() {
    const stored = localStorage.getItem('auth_token')
    const storedUser = localStorage.getItem('auth_user')
    if (stored) {
      token.value = stored
    }
    if (storedUser) {
      try {
        user.value = JSON.parse(storedUser)
      } catch {}
    }
  }

  async function login(email: string, password: string) {
    // La API valida `username`, no `email` (ver V1\Mobile\AuthController::login).
    // Mandar `email` devolvia 422 siempre: el login de la PWA estaba roto, no
    // degradado.
    const { data } = await http.post('/auth/login', {
      username: email,
      password,
      device_name: 'crater-pwa',
    })

    token.value = data.token
    localStorage.setItem('auth_token', data.token)

    // La respuesta del login solo trae `type` y `token`; el usuario no viene.
    // Antes se hacia `user.value = data.user`, que quedaba undefined y rompia
    // todo lo que dependiera del usuario. Se pide aparte.
    await fetchMe()
  }

  async function logout() {
    try {
      await http.post('/auth/logout')
    } finally {
      token.value = null
      user.value = null
      localStorage.removeItem('auth_token')
      localStorage.removeItem('auth_user')
      localStorage.removeItem('auth_company')
    }
  }

  async function fetchMe() {
    const { data } = await http.get('/me')
    user.value = data.user
    localStorage.setItem('auth_user', JSON.stringify(data.user))

    // El interceptor de http.ts lee esto para mandar el header `company`.
    if (data.user?.company?.id) {
      localStorage.setItem('auth_company', String(data.user.company.id))
    }
  }

  return { user, token, isAuthenticated, currentUser, company, currency, loadFromStorage, login, logout, fetchMe }
})
