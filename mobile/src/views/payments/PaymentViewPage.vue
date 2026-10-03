<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <ion-buttons slot="start"><ion-back-button default-href="/payments" /></ion-buttons>
        <ion-title>{{ payment?.payment_number ?? 'Cobro' }}</ion-title>
        <ion-buttons slot="end">
          <ion-button :id="`pay-menu-${id}`"><ion-icon slot="icon-only" :icon="ellipsisVerticalOutline" /></ion-button>
        </ion-buttons>
      </ion-toolbar>
    </ion-header>

    <ion-content color="light">
      <div v-if="loading" class="ion-text-center ion-padding-top"><ion-spinner color="primary" /></div>
      <template v-else-if="payment">
        <ion-card>
          <ion-card-content>
            <div class="amount-display">{{ formatMoney(payment.amount) }}</div>
            <p class="customer">{{ payment.customer?.name }}</p>
            <div class="meta">
              <span>{{ payment.payment_date }}</span>
              <span v-if="payment.payment_method">· {{ payment.payment_method.name }}</span>
            </div>
          </ion-card-content>
        </ion-card>

        <ion-card v-if="payment.invoice">
          <ion-card-header><ion-card-title>Factura relacionada</ion-card-title></ion-card-header>
          <ion-card-content class="ion-no-padding">
            <ion-item button :router-link="`/invoices/${payment.invoice.id}`" lines="none">
              <ion-label>{{ payment.invoice.invoice_number }}</ion-label>
              <ion-note slot="end">{{ formatMoney(payment.invoice.total) }}</ion-note>
            </ion-item>
          </ion-card-content>
        </ion-card>

        <div class="ion-padding">
          <ion-button expand="block" fill="outline" @click="sendReceipt">
            <ion-icon slot="start" :icon="sendOutline" />Enviar recibo
          </ion-button>
        </div>
      </template>
    </ion-content>

    <ion-popover :trigger="`pay-menu-${id}`" dismiss-on-select>
      <ion-list>
        <ion-item button color="danger" @click="confirmDelete">
          <ion-icon slot="start" :icon="trashOutline" color="danger" />
          <ion-label color="danger">Eliminar</ion-label>
        </ion-item>
      </ion-list>
    </ion-popover>
  </ion-page>
</template>

<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonButtons, IonButton, IonBackButton, IonIcon, IonCard, IonCardHeader, IonCardTitle, IonCardContent, IonItem, IonLabel, IonNote, IonSpinner, IonPopover, IonList, alertController, toastController } from '@ionic/vue'
import { ellipsisVerticalOutline, sendOutline, trashOutline } from 'ionicons/icons'
import { usePaymentsStore } from '@/stores/payments'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const store = usePaymentsStore()
const authStore = useAuthStore()
const id = computed(() => Number(route.params.id))
const payment = computed(() => store.current)
const loading = ref(false)
const currency = authStore.currency
function formatMoney(amount: number) { return `${currency?.symbol ?? '$'}${(amount / 100).toFixed(currency?.precision ?? 2)}` }
async function sendReceipt() {
  await store.sendReceipt(id.value, {})
  const t = await toastController.create({ message: 'Recibo enviado', duration: 2000, color: 'success' })
  t.present()
}
async function confirmDelete() {
  const alert = await alertController.create({
    header: 'Eliminar cobro', message: 'Esta accion no se puede deshacer.',
    buttons: [{ text: 'Cancelar', role: 'cancel' }, { text: 'Eliminar', role: 'destructive', handler: async () => { await store.deletePayment(id.value); router.replace('/payments') } }],
  })
  await alert.present()
}
onMounted(async () => { loading.value = true; await store.fetchPayment(id.value); loading.value = false })
</script>

<style scoped>
.amount-display { font-size: 32px; font-weight: 700; color: #111827; }
.customer { color: #6b7280; margin: 4px 0; }
.meta { font-size: 13px; color: #9ca3af; }
</style>
