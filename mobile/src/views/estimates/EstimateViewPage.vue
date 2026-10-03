<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <ion-buttons slot="start"><ion-back-button default-href="/estimates" /></ion-buttons>
        <ion-title>{{ estimate?.estimate_number ?? 'Presupuesto' }}</ion-title>
        <ion-buttons slot="end">
          <ion-button :id="`est-menu-${id}`">
            <ion-icon slot="icon-only" :icon="ellipsisVerticalOutline" />
          </ion-button>
        </ion-buttons>
      </ion-toolbar>
    </ion-header>

    <ion-content color="light">
      <div v-if="loading" class="ion-text-center ion-padding-top"><ion-spinner color="primary" /></div>
      <template v-else-if="estimate">
        <ion-card>
          <ion-card-content>
            <div class="header">
              <div>
                <h2 class="number">{{ estimate.estimate_number }}</h2>
                <p class="customer">{{ estimate.customer?.name }}</p>
              </div>
              <span :class="`status-badge status-${estimate.status?.toLowerCase()}`">{{ statusLabel(estimate.status) }}</span>
            </div>
            <ion-grid>
              <ion-row>
                <ion-col><div class="meta-label">Fecha</div><div class="meta-value">{{ estimate.estimate_date }}</div></ion-col>
                <ion-col><div class="meta-label">Expira</div><div class="meta-value">{{ estimate.expiry_date }}</div></ion-col>
              </ion-row>
            </ion-grid>
          </ion-card-content>
        </ion-card>

        <ion-card>
          <ion-card-header><ion-card-title>Items</ion-card-title></ion-card-header>
          <ion-card-content class="ion-no-padding">
            <ion-list lines="full">
              <ion-item v-for="item in estimate.items" :key="item.id">
                <ion-label><h3>{{ item.name }}</h3><p>{{ item.quantity }} x {{ formatMoney(item.price) }}</p></ion-label>
                <ion-note slot="end">{{ formatMoney(item.total) }}</ion-note>
              </ion-item>
            </ion-list>
          </ion-card-content>
        </ion-card>

        <ion-card>
          <ion-card-content class="ion-no-padding">
            <ion-list lines="full">
              <ion-item><ion-label color="medium">Subtotal</ion-label><ion-note slot="end">{{ formatMoney(estimate.sub_total) }}</ion-note></ion-item>
              <ion-item lines="none"><ion-label><strong>Total</strong></ion-label><ion-note slot="end"><strong>{{ formatMoney(estimate.total) }}</strong></ion-note></ion-item>
            </ion-list>
          </ion-card-content>
        </ion-card>

        <div class="ion-padding action-buttons">
          <ion-button expand="block" color="primary" @click="sendEstimate">
            <ion-icon slot="start" :icon="sendOutline" />Enviar al cliente
          </ion-button>
          <ion-button expand="block" fill="outline" color="success" @click="convertToInvoice">
            <ion-icon slot="start" :icon="documentTextOutline" />Convertir a factura
          </ion-button>
          <ion-button expand="block" fill="outline" :router-link="`/estimates/${id}/edit`">
            <ion-icon slot="start" :icon="createOutline" />Editar
          </ion-button>
        </div>
      </template>
    </ion-content>

    <ion-popover :trigger="`est-menu-${id}`" dismiss-on-select>
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
import { IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonButtons, IonButton, IonBackButton, IonIcon, IonCard, IonCardHeader, IonCardTitle, IonCardContent, IonList, IonItem, IonLabel, IonNote, IonSpinner, IonGrid, IonRow, IonCol, IonPopover, alertController, toastController } from '@ionic/vue'
import { ellipsisVerticalOutline, sendOutline, documentTextOutline, createOutline, trashOutline } from 'ionicons/icons'
import { useEstimatesStore } from '@/stores/estimates'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const store = useEstimatesStore()
const authStore = useAuthStore()
const id = computed(() => Number(route.params.id))
const estimate = computed(() => store.current)
const loading = ref(false)
const statusLabels: Record<string, string> = { DRAFT: 'Borrador', SENT: 'Enviado', VIEWED: 'Visto', ACCEPTED: 'Aceptado', REJECTED: 'Rechazado' }
function statusLabel(s: string) { return statusLabels[s] ?? s }
const currency = authStore.currency
function formatMoney(amount: number) { return `${currency?.symbol ?? '$'}${(amount / 100).toFixed(currency?.precision ?? 2)}` }
async function sendEstimate() {
  await store.sendEstimate(id.value, { subject: `Presupuesto ${estimate.value?.estimate_number}` })
  const t = await toastController.create({ message: 'Presupuesto enviado', duration: 2000, color: 'success' })
  t.present()
}
async function convertToInvoice() {
  const invoice = await store.convertToInvoice(id.value)
  router.replace(`/invoices/${invoice.id}`)
}
async function confirmDelete() {
  const alert = await alertController.create({
    header: 'Eliminar presupuesto',
    message: 'Esta accion no se puede deshacer.',
    buttons: [{ text: 'Cancelar', role: 'cancel' }, { text: 'Eliminar', role: 'destructive', handler: async () => { await store.deleteEstimate(id.value); router.replace('/estimates') } }],
  })
  await alert.present()
}
onMounted(async () => { loading.value = true; await store.fetchEstimate(id.value); loading.value = false })
</script>

<style scoped>
.header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
.number { font-size: 18px; font-weight: 700; color: #111827; margin: 0; }
.customer { color: #6b7280; margin: 4px 0 0; }
.meta-label { font-size: 11px; color: #9ca3af; text-transform: uppercase; }
.meta-value { font-size: 14px; font-weight: 500; color: #374151; }
.action-buttons { display: flex; flex-direction: column; gap: 8px; }
</style>
