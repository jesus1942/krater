<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <ion-buttons slot="start"><ion-back-button :default-href="isEdit ? `/customers/${id}` : '/customers'" /></ion-buttons>
        <ion-title>{{ isEdit ? 'Editar cliente' : 'Nuevo cliente' }}</ion-title>
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
          <ion-item lines="full"><ion-label position="stacked">Nombre *</ion-label><ion-input v-model="form.name" placeholder="Nombre del cliente" /></ion-item>
          <ion-item lines="full"><ion-label position="stacked">Correo electronico</ion-label><ion-input v-model="form.email" type="email" inputmode="email" /></ion-item>
          <ion-item lines="full"><ion-label position="stacked">Telefono</ion-label><ion-input v-model="form.phone" type="tel" inputmode="tel" /></ion-item>
          <ion-item lines="none"><ion-label position="stacked">Sitio web</ion-label><ion-input v-model="form.website" type="url" inputmode="url" /></ion-item>
        </ion-card-content>
      </ion-card>

      <ion-card>
        <ion-card-header><ion-card-title>Direccion</ion-card-title></ion-card-header>
        <ion-card-content class="ion-no-padding">
          <ion-item lines="full"><ion-label position="stacked">Direccion</ion-label><ion-input v-model="form.billing.address_street_1" /></ion-item>
          <ion-item lines="full"><ion-label position="stacked">Ciudad</ion-label><ion-input v-model="form.billing.city" /></ion-item>
          <ion-item lines="none"><ion-label position="stacked">Pais</ion-label><ion-input v-model="form.billing.country_id" type="number" placeholder="ID del pais" /></ion-item>
        </ion-card-content>
      </ion-card>
    </ion-content>
  </ion-page>
</template>

<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonButtons, IonButton, IonBackButton, IonCard, IonCardHeader, IonCardTitle, IonCardContent, IonItem, IonLabel, IonInput, IonSpinner, toastController } from '@ionic/vue'
import { useCustomersStore } from '@/stores/customers'

const route = useRoute()
const router = useRouter()
const store = useCustomersStore()
const isEdit = computed(() => !!route.params.id)
const id = computed(() => Number(route.params.id))
const saving = ref(false)
const form = ref({ name: '', email: '', phone: '', website: '', billing: { address_street_1: '', city: '', country_id: null as number | null } })

async function save() {
  if (!form.value.name) { const t = await toastController.create({ message: 'El nombre es requerido', duration: 2000, color: 'warning' }); t.present(); return }
  saving.value = true
  try {
    const payload = { name: form.value.name, email: form.value.email, phone: form.value.phone, website: form.value.website, billing: form.value.billing }
    if (isEdit.value) { await store.updateCustomer(id.value, payload); router.replace(`/customers/${id.value}`) }
    else { const c = await store.createCustomer(payload); router.replace(`/customers/${c.id}`) }
  } catch (e: any) {
    const t = await toastController.create({ message: e.response?.data?.message ?? 'Error al guardar', duration: 3000, color: 'danger' })
    t.present()
  } finally { saving.value = false }
}

onMounted(async () => {
  if (isEdit.value) {
    await store.fetchCustomer(id.value)
    const c = store.current
    form.value = { name: c.name, email: c.email ?? '', phone: c.phone ?? '', website: c.website ?? '', billing: { address_street_1: c.billing_address?.address_street_1 ?? '', city: c.billing_address?.city ?? '', country_id: c.billing_address?.country_id ?? null } }
  }
})
</script>
