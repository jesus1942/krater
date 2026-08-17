<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <ion-buttons slot="start"><ion-back-button default-href="/settings" /></ion-buttons>
        <ion-title>Datos de la empresa</ion-title>
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
          <ion-item lines="full"><ion-label position="stacked">Nombre de la empresa *</ion-label><ion-input v-model="form.name" /></ion-item>
          <ion-item lines="full"><ion-label position="stacked">Telefono</ion-label><ion-input v-model="form.phone" type="tel" /></ion-item>
          <ion-item lines="full"><ion-label position="stacked">Sitio web</ion-label><ion-input v-model="form.website" type="url" /></ion-item>
          <ion-item lines="none"><ion-label position="stacked">Numero fiscal</ion-label><ion-input v-model="form.vat_id" /></ion-item>
        </ion-card-content>
      </ion-card>
      <ion-card>
        <ion-card-header><ion-card-title>Direccion</ion-card-title></ion-card-header>
        <ion-card-content class="ion-no-padding">
          <ion-item lines="full"><ion-label position="stacked">Calle</ion-label><ion-input v-model="form.address.address_street_1" /></ion-item>
          <ion-item lines="full"><ion-label position="stacked">Ciudad</ion-label><ion-input v-model="form.address.city" /></ion-item>
          <ion-item lines="full"><ion-label position="stacked">Estado / Provincia</ion-label><ion-input v-model="form.address.state" /></ion-item>
          <ion-item lines="none"><ion-label position="stacked">Codigo postal</ion-label><ion-input v-model="form.address.zip" /></ion-item>
        </ion-card-content>
      </ion-card>
    </ion-content>
  </ion-page>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonButtons, IonButton, IonBackButton, IonCard, IonCardHeader, IonCardTitle, IonCardContent, IonItem, IonLabel, IonInput, IonSpinner, toastController } from '@ionic/vue'
import http from '@/services/http'

const saving = ref(false)
const form = ref({ name: '', phone: '', website: '', vat_id: '', address: { address_street_1: '', city: '', state: '', zip: '' } })

async function save() {
  saving.value = true
  try {
    await http.put('/company', form.value)
    const t = await toastController.create({ message: 'Empresa actualizada', duration: 2000, color: 'success' })
    t.present()
  } catch (e: any) {
    const t = await toastController.create({ message: e.response?.data?.message ?? 'Error al guardar', duration: 3000, color: 'danger' })
    t.present()
  } finally { saving.value = false }
}

onMounted(async () => {
  const { data } = await http.get('/me')
  const company = data.company
  if (company) {
    form.value.name = company.name ?? ''
    form.value.phone = company.phone ?? ''
    form.value.website = company.website ?? ''
    form.value.vat_id = company.vat_id ?? ''
    if (company.address) {
      form.value.address.address_street_1 = company.address.address_street_1 ?? ''
      form.value.address.city = company.address.city ?? ''
      form.value.address.state = company.address.state ?? ''
      form.value.address.zip = company.address.zip ?? ''
    }
  }
})
</script>
