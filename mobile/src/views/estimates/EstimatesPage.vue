<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <ion-buttons slot="start">
          <ion-back-button default-href="/tabs/more" />
        </ion-buttons>
        <ion-title>Presupuestos</ion-title>
        <ion-buttons slot="end">
          <ion-button router-link="/estimates/create">
            <ion-icon slot="icon-only" :icon="addOutline" />
          </ion-button>
        </ion-buttons>
      </ion-toolbar>
      <ion-toolbar>
        <ion-searchbar v-model="search" placeholder="Buscar presupuesto..." debounce="400" @ionInput="load" />
      </ion-toolbar>
    </ion-header>

    <ion-content color="light">
      <ion-refresher slot="fixed" @ionRefresh="onRefresh"><ion-refresher-content /></ion-refresher>

      <div class="filter-chips ion-padding-horizontal">
        <ion-chip v-for="s in statuses" :key="s.value" :color="activeStatus === s.value ? 'primary' : undefined" :outline="activeStatus !== s.value" @click="setStatus(s.value)">
          {{ s.label }}
        </ion-chip>
      </div>

      <div v-if="store.loading" class="ion-text-center ion-padding-top"><ion-spinner color="primary" /></div>

      <ion-list v-else-if="store.list.length" lines="none" class="ion-padding-horizontal">
        <ion-card v-for="estimate in store.list" :key="estimate.id" button @click="$router.push(`/estimates/${estimate.id}`)">
          <ion-card-content>
            <div class="row">
              <div>
                <div class="number">{{ estimate.estimate_number }}</div>
                <div class="sub">{{ estimate.customer?.name }}</div>
                <div class="date">{{ estimate.estimate_date }}</div>
              </div>
              <div class="right">
                <div class="amount">{{ formatMoney(estimate.total) }}</div>
                <span :class="`status-badge status-${estimate.status?.toLowerCase()}`">{{ statusLabel(estimate.status) }}</span>
              </div>
            </div>
          </ion-card-content>
        </ion-card>
      </ion-list>

      <div v-else class="empty-state">
        <ion-icon :icon="receiptOutline" />
        <p>No hay presupuestos</p>
        <ion-button fill="outline" router-link="/estimates/create">Crear presupuesto</ion-button>
      </div>
    </ion-content>
  </ion-page>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonButtons, IonButton, IonBackButton, IonIcon, IonList, IonCard, IonCardContent, IonSearchbar, IonChip, IonSpinner, IonRefresher, IonRefresherContent } from '@ionic/vue'
import { addOutline, receiptOutline } from 'ionicons/icons'
import { useEstimatesStore } from '@/stores/estimates'
import { useAuthStore } from '@/stores/auth'

const store = useEstimatesStore()
const authStore = useAuthStore()
const search = ref('')
const activeStatus = ref('')
const statuses = [
  { label: 'Todos', value: '' }, { label: 'Borrador', value: 'DRAFT' },
  { label: 'Enviados', value: 'SENT' }, { label: 'Aceptados', value: 'ACCEPTED' },
  { label: 'Rechazados', value: 'REJECTED' },
]
const statusLabels: Record<string, string> = { DRAFT: 'Borrador', SENT: 'Enviado', VIEWED: 'Visto', ACCEPTED: 'Aceptado', REJECTED: 'Rechazado' }
function statusLabel(s: string) { return statusLabels[s] ?? s }
const currency = authStore.currency
function formatMoney(amount: number) { return `${currency?.symbol ?? '$'}${(amount / 100).toFixed(currency?.precision ?? 2)}` }
async function load() { await store.fetchEstimates({ search: search.value, status: activeStatus.value || undefined }) }
function setStatus(s: string) { activeStatus.value = s; load() }
async function onRefresh(event: any) { await load(); event.target.complete() }
onMounted(() => load())
</script>

<style scoped>
.filter-chips { display: flex; gap: 8px; overflow-x: auto; padding: 8px 0; }
.filter-chips::-webkit-scrollbar { display: none; }
.row { display: flex; justify-content: space-between; align-items: flex-start; }
.number { font-weight: 600; font-size: 15px; color: #111827; }
.sub { font-size: 13px; color: #6b7280; margin-top: 2px; }
.date { font-size: 12px; color: #9ca3af; margin-top: 2px; }
.right { text-align: right; }
.amount { font-weight: 700; font-size: 16px; color: #111827; }
.empty-state { display: flex; flex-direction: column; align-items: center; padding: 64px 24px; color: #9ca3af; }
.empty-state ion-icon { font-size: 64px; margin-bottom: 16px; }
</style>
