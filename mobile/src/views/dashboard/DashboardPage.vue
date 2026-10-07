<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <ion-title>Dashboard</ion-title>
        <ion-buttons slot="end">
          <ion-button @click="refresh">
            <ion-icon slot="icon-only" :icon="refreshOutline" />
          </ion-button>
        </ion-buttons>
      </ion-toolbar>
    </ion-header>

    <ion-content color="light">
      <ion-refresher slot="fixed" @ionRefresh="onRefresh">
        <ion-refresher-content />
      </ion-refresher>

      <div v-if="loading" class="ion-text-center ion-padding-top">
        <ion-spinner color="primary" />
      </div>

      <template v-else>
        <!-- Stats grid -->
        <div class="stats-grid ion-padding-horizontal ion-padding-top">
          <div class="stat-card" @click="$router.push('/tabs/invoices')">
            <div class="stat-value">{{ formatMoney(store.totalDueAmount) }}</div>
            <div class="stat-label">Monto Pendiente</div>
            <ion-icon :icon="documentTextOutline" class="stat-icon" />
          </div>

          <div class="stat-card" @click="$router.push('/customers')">
            <div class="stat-value">{{ store.contacts }}</div>
            <div class="stat-label">Clientes</div>
            <ion-icon :icon="peopleOutline" class="stat-icon" />
          </div>

          <div class="stat-card" @click="$router.push('/tabs/invoices')">
            <div class="stat-value">{{ store.invoices }}</div>
            <div class="stat-label">Facturas</div>
            <ion-icon :icon="receiptOutline" class="stat-icon" />
          </div>

          <div class="stat-card" @click="$router.push('/estimates')">
            <div class="stat-value">{{ store.estimates }}</div>
            <div class="stat-label">Presupuestos</div>
            <ion-icon :icon="clipboardOutline" class="stat-icon" />
          </div>
        </div>

        <!-- Financial summary -->
        <ion-card>
          <ion-card-header>
            <ion-card-title>Resumen financiero</ion-card-title>
          </ion-card-header>
          <ion-card-content class="ion-no-padding">
            <ion-list lines="full">
              <ion-item>
                <ion-label>Ventas totales</ion-label>
                <ion-note slot="end" color="success">{{ formatMoney(store.totalSales) }}</ion-note>
              </ion-item>
              <ion-item>
                <ion-label>Total cobrado</ion-label>
                <ion-note slot="end" color="primary">{{ formatMoney(store.totalReceipts) }}</ion-note>
              </ion-item>
              <ion-item>
                <ion-label>Total gastos</ion-label>
                <ion-note slot="end" color="danger">{{ formatMoney(store.totalExpenses) }}</ion-note>
              </ion-item>
              <ion-item lines="none">
                <ion-label><strong>Ganancia neta</strong></ion-label>
                <ion-note slot="end" :color="store.netProfit >= 0 ? 'success' : 'danger'">
                  <strong>{{ formatMoney(store.netProfit) }}</strong>
                </ion-note>
              </ion-item>
            </ion-list>
          </ion-card-content>
        </ion-card>

        <!-- Recent invoices -->
        <ion-card v-if="store.recentInvoices.length">
          <ion-card-header>
            <ion-card-title>Facturas recientes</ion-card-title>
          </ion-card-header>
          <ion-card-content class="ion-no-padding">
            <ion-list lines="full">
              <ion-item
                v-for="invoice in store.recentInvoices"
                :key="invoice.id"
                button
                @click="$router.push(`/invoices/${invoice.id}`)"
              >
                <ion-label>
                  <h3>{{ invoice.invoice_number }}</h3>
                  <p>{{ invoice.customer?.name }}</p>
                </ion-label>
                <div slot="end" class="ion-text-right">
                  <div class="amount">{{ formatMoney(invoice.total) }}</div>
                  <span :class="`status-badge status-${invoice.status?.toLowerCase()}`">
                    {{ invoice.status }}
                  </span>
                </div>
              </ion-item>
            </ion-list>
          </ion-card-content>
        </ion-card>
      </template>
    </ion-content>
  </ion-page>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import {
  IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonButtons, IonButton,
  IonIcon, IonCard, IonCardHeader, IonCardTitle, IonCardContent,
  IonList, IonItem, IonLabel, IonNote, IonSpinner, IonRefresher, IonRefresherContent,
} from '@ionic/vue'
import {
  refreshOutline, documentTextOutline, peopleOutline,
  receiptOutline, clipboardOutline,
} from 'ionicons/icons'
import { useDashboardStore } from '@/stores/dashboard'
import { useAuthStore } from '@/stores/auth'

const store = useDashboardStore()
const authStore = useAuthStore()
const loading = ref(false)

const currency = authStore.currency

function formatMoney(amount: number) {
  const sym = currency?.symbol ?? '$'
  const precision = currency?.precision ?? 2
  return `${sym}${(amount / 100).toFixed(precision)}`
}

async function refresh() {
  loading.value = true
  await store.fetchDashboard()
  loading.value = false
}

async function onRefresh(event: any) {
  await store.fetchDashboard()
  event.target.complete()
}

onMounted(async () => {
  if (!store.loaded) await refresh()
})
</script>

<style scoped>
.stats-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-bottom: 4px;
}
.stat-card {
  background: #fff;
  border-radius: 12px;
  padding: 16px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.08);
  position: relative;
  overflow: hidden;
  cursor: pointer;
}
.stat-value {
  font-size: 20px;
  font-weight: 700;
  color: #111827;
}
.stat-label {
  font-size: 12px;
  color: #6b7280;
  margin-top: 4px;
}
.stat-icon {
  position: absolute;
  bottom: 8px;
  right: 12px;
  font-size: 28px;
  color: var(--ion-color-primary);
  opacity: 0.15;
}
.amount {
  font-size: 13px;
  font-weight: 600;
  color: #111827;
}
</style>
