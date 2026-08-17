<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <ion-buttons slot="start"><ion-back-button default-href="/settings" /></ion-buttons>
        <ion-title>Campos personalizados</ion-title>
        <ion-buttons slot="end">
          <ion-button @click="openCreate"><ion-icon slot="icon-only" :icon="addOutline" /></ion-button>
        </ion-buttons>
      </ion-toolbar>
    </ion-header>
    <ion-content color="light">
      <div v-if="loading" class="ion-text-center ion-padding-top"><ion-spinner color="primary" /></div>
      <ion-list v-else-if="fields.length" lines="none" class="ion-padding-horizontal ion-padding-top">
        <ion-card>
          <ion-list lines="full">
            <ion-item v-for="field in fields" :key="field.id" button @click="openEdit(field)">
              <ion-label>
                <h3>{{ field.name }}</h3>
                <p>{{ field.model_type }} · {{ field.type }}</p>
              </ion-label>
              <ion-note slot="end" :color="field.is_required ? 'danger' : 'medium'">{{ field.is_required ? 'Requerido' : 'Opcional' }}</ion-note>
            </ion-item>
          </ion-list>
        </ion-card>
      </ion-list>
    </ion-content>

    <ion-modal :is-open="showModal" @didDismiss="showModal = false">
      <ion-header>
        <ion-toolbar>
          <ion-title>{{ editing ? 'Editar campo' : 'Nuevo campo' }}</ion-title>
          <ion-buttons slot="end"><ion-button @click="showModal = false">Cancelar</ion-button></ion-buttons>
        </ion-toolbar>
      </ion-header>
      <ion-content class="ion-padding">
        <ion-item lines="full"><ion-label position="stacked">Nombre *</ion-label><ion-input v-model="form.name" /></ion-item>
        <ion-item lines="full">
          <ion-label position="stacked">Aplica a</ion-label>
          <ion-select v-model="form.model_type">
            <ion-select-option value="Invoice">Facturas</ion-select-option>
            <ion-select-option value="Customer">Clientes</ion-select-option>
            <ion-select-option value="Payment">Cobros</ion-select-option>
            <ion-select-option value="Expense">Gastos</ion-select-option>
          </ion-select>
        </ion-item>
        <ion-item lines="full">
          <ion-label position="stacked">Tipo</ion-label>
          <ion-select v-model="form.type">
            <ion-select-option value="Input">Texto</ion-select-option>
            <ion-select-option value="TextArea">Texto largo</ion-select-option>
            <ion-select-option value="Number">Numero</ion-select-option>
            <ion-select-option value="Date">Fecha</ion-select-option>
            <ion-select-option value="Switch">Si/No</ion-select-option>
          </ion-select>
        </ion-item>
        <ion-item lines="none">
          <ion-label>Requerido</ion-label>
          <ion-toggle v-model="form.is_required" slot="end" />
        </ion-item>
        <ion-button expand="block" class="ion-margin-top" color="primary" @click="save">Guardar</ion-button>
        <ion-button v-if="editing" expand="block" fill="outline" color="danger" class="ion-margin-top" @click="remove">Eliminar</ion-button>
      </ion-content>
    </ion-modal>
  </ion-page>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonButtons, IonButton, IonBackButton, IonIcon, IonList, IonItem, IonLabel, IonNote, IonSpinner, IonModal, IonInput, IonSelect, IonSelectOption, IonToggle, IonCard, toastController } from '@ionic/vue'
import { addOutline } from 'ionicons/icons'
import http from '@/services/http'

const fields = ref<any[]>([])
const loading = ref(false)
const showModal = ref(false)
const editing = ref<any>(null)
const form = ref({ name: '', model_type: 'Invoice', type: 'Input', is_required: false })

async function load() { loading.value = true; const { data } = await http.get('/custom-fields'); fields.value = data.customFields; loading.value = false }
function openCreate() { editing.value = null; form.value = { name: '', model_type: 'Invoice', type: 'Input', is_required: false }; showModal.value = true }
function openEdit(f: any) { editing.value = f; form.value = { name: f.name, model_type: f.model_type, type: f.type, is_required: f.is_required }; showModal.value = true }
async function save() {
  try {
    if (editing.value) { await http.put(`/custom-fields/${editing.value.id}`, form.value) }
    else { await http.post('/custom-fields', form.value) }
    showModal.value = false; await load()
  } catch (e: any) { const t = await toastController.create({ message: e.response?.data?.message ?? 'Error', duration: 3000, color: 'danger' }); t.present() }
}
async function remove() { await http.delete(`/custom-fields/${editing.value.id}`); showModal.value = false; await load() }
onMounted(() => load())
</script>
