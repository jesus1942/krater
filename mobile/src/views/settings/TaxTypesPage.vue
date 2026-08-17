<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <ion-buttons slot="start"><ion-back-button default-href="/settings" /></ion-buttons>
        <ion-title>Tipos de impuesto</ion-title>
        <ion-buttons slot="end">
          <ion-button @click="openCreate"><ion-icon slot="icon-only" :icon="addOutline" /></ion-button>
        </ion-buttons>
      </ion-toolbar>
    </ion-header>
    <ion-content color="light">
      <div v-if="loading" class="ion-text-center ion-padding-top"><ion-spinner color="primary" /></div>
      <ion-list v-else-if="taxTypes.length" lines="none" class="ion-padding-horizontal ion-padding-top">
        <ion-card>
          <ion-list lines="full">
            <ion-item v-for="tax in taxTypes" :key="tax.id" button @click="openEdit(tax)">
              <ion-label><h3>{{ tax.name }}</h3></ion-label>
              <ion-note slot="end">{{ tax.percent }}%</ion-note>
            </ion-item>
          </ion-list>
        </ion-card>
      </ion-list>
    </ion-content>

    <ion-modal :is-open="showModal" @didDismiss="showModal = false">
      <ion-header>
        <ion-toolbar>
          <ion-title>{{ editing ? 'Editar impuesto' : 'Nuevo impuesto' }}</ion-title>
          <ion-buttons slot="end"><ion-button @click="showModal = false">Cancelar</ion-button></ion-buttons>
        </ion-toolbar>
      </ion-header>
      <ion-content class="ion-padding">
        <ion-item lines="full"><ion-label position="stacked">Nombre *</ion-label><ion-input v-model="form.name" /></ion-item>
        <ion-item lines="none"><ion-label position="stacked">Porcentaje *</ion-label><ion-input v-model.number="form.percent" type="number" min="0" max="100" step="0.01" /></ion-item>
        <ion-button expand="block" class="ion-margin-top" color="primary" @click="saveType">Guardar</ion-button>
        <ion-button v-if="editing" expand="block" fill="outline" color="danger" class="ion-margin-top" @click="deleteType">Eliminar</ion-button>
      </ion-content>
    </ion-modal>
  </ion-page>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonButtons, IonButton, IonBackButton, IonIcon, IonList, IonItem, IonLabel, IonNote, IonSpinner, IonModal, IonInput, IonCard, toastController } from '@ionic/vue'
import { addOutline } from 'ionicons/icons'
import http from '@/services/http'

const taxTypes = ref<any[]>([])
const loading = ref(false)
const showModal = ref(false)
const editing = ref<any>(null)
const form = ref({ name: '', percent: 0 })

async function load() { loading.value = true; const { data } = await http.get('/tax-types'); taxTypes.value = data.taxTypes; loading.value = false }

function openCreate() { editing.value = null; form.value = { name: '', percent: 0 }; showModal.value = true }
function openEdit(tax: any) { editing.value = tax; form.value = { name: tax.name, percent: tax.percent }; showModal.value = true }

async function saveType() {
  try {
    if (editing.value) { await http.put(`/tax-types/${editing.value.id}`, form.value) }
    else { await http.post('/tax-types', form.value) }
    showModal.value = false; await load()
  } catch (e: any) {
    const t = await toastController.create({ message: e.response?.data?.message ?? 'Error', duration: 3000, color: 'danger' })
    t.present()
  }
}

async function deleteType() {
  await http.delete(`/tax-types/${editing.value.id}`)
  showModal.value = false; await load()
}

onMounted(() => load())
</script>
