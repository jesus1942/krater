<template>
  <ion-page>
    <ion-tabs>
      <ion-router-outlet />
      <ion-tab-bar slot="bottom">
        <ion-tab-button tab="dashboard" href="/tabs/dashboard">
          <ion-icon :icon="statsChartOutline" />
          <ion-label>Dashboard</ion-label>
        </ion-tab-button>

        <ion-tab-button tab="invoices" href="/tabs/invoices">
          <ion-icon :icon="documentTextOutline" />
          <ion-label>Facturas</ion-label>
        </ion-tab-button>

        <ion-tab-button tab="expenses" href="/tabs/expenses">
          <ion-icon :icon="walletOutline" />
          <ion-label>Gastos</ion-label>
        </ion-tab-button>

        <ion-tab-button tab="more" href="/tabs/more">
          <ion-icon :icon="gridOutline" />
          <ion-label>Mas</ion-label>
        </ion-tab-button>
      </ion-tab-bar>
    </ion-tabs>

    <ion-fab slot="fixed" vertical="bottom" horizontal="end" class="fab-create">
      <ion-fab-button color="primary" @click="openCreateMenu">
        <ion-icon :icon="addOutline" />
      </ion-fab-button>
    </ion-fab>

    <ion-action-sheet
      :is-open="showCreateMenu"
      header="Crear nuevo"
      :buttons="createButtons"
      @didDismiss="showCreateMenu = false"
    />
  </ion-page>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  IonPage, IonTabs, IonTabBar, IonTabButton, IonIcon, IonLabel,
  IonRouterOutlet, IonFab, IonFabButton, IonActionSheet,
} from '@ionic/vue'
import {
  statsChartOutline, documentTextOutline, walletOutline,
  gridOutline, addOutline, documentOutline, cashOutline,
  receiptOutline, peopleOutline,
} from 'ionicons/icons'

const router = useRouter()
const showCreateMenu = ref(false)

function openCreateMenu() {
  showCreateMenu.value = true
}

const createButtons = [
  { text: 'Nueva Factura', icon: documentOutline, handler: () => router.push('/invoices/create') },
  { text: 'Nuevo Presupuesto', icon: receiptOutline, handler: () => router.push('/estimates/create') },
  { text: 'Nuevo Gasto', icon: walletOutline, handler: () => router.push('/expenses/create') },
  { text: 'Nuevo Pago', icon: cashOutline, handler: () => router.push('/payments/create') },
  { text: 'Nuevo Cliente', icon: peopleOutline, handler: () => router.push('/customers/create') },
  { text: 'Cancelar', role: 'cancel' },
]
</script>

<style scoped>
.fab-create {
  bottom: calc(56px + env(safe-area-inset-bottom) + 16px);
}
</style>
