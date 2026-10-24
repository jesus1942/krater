<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <ion-buttons slot="start"><ion-back-button default-href="/settings" /></ion-buttons>
        <ion-title>Metodos de pago</ion-title>
        <ion-buttons slot="end">
          <ion-button @click="openCreate"><ion-icon slot="icon-only" :icon="addOutline" /></ion-button>
        </ion-buttons>
      </ion-toolbar>
    </ion-header>
    <ion-content color="light">
      <div v-if="loading" class="ion-text-center ion-padding-top"><ion-spinner color="primary" /></div>
      <ion-list v-else-if="methods.length" lines="none" class="ion-padding-horizontal ion-padding-top">
        <ion-card>
          <ion-list lines="full">
            <ion-item v-for="m in methods" :key="m.id" button @click="openEdit(m)">
              <ion-label>{{ m.name }}</ion-label>
            </ion-item>
          </ion-list>
        </ion-card>
      </ion-list>
    </ion-content>

    <ion-modal :is-open="showModal" @didDismiss="showModal = false">
      <ion-header>
        <ion-toolbar>
          <ion-title>{{ editing ? 'Editar metodo' : 'Nuevo metodo' }}</ion-title>
          <ion-buttons slot="end"><ion-button @click="showModal = false">Cancelar</ion-button></ion-buttons>
        </ion-toolbar>
      </ion-header>
      <ion-content class="ion-padding">
        <ion-item lines="none"><ion-label position="stacked">Nombre *</ion-label><ion-input v-model="form.name" /></ion-item>
        <ion-button expand="block" class="ion-margin-top" color="primary" @click="save">Guardar</ion-button>
        <ion-button v-if="editing" expand="block" fill="outline" color="danger" class="ion-margin-top" @click="remove">Eliminar</ion-button>
      </ion-content>
    </ion-modal>
  </ion-page>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonButtons, IonButton, IonBackButton, IonIcon, IonList, IonItem, IonLabel, IonSpinner, IonModal, IonInput, IonCard, toastController } from '@ionic/vue'
import { addOutline } from 'ionicons/icons'
import http from '@/services/http'

const methods = ref<any[]>([])
const loading = ref(false)
const showModal = ref(false)
const editing = ref<any>(null)
const form = ref({ name: '' })

async function load() { loading.value = true; const { data } = await http.get('/payment-methods'); methods.value = data.payment_methods; loading.value = false }
function openCreate() { editing.value = null; form.value = { name: '' }; showModal.value = true }
function openEdit(m: any) { editing.value = m; form.value = { name: m.name }; showModal.value = true }
async function save() {
  try {
    if (editing.value) { await http.put(`/payment-methods/${editing.value.id}`, form.value) }
    else { await http.post('/payment-methods', form.value) }
    showModal.value = false; await load()
  } catch (e: any) { const t = await toastController.create({ message: e.response?.data?.message ?? 'Error', duration: 3000, color: 'danger' }); t.present() }
}
async function remove() { await http.delete(`/payment-methods/${editing.value.id}`); showModal.value = false; await load() }
onMounted(() => load())
</script>
