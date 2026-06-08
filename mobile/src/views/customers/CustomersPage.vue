<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <ion-buttons slot="start"><ion-back-button default-href="/tabs/more" /></ion-buttons>
        <ion-title>Clientes</ion-title>
        <ion-buttons slot="end">
          <ion-button router-link="/customers/create">
            <ion-icon slot="icon-only" :icon="addOutline" />
          </ion-button>
        </ion-buttons>
      </ion-toolbar>
      <ion-toolbar>
        <ion-searchbar v-model="search" placeholder="Buscar cliente..." debounce="400" @ionInput="load" />
      </ion-toolbar>
    </ion-header>

    <ion-content color="light">
      <ion-refresher slot="fixed" @ionRefresh="onRefresh"><ion-refresher-content /></ion-refresher>

      <div v-if="store.loading" class="ion-text-center ion-padding-top"><ion-spinner color="primary" /></div>

      <ion-list v-else-if="store.list.length" lines="none" class="ion-padding-horizontal ion-padding-top">
        <ion-card v-for="customer in store.list" :key="customer.id" button @click="$router.push(`/customers/${customer.id}`)">
          <ion-card-content>
            <div class="customer-row">
              <div class="avatar">{{ initials(customer.name) }}</div>
              <div class="info">
                <div class="name">{{ customer.name }}</div>
                <div class="email">{{ customer.email }}</div>
                <div v-if="customer.phone" class="phone">{{ customer.phone }}</div>
              </div>
              <ion-icon :icon="chevronForwardOutline" color="medium" />
            </div>
          </ion-card-content>
        </ion-card>
      </ion-list>

      <div v-else class="empty-state">
        <ion-icon :icon="peopleOutline" />
        <p>No hay clientes</p>
        <ion-button fill="outline" router-link="/customers/create">Agregar cliente</ion-button>
      </div>
    </ion-content>
  </ion-page>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonButtons, IonButton, IonBackButton, IonIcon, IonList, IonCard, IonCardContent, IonSearchbar, IonSpinner, IonRefresher, IonRefresherContent } from '@ionic/vue'
import { addOutline, peopleOutline, chevronForwardOutline } from 'ionicons/icons'
import { useCustomersStore } from '@/stores/customers'

const store = useCustomersStore()
const search = ref('')
function initials(name: string) { return name.split(' ').map((w) => w[0]).join('').slice(0, 2).toUpperCase() }
async function load() { await store.fetchCustomers({ search: search.value }) }
async function onRefresh(event: any) { await load(); event.target.complete() }
onMounted(() => load())
</script>

<style scoped>
.customer-row { display: flex; align-items: center; gap: 12px; }
.avatar { width: 40px; height: 40px; border-radius: 50%; background: var(--ion-color-primary); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; flex-shrink: 0; }
.info { flex: 1; }
.name { font-weight: 600; font-size: 15px; color: #111827; }
.email { font-size: 13px; color: #6b7280; }
.phone { font-size: 12px; color: #9ca3af; }
.empty-state { display: flex; flex-direction: column; align-items: center; padding: 64px 24px; color: #9ca3af; }
.empty-state ion-icon { font-size: 64px; margin-bottom: 16px; }
</style>
