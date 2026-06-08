<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <ion-buttons slot="start"><ion-back-button default-href="/tabs/more" /></ion-buttons>
        <ion-title>Cobros</ion-title>
        <ion-buttons slot="end">
          <ion-button router-link="/payments/create">
            <ion-icon slot="icon-only" :icon="addOutline" />
          </ion-button>
        </ion-buttons>
      </ion-toolbar>
      <ion-toolbar>
        <ion-searchbar v-model="search" placeholder="Buscar cobro..." debounce="400" @ionInput="load" />
      </ion-toolbar>
    </ion-header>

    <ion-content color="light">
      <ion-refresher slot="fixed" @ionRefresh="onRefresh"><ion-refresher-content /></ion-refresher>
      <div v-if="store.loading" class="ion-text-center ion-padding-top"><ion-spinner color="primary" /></div>
      <ion-list v-else-if="store.list.length" lines="none" class="ion-padding-horizontal ion-padding-top">
        <ion-card v-for="payment in store.list" :key="payment.id" button @click="$router.push(`/payments/${payment.id}`)">
          <ion-card-content>
            <div class="row">
              <div>
                <div class="number">{{ payment.payment_number }}</div>
                <div class="sub">{{ payment.customer?.name }}</div>
                <div class="date">{{ payment.payment_date }}</div>
              </div>
              <div class="amount">{{ formatMoney(payment.amount) }}</div>
            </div>
          </ion-card-content>
        </ion-card>
      </ion-list>
      <div v-else class="empty-state">
        <ion-icon :icon="cashOutline" />
        <p>No hay cobros registrados</p>
        <ion-button fill="outline" router-link="/payments/create">Registrar cobro</ion-button>
      </div>
    </ion-content>
  </ion-page>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonButtons, IonButton, IonBackButton, IonIcon, IonList, IonCard, IonCardContent, IonSearchbar, IonSpinner, IonRefresher, IonRefresherContent } from '@ionic/vue'
import { addOutline, cashOutline } from 'ionicons/icons'
import { usePaymentsStore } from '@/stores/payments'
import { useAuthStore } from '@/stores/auth'

const store = usePaymentsStore()
const authStore = useAuthStore()
const search = ref('')
const currency = authStore.currency
function formatMoney(amount: number) { return `${currency?.symbol ?? '$'}${(amount / 100).toFixed(currency?.precision ?? 2)}` }
async function load() { await store.fetchPayments({ search: search.value }) }
async function onRefresh(event: any) { await load(); event.target.complete() }
onMounted(() => load())
</script>

<style scoped>
.row { display: flex; justify-content: space-between; align-items: center; }
.number { font-weight: 600; font-size: 15px; color: #111827; }
.sub { font-size: 13px; color: #6b7280; }
.date { font-size: 12px; color: #9ca3af; }
.amount { font-weight: 700; font-size: 18px; color: #10b981; }
.empty-state { display: flex; flex-direction: column; align-items: center; padding: 64px 24px; color: #9ca3af; }
.empty-state ion-icon { font-size: 64px; margin-bottom: 16px; }
</style>
