<template>
  <ion-page>
    <ion-content class="ion-padding" color="light">
      <div class="login-container">
        <div class="logo-area">
          <h1 class="app-title">Escuela Nueva Austral</h1>
          <p class="app-subtitle">Gestion de facturas y gastos</p>
        </div>

        <ion-card>
          <ion-card-content>
            <ion-item lines="full">
              <ion-label position="stacked">Correo electronico</ion-label>
              <ion-input
                v-model="email"
                type="email"
                placeholder="tu@empresa.com"
                autocomplete="email"
                inputmode="email"
              />
            </ion-item>

            <ion-item lines="none" class="ion-margin-bottom">
              <ion-label position="stacked">Contrasena</ion-label>
              <ion-input
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                placeholder="Minimo 8 caracteres"
                autocomplete="current-password"
              />
              <ion-button slot="end" fill="clear" @click="showPassword = !showPassword">
                <ion-icon :icon="showPassword ? eyeOffOutline : eyeOutline" color="medium" />
              </ion-button>
            </ion-item>

            <p v-if="error" class="error-text">{{ error }}</p>

            <ion-button
              expand="block"
              color="primary"
              :disabled="loading"
              class="ion-margin-top"
              @click="handleLogin"
            >
              <ion-spinner v-if="loading" name="crescent" />
              <span v-else>Iniciar sesion</span>
            </ion-button>

            <div class="forgot-link">
              <router-link to="/auth/forgot-password">Olvide mi contrasena</router-link>
            </div>
          </ion-card-content>
        </ion-card>
      </div>
    </ion-content>
  </ion-page>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  IonPage, IonContent, IonCard, IonCardContent,
  IonItem, IonLabel, IonInput, IonButton, IonIcon, IonSpinner,
} from '@ionic/vue'
import { eyeOutline, eyeOffOutline } from 'ionicons/icons'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const email = ref('')
const password = ref('')
const showPassword = ref(false)
const loading = ref(false)
const error = ref('')

async function handleLogin() {
  if (!email.value || !password.value) {
    error.value = 'Completa todos los campos'
    return
  }
  loading.value = true
  error.value = ''
  try {
    await authStore.login(email.value, password.value)
    router.replace('/tabs/dashboard')
  } catch (e: any) {
    error.value = e.response?.data?.message ?? 'Credenciales incorrectas'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.login-container {
  display: flex;
  flex-direction: column;
  justify-content: center;
  min-height: 100%;
  padding: 24px 0;
}
.logo-area {
  text-align: center;
  margin-bottom: 32px;
}
.app-title {
  font-size: 36px;
  font-weight: 700;
  color: var(--ion-color-primary);
  margin: 0;
}
.app-subtitle {
  color: var(--ion-color-medium);
  margin: 4px 0 0;
  font-size: 14px;
}
.error-text {
  color: var(--ion-color-danger);
  font-size: 13px;
  margin: 8px 16px 0;
}
.forgot-link {
  text-align: center;
  margin-top: 16px;
  font-size: 14px;
}
.forgot-link a {
  color: var(--ion-color-primary);
  text-decoration: none;
}
</style>
