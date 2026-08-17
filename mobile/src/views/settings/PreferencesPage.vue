<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <ion-buttons slot="start"><ion-back-button default-href="/settings" /></ion-buttons>
        <ion-title>Preferencias</ion-title>
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
          <ion-item lines="full">
            <ion-label position="stacked">Moneda</ion-label>
            <ion-select v-model="form.currency_id" placeholder="Seleccionar moneda">
              <ion-select-option v-for="c in currencies" :key="c.id" :value="c.id">{{ c.code }} - {{ c.name }}</ion-select-option>
            </ion-select>
          </ion-item>
          <ion-item lines="full">
            <ion-label position="stacked">Idioma</ion-label>
            <ion-select v-model="form.language" placeholder="Seleccionar idioma">
              <ion-select-option value="es">Espanol</ion-select-option>
              <ion-select-option value="en">English</ion-select-option>
            </ion-select>
          </ion-item>
          <ion-item lines="full">
            <ion-label position="stacked">Formato de fecha</ion-label>
            <ion-select v-model="form.date_format">
              <ion-select-option value="DD/MM/YYYY">DD/MM/YYYY</ion-select-option>
              <ion-select-option value="MM/DD/YYYY">MM/DD/YYYY</ion-select-option>
              <ion-select-option value="YYYY-MM-DD">YYYY-MM-DD</ion-select-option>
            </ion-select>
          </ion-item>
          <ion-item lines="none">
            <ion-label position="stacked">Zona horaria</ion-label>
            <ion-input v-model="form.time_zone" placeholder="America/Argentina/Buenos_Aires" />
          </ion-item>
        </ion-card-content>
      </ion-card>
    </ion-content>
  </ion-page>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonButtons, IonButton, IonBackButton, IonCard, IonCardContent, IonItem, IonLabel, IonInput, IonSelect, IonSelectOption, IonSpinner, toastController } from '@ionic/vue'
import http from '@/services/http'

const saving = ref(false)
const currencies = ref<any[]>([])
const form = ref({ currency_id: null as number | null, language: 'es', date_format: 'DD/MM/YYYY', time_zone: '' })

async function save() {
  saving.value = true
  try {
    await http.post('/company/settings', form.value)
    const t = await toastController.create({ message: 'Preferencias guardadas', duration: 2000, color: 'success' })
    t.present()
  } catch (e: any) {
    const t = await toastController.create({ message: e.response?.data?.message ?? 'Error al guardar', duration: 3000, color: 'danger' })
    t.present()
  } finally { saving.value = false }
}

onMounted(async () => {
  const [currResp, settingsResp] = await Promise.all([http.get('/currencies'), http.get('/company/settings')])
  currencies.value = currResp.data.currencies
  const settings = settingsResp.data.company_setting
  if (settings) {
    form.value.currency_id = settings.currency_id
    form.value.language = settings.language ?? 'es'
    form.value.date_format = settings.date_format ?? 'DD/MM/YYYY'
    form.value.time_zone = settings.time_zone ?? ''
  }
})
</script>
