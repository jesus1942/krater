<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <ion-buttons slot="start"><ion-back-button default-href="/items" /></ion-buttons>
        <ion-title>{{ isEdit ? 'Editar item' : 'Nuevo item' }}</ion-title>
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
          <ion-item lines="full"><ion-label position="stacked">Nombre *</ion-label><ion-input v-model="form.name" placeholder="Producto o servicio" /></ion-item>
          <ion-item lines="full"><ion-label position="stacked">Precio *</ion-label><ion-input v-model.number="form.price" type="number" min="0" step="0.01" /></ion-item>
          <ion-item lines="full" button @click="openUnitSelect">
            <ion-label position="stacked">Unidad</ion-label>
            <ion-input :value="form.unit?.name ?? ''" readonly placeholder="Seleccionar..." />
            <ion-icon slot="end" :icon="chevronForwardOutline" color="medium" />
          </ion-item>
          <ion-item lines="none"><ion-label position="stacked">Descripcion</ion-label><ion-textarea v-model="form.description" rows="3" /></ion-item>
        </ion-card-content>
      </ion-card>
    </ion-content>

    <ion-modal :is-open="showUnitModal" @didDismiss="showUnitModal = false">
      <ion-header><ion-toolbar><ion-title>Unidad</ion-title><ion-buttons slot="end"><ion-button @click="showUnitModal = false">Cerrar</ion-button></ion-buttons></ion-toolbar></ion-header>
      <ion-content><ion-list><ion-item v-for="u in store.units" :key="u.id" button @click="selectUnit(u)"><ion-label>{{ u.name }}</ion-label></ion-item></ion-list></ion-content>
    </ion-modal>
  </ion-page>
</template>

<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonButtons, IonButton, IonBackButton, IonIcon, IonCard, IonCardContent, IonItem, IonLabel, IonInput, IonTextarea, IonSpinner, IonModal, IonList, toastController } from '@ionic/vue'
import { chevronForwardOutline } from 'ionicons/icons'
import { useItemsStore } from '@/stores/items'
import http from '@/services/http'

const route = useRoute()
const router = useRouter()
const store = useItemsStore()
const isEdit = computed(() => !!route.params.id)
const id = computed(() => Number(route.params.id))
const saving = ref(false)
const showUnitModal = ref(false)
const form = ref({ name: '', price: 0, unit: null as any, unit_id: null as number | null, description: '' })

function openUnitSelect() { if (!store.units.length) store.fetchUnits(); showUnitModal.value = true }
function selectUnit(u: any) { form.value.unit = u; form.value.unit_id = u.id; showUnitModal.value = false }

async function save() {
  saving.value = true
  try {
    const payload = { name: form.value.name, price: Math.round(form.value.price * 100), unit_id: form.value.unit_id, description: form.value.description }
    if (isEdit.value) { await store.updateItem(id.value, payload); router.back() }
    else { await store.createItem(payload); router.back() }
  } catch (e: any) {
    const t = await toastController.create({ message: e.response?.data?.message ?? 'Error al guardar', duration: 3000, color: 'danger' })
    t.present()
  } finally { saving.value = false }
}

onMounted(async () => {
  if (isEdit.value) {
    const { data } = await http.get(`/items/${id.value}`)
    const item = data.item
    form.value = { name: item.name, price: item.price / 100, unit: item.unit, unit_id: item.unit_id, description: item.description ?? '' }
  }
})
</script>
