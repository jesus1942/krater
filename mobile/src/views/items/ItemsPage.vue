<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <ion-buttons slot="start"><ion-back-button default-href="/tabs/more" /></ion-buttons>
        <ion-title>Productos y servicios</ion-title>
        <ion-buttons slot="end">
          <ion-button router-link="/items/create"><ion-icon slot="icon-only" :icon="addOutline" /></ion-button>
        </ion-buttons>
      </ion-toolbar>
      <ion-toolbar>
        <ion-searchbar v-model="search" placeholder="Buscar item..." debounce="400" @ionInput="load" />
      </ion-toolbar>
    </ion-header>

    <ion-content color="light">
      <ion-refresher slot="fixed" @ionRefresh="onRefresh"><ion-refresher-content /></ion-refresher>
      <div v-if="store.loading" class="ion-text-center ion-padding-top"><ion-spinner color="primary" /></div>
      <ion-list v-else-if="store.list.length" lines="none" class="ion-padding-horizontal ion-padding-top">
        <ion-card v-for="item in store.list" :key="item.id" button @click="openItem(item)">
          <ion-card-content>
            <div class="row">
              <div>
                <div class="name">{{ item.name }}</div>
                <div class="unit">{{ item.unit?.name }}</div>
              </div>
              <div class="price">{{ formatMoney(item.price) }}</div>
            </div>
          </ion-card-content>
        </ion-card>
      </ion-list>
      <div v-else class="empty-state">
        <ion-icon :icon="cubeOutline" />
        <p>No hay productos ni servicios</p>
        <ion-button fill="outline" router-link="/items/create">Agregar item</ion-button>
      </div>
    </ion-content>
  </ion-page>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonButtons, IonButton, IonBackButton, IonIcon, IonList, IonCard, IonCardContent, IonSearchbar, IonSpinner, IonRefresher, IonRefresherContent, actionSheetController, alertController } from '@ionic/vue'
import { addOutline, cubeOutline } from 'ionicons/icons'
import { useItemsStore } from '@/stores/items'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const store = useItemsStore()
const authStore = useAuthStore()
const search = ref('')
const currency = authStore.currency
function formatMoney(amount: number) { return `${currency?.symbol ?? '$'}${(amount / 100).toFixed(currency?.precision ?? 2)}` }
async function load() { await store.fetchItems({ search: search.value }) }
async function onRefresh(event: any) { await load(); event.target.complete() }
async function openItem(item: any) {
  const sheet = await actionSheetController.create({
    header: item.name,
    buttons: [
      { text: 'Editar', handler: () => router.push(`/items/${item.id}/edit`) },
      { text: 'Eliminar', role: 'destructive', handler: () => confirmDelete(item.id) },
      { text: 'Cancelar', role: 'cancel' },
    ],
  })
  await sheet.present()
}
async function confirmDelete(id: number) {
  const alert = await alertController.create({
    header: 'Eliminar item', message: 'Esta accion no se puede deshacer.',
    buttons: [{ text: 'Cancelar', role: 'cancel' }, { text: 'Eliminar', role: 'destructive', handler: () => store.deleteItem(id) }],
  })
  await alert.present()
}
onMounted(() => load())
</script>

<style scoped>
.row { display: flex; justify-content: space-between; align-items: center; }
.name { font-weight: 600; font-size: 15px; color: #111827; }
.unit { font-size: 12px; color: #9ca3af; }
.price { font-weight: 700; font-size: 16px; color: #5851d8; }
.empty-state { display: flex; flex-direction: column; align-items: center; padding: 64px 24px; color: #9ca3af; }
.empty-state ion-icon { font-size: 64px; margin-bottom: 16px; }
</style>
