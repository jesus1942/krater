<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <ion-buttons slot="start"><ion-back-button default-href="/settings" /></ion-buttons>
        <ion-title>Mi perfil</ion-title>
        <ion-buttons slot="end">
          <ion-button :disabled="saving" @click="save">
            <ion-spinner v-if="saving" name="crescent" /><span v-else>Guardar</span>
          </ion-button>
        </ion-buttons>
      </ion-toolbar>
    </ion-header>
    <ion-content color="light">
      <ion-card>
        <ion-card-content class="ion-no-padding">
          <ion-item lines="full"><ion-label position="stacked">Nombre *</ion-label><ion-input v-model="form.name" /></ion-item>
          <ion-item lines="full"><ion-label position="stacked">Correo electronico</ion-label><ion-input v-model="form.email" type="email" /></ion-item>
          <ion-item lines="full"><ion-label position="stacked">Nueva contrasena</ion-label><ion-input v-model="form.password" type="password" /></ion-item>
          <ion-item lines="none"><ion-label position="stacked">Confirmar contrasena</ion-label><ion-input v-model="form.password_confirmation" type="password" /></ion-item>
        </ion-card-content>
      </ion-card>
    </ion-content>
  </ion-page>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonButtons, IonButton, IonBackButton, IonCard, IonCardContent, IonItem, IonLabel, IonInput, IonSpinner, toastController } from '@ionic/vue'
import http from '@/services/http'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const saving = ref(false)
const form = ref({ name: '', email: '', password: '', password_confirmation: '' })

async function save() {
  saving.value = true
  try {
    const payload: any = { name: form.value.name, email: form.value.email }
    if (form.value.password) { payload.password = form.value.password; payload.password_confirmation = form.value.password_confirmation }
    await http.put('/me', payload)
    await authStore.fetchMe()
    const t = await toastController.create({ message: 'Perfil actualizado', duration: 2000, color: 'success' })
    t.present()
  } catch (e: any) {
    const t = await toastController.create({ message: e.response?.data?.message ?? 'Error al guardar', duration: 3000, color: 'danger' })
    t.present()
  } finally { saving.value = false }
}

onMounted(() => {
  const user = authStore.currentUser
  if (user) { form.value.name = user.name; form.value.email = user.email }
})
</script>
