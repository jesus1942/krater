<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <ion-buttons slot="start"><ion-back-button default-href="/customers" /></ion-buttons>
        <ion-title>{{ customer?.name ?? 'Cliente' }}</ion-title>
        <ion-buttons slot="end">
          <ion-button :router-link="`/customers/${id}/edit`">
            <ion-icon slot="icon-only" :icon="createOutline" />
          </ion-button>
        </ion-buttons>
      </ion-toolbar>
    </ion-header>

    <ion-content color="light">
      <div v-if="loading" class="ion-text-center ion-padding-top"><ion-spinner color="primary" /></div>
      <template v-else-if="customer">
        <ion-card>
          <ion-card-content>
            <div class="profile">
              <div class="avatar">{{ initials(customer.name) }}</div>
              <div>
                <h2 class="name">{{ customer.name }}</h2>
                <p class="email">{{ customer.email }}</p>
                <p v-if="customer.phone" class="phone">{{ customer.phone }}</p>
              </div>
            </div>
          </ion-card-content>
        </ion-card>

        <ion-card v-if="stats">
          <ion-card-header><ion-card-title>Estadisticas</ion-card-title></ion-card-header>
          <ion-card-content class="ion-no-padding">
            <ion-list lines="full">
              <ion-item><ion-label>Total facturado</ion-label><ion-note slot="end">{{ formatMoney(stats.invoices_total) }}</ion-note></ion-item>
              <ion-item><ion-label>Total cobrado</ion-label><ion-note slot="end" color="success">{{ formatMoney(stats.invoices_paid) }}</ion-note></ion-item>
              <ion-item lines="none"><ion-label>Total pendiente</ion-label><ion-note slot="end" color="danger">{{ formatMoney(stats.invoices_unpaid) }}</ion-note></ion-item>
            </ion-list>
          </ion-card-content>
        </ion-card>

        <div class="ion-padding action-buttons">
          <ion-button expand="block" color="primary" :router-link="`/invoices/create`">
            <ion-icon slot="start" :icon="documentTextOutline" />Nueva factura
          </ion-button>
          <ion-button expand="block" fill="outline" :router-link="`/estimates/create`">
            <ion-icon slot="start" :icon="receiptOutline" />Nuevo presupuesto
          </ion-button>
        </div>
      </template>
    </ion-content>
  </ion-page>
</template>

<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useRoute } from 'vue-router'
import { IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonButtons, IonButton, IonBackButton, IonIcon, IonCard, IonCardHeader, IonCardTitle, IonCardContent, IonList, IonItem, IonLabel, IonNote, IonSpinner } from '@ionic/vue'
import { createOutline, documentTextOutline, receiptOutline } from 'ionicons/icons'
import { useCustomersStore } from '@/stores/customers'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const store = useCustomersStore()
const authStore = useAuthStore()
const id = computed(() => Number(route.params.id))
const customer = computed(() => store.current)
const stats = ref<any>(null)
const loading = ref(false)
function initials(name: string) { return name.split(' ').map((w) => w[0]).join('').slice(0, 2).toUpperCase() }
const currency = authStore.currency
function formatMoney(amount: number) { return `${currency?.symbol ?? '$'}${(amount / 100).toFixed(currency?.precision ?? 2)}` }
onMounted(async () => {
  loading.value = true
  await store.fetchCustomer(id.value)
  stats.value = await store.fetchStats(id.value)
  loading.value = false
})
</script>

<style scoped>
.profile { display: flex; align-items: center; gap: 16px; }
.avatar { width: 56px; height: 56px; border-radius: 50%; background: var(--ion-color-primary); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 20px; flex-shrink: 0; }
.name { font-size: 18px; font-weight: 700; color: #111827; margin: 0; }
.email { color: #6b7280; margin: 4px 0 0; }
.phone { color: #9ca3af; margin: 2px 0 0; }
.action-buttons { display: flex; flex-direction: column; gap: 8px; }
</style>
