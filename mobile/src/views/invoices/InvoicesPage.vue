<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <ion-title>Facturas</ion-title>
        <ion-buttons slot="end">
          <ion-button router-link="/invoices/create">
            <ion-icon slot="icon-only" :icon="addOutline" />
          </ion-button>
        </ion-buttons>
      </ion-toolbar>
      <ion-toolbar>
        <ion-searchbar
          v-model="search"
          placeholder="Buscar factura..."
          debounce="400"
          @ionInput="onSearch"
        />
      </ion-toolbar>
    </ion-header>

    <ion-content color="light">
      <ion-refresher slot="fixed" @ionRefresh="onRefresh">
        <ion-refresher-content />
      </ion-refresher>

      <div class="filter-chips ion-padding-horizontal">
        <ion-chip
          v-for="s in statuses"
          :key="s.value"
          :color="activeStatus === s.value ? 'primary' : undefined"
          :outline="activeStatus !== s.value"
          @click="setStatus(s.value)"
        >
          {{ s.label }}
        </ion-chip>
      </div>

      <div v-if="store.loading" class="ion-text-center ion-padding-top">
        <ion-spinner color="primary" />
      </div>

      <ion-list v-else-if="store.list.length" lines="none" class="ion-padding-horizontal">
        <ion-card
          v-for="invoice in store.list"
          :key="invoice.id"
          button
          @click="$router.push(`/invoices/${invoice.id}`)"
        >
          <ion-card-content>
            <div class="invoice-row">
              <div class="invoice-info">
                <div class="invoice-number">{{ invoice.invoice_number }}</div>
                <div class="invoice-customer">{{ invoice.customer?.name }}</div>
                <div class="invoice-date">Vence: {{ invoice.due_date }}</div>
              </div>
              <div class="invoice-right">
                <div class="invoice-amount">{{ formatMoney(invoice.total) }}</div>
                <span :class="`status-badge status-${invoice.status?.toLowerCase()}`">
                  {{ statusLabel(invoice.status) }}
                </span>
              </div>
            </div>
          </ion-card-content>
        </ion-card>

        <ion-infinite-scroll @ionInfinite="loadMore">
          <ion-infinite-scroll-content />
        </ion-infinite-scroll>
      </ion-list>

      <div v-else class="empty-state">
        <ion-icon :icon="documentTextOutline" />
        <p>No hay facturas</p>
        <ion-button fill="outline" router-link="/invoices/create">Crear factura</ion-button>
      </div>
    </ion-content>
  </ion-page>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import {
  IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonButtons, IonButton,
  IonIcon, IonList, IonCard, IonCardContent, IonSearchbar, IonChip,
  IonSpinner, IonRefresher, IonRefresherContent, IonInfiniteScroll, IonInfiniteScrollContent,
} from '@ionic/vue'
import { addOutline, documentTextOutline } from 'ionicons/icons'
import { useInvoicesStore } from '@/stores/invoices'
import { useAuthStore } from '@/stores/auth'

const store = useInvoicesStore()
const authStore = useAuthStore()
const search = ref('')
const activeStatus = ref('')

const statuses = [
  { label: 'Todas', value: '' },
  { label: 'Borrador', value: 'DRAFT' },
  { label: 'Enviadas', value: 'SENT' },
  { label: 'Sin pagar', value: 'UNPAID' },
  { label: 'Vencidas', value: 'OVERDUE' },
  { label: 'Pagadas', value: 'PAID' },
]

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

async function load(page = 1) {
  await store.fetchInvoices({ page, search: search.value, status: activeStatus.value || undefined })
}

function onSearch() { load() }
function setStatus(s: string) { activeStatus.value = s; load() }

async function onRefresh(event: any) {
  await load()
  event.target.complete()
}

async function loadMore(event: any) {
  const nextPage = store.meta.current_page + 1
  if (nextPage > store.meta.last_page) { event.target.complete(); return }
  const { data } = await import('@/services/http').then(m => m.default.get('/invoices', { params: { page: nextPage, search: search.value, status: activeStatus.value || undefined } }))
  store.list.push(...data.invoices.data)
  store.meta.current_page = nextPage
  event.target.complete()
}

onMounted(() => load())
</script>

<style scoped>
.filter-chips { display: flex; gap: 8px; overflow-x: auto; padding: 8px 0; }
.filter-chips::-webkit-scrollbar { display: none; }
.invoice-row { display: flex; justify-content: space-between; align-items: flex-start; }
.invoice-number { font-weight: 600; font-size: 15px; color: #111827; }
.invoice-customer { font-size: 13px; color: #6b7280; margin-top: 2px; }
.invoice-date { font-size: 12px; color: #9ca3af; margin-top: 2px; }
.invoice-right { text-align: right; }
.invoice-amount { font-weight: 700; font-size: 16px; color: #111827; }
.empty-state { display: flex; flex-direction: column; align-items: center; padding: 64px 24px; color: #9ca3af; }
.empty-state ion-icon { font-size: 64px; margin-bottom: 16px; }
</style>
