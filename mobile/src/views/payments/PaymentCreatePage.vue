<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <ion-buttons slot="start"><ion-back-button default-href="/payments" /></ion-buttons>
        <ion-title>Nuevo cobro</ion-title>
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
          <ion-item lines="full"><ion-label position="stacked">Numero</ion-label><ion-input v-model="form.payment_number" /></ion-item>
          <ion-item lines="full"><ion-label position="stacked">Fecha *</ion-label><ion-input v-model="form.payment_date" type="date" /></ion-item>
          <ion-item lines="full"><ion-label position="stacked">Monto *</ion-label><ion-input v-model.number="form.amount" type="number" min="0" step="0.01" /></ion-item>
          <ion-item lines="full" button @click="openCustomerSelect">
            <ion-label position="stacked">Cliente</ion-label>
            <ion-input :value="form.customer?.name ?? ''" readonly placeholder="Seleccionar..." />
            <ion-icon slot="end" :icon="chevronForwardOutline" color="medium" />
          </ion-item>
          <ion-item lines="full" button @click="openMethodSelect">
            <ion-label position="stacked">Metodo de pago</ion-label>
            <ion-input :value="form.method?.name ?? ''" readonly placeholder="Seleccionar..." />
            <ion-icon slot="end" :icon="chevronForwardOutline" color="medium" />
          </ion-item>
          <ion-item lines="none"><ion-label position="stacked">Notas</ion-label><ion-textarea v-model="form.notes" rows="2" /></ion-item>
        </ion-card-content>
      </ion-card>
    </ion-content>

    <ion-modal :is-open="showCustomerModal" @didDismiss="showCustomerModal = false">
      <ion-header><ion-toolbar><ion-title>Cliente</ion-title><ion-buttons slot="end"><ion-button @click="showCustomerModal = false">Cerrar</ion-button></ion-buttons></ion-toolbar></ion-header>
      <ion-content><ion-list><ion-item v-for="c in customers.list" :key="c.id" button @click="selectCustomer(c)"><ion-label>{{ c.name }}</ion-label></ion-item></ion-list></ion-content>
    </ion-modal>

    <ion-modal :is-open="showMethodModal" @didDismiss="showMethodModal = false">
      <ion-header><ion-toolbar><ion-title>Metodo</ion-title><ion-buttons slot="end"><ion-button @click="showMethodModal = false">Cerrar</ion-button></ion-buttons></ion-toolbar></ion-header>
      <ion-content><ion-list><ion-item v-for="m in store.methods" :key="m.id" button @click="selectMethod(m)"><ion-label>{{ m.name }}</ion-label></ion-item></ion-list></ion-content>
    </ion-modal>
  </ion-page>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonButtons, IonButton, IonBackButton, IonIcon, IonCard, IonCardContent, IonItem, IonLabel, IonInput, IonTextarea, IonSpinner, IonModal, IonList, toastController } from '@ionic/vue'
import { chevronForwardOutline } from 'ionicons/icons'
import { usePaymentsStore } from '@/stores/payments'
import { useCustomersStore } from '@/stores/customers'
import http from '@/services/http'

const router = useRouter()
const store = usePaymentsStore()
const customers = useCustomersStore()
const saving = ref(false)
const showCustomerModal = ref(false)
const showMethodModal = ref(false)
const form = ref({ payment_number: '', payment_date: new Date().toISOString().split('T')[0], amount: 0, customer: null as any, customer_id: null as number | null, method: null as any, payment_method_id: null as number | null, notes: '' })

function openCustomerSelect() { if (!customers.list.length) customers.fetchCustomers(); showCustomerModal.value = true }
function selectCustomer(c: any) { form.value.customer = c; form.value.customer_id = c.id; showCustomerModal.value = false }
function openMethodSelect() { if (!store.methods.length) store.fetchMethods(); showMethodModal.value = true }
function selectMethod(m: any) { form.value.method = m; form.value.payment_method_id = m.id; showMethodModal.value = false }

async function save() {
  saving.value = true
  try {
    const payload = { payment_number: form.value.payment_number, payment_date: form.value.payment_date, amount: Math.round(form.value.amount * 100), customer_id: form.value.customer_id, payment_method_id: form.value.payment_method_id, notes: form.value.notes }
    const p = await store.createPayment(payload)
    router.replace(`/payments/${p.id}`)
  } catch (e: any) {
    const t = await toastController.create({ message: e.response?.data?.message ?? 'Error al guardar', duration: 3000, color: 'danger' })
    t.present()
  } finally { saving.value = false }
}

onMounted(async () => {
  const { data } = await http.get('/next-number', { params: { type: 'payment' } })
  form.value.payment_number = data.nextNumber
})
</script>
