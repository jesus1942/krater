<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <ion-buttons slot="start">
          <ion-back-button default-href="/tabs/invoices" />
        </ion-buttons>
        <ion-title>{{ invoice?.invoice_number ?? 'Factura' }}</ion-title>
        <ion-buttons slot="end">
          <ion-button :id="`invoice-menu-${id}`">
            <ion-icon slot="icon-only" :icon="ellipsisVerticalOutline" />
          </ion-button>
        </ion-buttons>
      </ion-toolbar>
    </ion-header>

    <ion-content color="light">
      <div v-if="loading" class="ion-text-center ion-padding-top">
        <ion-spinner color="primary" />
      </div>

      <template v-else-if="invoice">
        <!-- Status + header -->
        <ion-card>
          <ion-card-content>
            <div class="invoice-header">
              <div>
                <h2 class="invoice-number">{{ invoice.invoice_number }}</h2>
                <p class="customer-name">{{ invoice.customer?.name }}</p>
              </div>
              <span :class="`status-badge status-${invoice.status?.toLowerCase()}`">
                {{ statusLabel(invoice.status) }}
              </span>
            </div>
            <ion-grid>
              <ion-row>
                <ion-col>
                  <div class="meta-label">Fecha</div>
                  <div class="meta-value">{{ invoice.invoice_date }}</div>
                </ion-col>
                <ion-col>
                  <div class="meta-label">Vencimiento</div>
                  <div class="meta-value">{{ invoice.due_date }}</div>
                </ion-col>
              </ion-row>
            </ion-grid>
          </ion-card-content>
        </ion-card>

        <!-- Line items -->
        <ion-card>
          <ion-card-header><ion-card-title>Items</ion-card-title></ion-card-header>
          <ion-card-content class="ion-no-padding">
            <ion-list lines="full">
              <ion-item v-for="item in invoice.items" :key="item.id">
                <ion-label>
                  <h3>{{ item.name }}</h3>
                  <p>{{ item.quantity }} x {{ formatMoney(item.price) }}</p>
                </ion-label>
                <ion-note slot="end">{{ formatMoney(item.total) }}</ion-note>
              </ion-item>
            </ion-list>
          </ion-card-content>
        </ion-card>

        <!-- Totals -->
        <ion-card>
          <ion-card-content class="ion-no-padding">
            <ion-list lines="full">
              <ion-item>
                <ion-label color="medium">Subtotal</ion-label>
                <ion-note slot="end">{{ formatMoney(invoice.sub_total) }}</ion-note>
              </ion-item>
              <ion-item v-for="tax in invoice.taxes" :key="tax.id">
                <ion-label color="medium">{{ tax.name }} ({{ tax.percent }}%)</ion-label>
                <ion-note slot="end">{{ formatMoney(tax.amount) }}</ion-note>
              </ion-item>
              <ion-item v-if="invoice.discount_val">
                <ion-label color="danger">Descuento</ion-label>
                <ion-note slot="end" color="danger">-{{ formatMoney(invoice.discount_val) }}</ion-note>
              </ion-item>
              <ion-item lines="none">
                <ion-label><strong>Total</strong></ion-label>
                <ion-note slot="end"><strong>{{ formatMoney(invoice.total) }}</strong></ion-note>
              </ion-item>
            </ion-list>
          </ion-card-content>
        </ion-card>

        <!-- Actions -->
        <div class="ion-padding action-buttons">
          <ion-button expand="block" color="primary" @click="viewPdf">
            <ion-icon slot="start" :icon="documentOutline" />
            Ver PDF
          </ion-button>
          <ion-button expand="block" fill="outline" @click="sendInvoice">
            <ion-icon slot="start" :icon="sendOutline" />
            Enviar al cliente
          </ion-button>
          <ion-button expand="block" fill="outline" router-link="`/invoices/${id}/edit`">
            <ion-icon slot="start" :icon="createOutline" />
            Editar
          </ion-button>
        </div>
      </template>
    </ion-content>

    <!-- Context menu -->
    <ion-popover :trigger="`invoice-menu-${id}`" dismiss-on-select>
      <ion-list>
        <ion-item button @click="cloneInvoice">
          <ion-icon slot="start" :icon="copyOutline" />
          <ion-label>Duplicar</ion-label>
        </ion-item>
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
import {
  IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonButtons, IonButton,
  IonBackButton, IonIcon, IonCard, IonCardHeader, IonCardTitle, IonCardContent,
  IonList, IonItem, IonLabel, IonNote, IonSpinner, IonGrid, IonRow, IonCol,
  IonPopover, alertController, toastController,
} from '@ionic/vue'
import {
  ellipsisVerticalOutline, documentOutline, sendOutline, createOutline,
  copyOutline, trashOutline,
} from 'ionicons/icons'
import { useInvoicesStore } from '@/stores/invoices'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const store = useInvoicesStore()
const authStore = useAuthStore()

const id = computed(() => Number(route.params.id))
const invoice = computed(() => store.current)
const loading = ref(false)

const statusLabels: Record<string, string> = {
  DRAFT: 'Borrador', SENT: 'Enviada', VIEWED: 'Vista',
  UNPAID: 'Sin pagar', OVERDUE: 'Vencida', PAID: 'Pagada',
  PARTIALLY_PAID: 'Pago parcial', COMPLETED: 'Completada',
}
function statusLabel(s: string) { return statusLabels[s] ?? s }

const currency = authStore.currency
function formatMoney(amount: number) {
  const sym = currency?.symbol ?? '$'
  const precision = currency?.precision ?? 2
  return `${sym}${(amount / 100).toFixed(precision)}`
}

function viewPdf() {
  window.open(`/invoices/pdf/${invoice.value?.unique_hash}`, '_blank')
}

async function sendInvoice() {
  const toast = await toastController.create({ message: 'Factura enviada al cliente', duration: 2000, color: 'success' })
  await store.sendInvoice(id.value, { subject: `Factura ${invoice.value?.invoice_number}` })
  toast.present()
}

async function cloneInvoice() {
  const cloned = await store.cloneInvoice(id.value)
  router.push(`/invoices/${cloned.id}`)
}

async function confirmDelete() {
  const alert = await alertController.create({
    header: 'Eliminar factura',
    message: 'Esta accion no se puede deshacer.',
    buttons: [
      { text: 'Cancelar', role: 'cancel' },
      { text: 'Eliminar', role: 'destructive', handler: async () => {
        await store.deleteInvoice(id.value)
        router.replace('/tabs/invoices')
      }},
    ],
  })
  await alert.present()
}

onMounted(async () => {
  loading.value = true
  await store.fetchInvoice(id.value)
  loading.value = false
})
</script>

<style scoped>
.invoice-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
.invoice-number { font-size: 18px; font-weight: 700; color: #111827; margin: 0; }
.customer-name { color: #6b7280; margin: 4px 0 0; }
.meta-label { font-size: 11px; color: #9ca3af; text-transform: uppercase; }
.meta-value { font-size: 14px; font-weight: 500; color: #374151; }
.action-buttons { display: flex; flex-direction: column; gap: 8px; }
</style>
