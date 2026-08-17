<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <ion-buttons slot="start">
          <ion-back-button default-href="/auth/login" />
        </ion-buttons>
        <ion-title>Recuperar contrasena</ion-title>
      </ion-toolbar>
    </ion-header>

    <ion-content class="ion-padding" color="light">
      <div v-if="!sent">
        <p class="description">
          Ingresa tu correo y te enviaremos un enlace para restablecer tu contrasena.
        </p>

        <ion-card>
          <ion-card-content>
            <ion-item lines="none">
              <ion-label position="stacked">Correo electronico</ion-label>
              <ion-input
                v-model="email"
                type="email"
                placeholder="tu@empresa.com"
                inputmode="email"
              />
            </ion-item>
            <p v-if="error" class="error-text">{{ error }}</p>
            <ion-button expand="block" color="primary" :disabled="loading" class="ion-margin-top" @click="handleSubmit">
              <ion-spinner v-if="loading" name="crescent" />
              <span v-else>Enviar enlace</span>
            </ion-button>
          </ion-card-content>
        </ion-card>
      </div>

      <div v-else class="success-area">
        <ion-icon :icon="mailOutline" class="success-icon" color="primary" />
        <h2>Revisa tu correo</h2>
        <p>Te enviamos un enlace para restablecer tu contrasena.</p>
        <ion-button fill="outline" @click="$router.replace('/auth/login')">Volver al login</ion-button>
      </div>
    </ion-content>
  </ion-page>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import {
  IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonCard, IonCardContent,
  IonItem, IonLabel, IonInput, IonButton, IonButtons, IonBackButton, IonIcon, IonSpinner,
} from '@ionic/vue'
import { mailOutline } from 'ionicons/icons'
import http from '@/services/http'

const email = ref('')
const loading = ref(false)
const error = ref('')
const sent = ref(false)

async function handleSubmit() {
  if (!email.value) { error.value = 'Ingresa tu correo'; return }
  loading.value = true
  error.value = ''
  try {
    await http.post('/auth/password/email', { email: email.value })
    sent.value = true
  } catch (e: any) {
    error.value = e.response?.data?.message ?? 'Error al enviar el correo'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.description { color: var(--ion-color-medium); margin: 16px 0; }
.error-text { color: var(--ion-color-danger); font-size: 13px; margin: 8px 16px 0; }
.success-area { text-align: center; padding: 48px 24px; }
.success-icon { font-size: 64px; }
</style>
