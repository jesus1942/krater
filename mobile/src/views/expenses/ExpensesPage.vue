<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <ion-title>Gastos</ion-title>
        <ion-buttons slot="end">
          <ion-button router-link="/expenses/create">
            <ion-icon slot="icon-only" :icon="addOutline" />
          </ion-button>
        </ion-buttons>
      </ion-toolbar>
      <ion-toolbar>
        <ion-searchbar v-model="search" placeholder="Buscar gasto..." debounce="400" @ionInput="load" />
      </ion-toolbar>
    </ion-header>

    <ion-content color="light">
      <ion-refresher slot="fixed" @ionRefresh="onRefresh">
        <ion-refresher-content />
      </ion-refresher>

      <div v-if="store.loading" class="ion-text-center ion-padding-top">
        <ion-spinner color="primary" />
      </div>

      <ion-list v-else-if="store.list.length" lines="none" class="ion-padding-horizontal">
        <ion-card
          v-for="expense in store.list"
          :key="expense.id"
          button
          @click="openExpense(expense)"
        >
          <ion-card-content>
            <div class="expense-row">
              <div>
                <div class="expense-desc">{{ expense.notes ?? 'Sin descripcion' }}</div>
                <div class="expense-category">{{ expense.category?.name }}</div>
                <div class="expense-date">{{ expense.expense_date }}</div>
              </div>
              <div class="expense-amount">{{ formatMoney(expense.amount) }}</div>
            </div>
          </ion-card-content>
        </ion-card>
      </ion-list>

      <div v-else class="empty-state">
        <ion-icon :icon="walletOutline" />
        <p>No hay gastos registrados</p>
        <ion-button fill="outline" router-link="/expenses/create">Registrar gasto</ion-button>
      </div>
    </ion-content>
  </ion-page>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonButtons, IonButton,
  IonIcon, IonList, IonCard, IonCardContent, IonSearchbar, IonSpinner,
  IonRefresher, IonRefresherContent, actionSheetController, alertController,
} from '@ionic/vue'
import { addOutline, walletOutline } from 'ionicons/icons'
import { useExpensesStore } from '@/stores/expenses'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const store = useExpensesStore()
const authStore = useAuthStore()
const search = ref('')

const currency = authStore.currency
function formatMoney(amount: number) {
  const sym = currency?.symbol ?? '$'
  const precision = currency?.precision ?? 2
  return `${sym}${(amount / 100).toFixed(precision)}`
}

async function load() {
  await store.fetchExpenses({ search: search.value })
}

async function onRefresh(event: any) {
  await load()
  event.target.complete()
}

async function openExpense(expense: any) {
  const sheet = await actionSheetController.create({
    header: expense.notes ?? 'Gasto',
    buttons: [
      { text: 'Editar', handler: () => router.push(`/expenses/${expense.id}/edit`) },
      { text: 'Eliminar', role: 'destructive', handler: () => confirmDelete(expense.id) },
      { text: 'Cancelar', role: 'cancel' },
    ],
  })
  await sheet.present()
}

async function confirmDelete(id: number) {
  const alert = await alertController.create({
    header: 'Eliminar gasto',
    message: 'Esta accion no se puede deshacer.',
    buttons: [
      { text: 'Cancelar', role: 'cancel' },
      { text: 'Eliminar', role: 'destructive', handler: () => store.deleteExpense(id) },
    ],
  })
  await alert.present()
}

onMounted(() => load())
</script>

<style scoped>
.expense-row { display: flex; justify-content: space-between; align-items: center; }
.expense-desc { font-weight: 600; font-size: 14px; color: #111827; }
.expense-category { font-size: 12px; color: #6b7280; margin-top: 2px; }
.expense-date { font-size: 12px; color: #9ca3af; margin-top: 2px; }
.expense-amount { font-weight: 700; font-size: 16px; color: #ef4444; }
.empty-state { display: flex; flex-direction: column; align-items: center; padding: 64px 24px; color: #9ca3af; }
.empty-state ion-icon { font-size: 64px; margin-bottom: 16px; }
</style>
