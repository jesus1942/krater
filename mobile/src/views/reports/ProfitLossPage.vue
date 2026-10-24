<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <ion-buttons slot="start"><ion-back-button default-href="/reports" /></ion-buttons>
        <ion-title>Ganancia y perdida</ion-title>
      </ion-toolbar>
    </ion-header>
    <ion-content color="light">
      <ion-card>
        <ion-card-content class="ion-no-padding">
          <ion-item lines="full"><ion-label position="stacked">Desde</ion-label><ion-input v-model="from" type="date" /></ion-item>
          <ion-item lines="none"><ion-label position="stacked">Hasta</ion-label><ion-input v-model="to" type="date" /></ion-item>
        </ion-card-content>
      </ion-card>
      <div class="ion-padding-horizontal"><ion-button expand="block" color="primary" @click="load">Generar reporte</ion-button></div>
      <div v-if="loading" class="ion-text-center ion-padding-top"><ion-spinner color="primary" /></div>
      <template v-else-if="report">
        <ion-card>
          <ion-card-content class="ion-no-padding">
            <ion-list lines="full">
              <ion-item><ion-label>Total ingresos</ion-label><ion-note slot="end" color="success">{{ formatMoney(report.total_receipts) }}</ion-note></ion-item>
              <ion-item><ion-label>Total gastos</ion-label><ion-note slot="end" color="danger">{{ formatMoney(report.total_expenses) }}</ion-note></ion-item>
              <ion-item lines="none">
                <ion-label><strong>Ganancia neta</strong></ion-label>
                <ion-note slot="end" :color="report.net_profit >= 0 ? 'success' : 'danger'">
                  <strong>{{ formatMoney(report.net_profit) }}</strong>
                </ion-note>
              </ion-item>
            </ion-list>
          </ion-card-content>
        </ion-card>
      </template>
    </ion-content>
  </ion-page>
</template>
<script setup lang="ts">
import { ref } from 'vue'
import { IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonButtons, IonBackButton, IonCard, IonCardContent, IonList, IonItem, IonLabel, IonNote, IonInput, IonButton, IonSpinner } from '@ionic/vue'
import http from '@/services/http'
import { useAuthStore } from '@/stores/auth'
const authStore = useAuthStore()
const now = new Date()
const from = ref(new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0])
const to = ref(now.toISOString().split('T')[0])
const loading = ref(false)
const report = ref<any>(null)
const currency = authStore.currency
function formatMoney(amount: number) { return `${currency?.symbol ?? '$'}${(amount / 100).toFixed(currency?.precision ?? 2)}` }
async function load() { loading.value = true; try { const { data } = await http.get('/reports/profit-loss', { params: { from_date: from.value, to_date: to.value } }); report.value = data } finally { loading.value = false } }
</script>
