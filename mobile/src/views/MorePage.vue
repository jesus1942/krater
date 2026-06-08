<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <ion-title>Mas</ion-title>
      </ion-toolbar>
    </ion-header>

    <ion-content color="light">
      <ion-list lines="none" class="ion-padding-horizontal ion-padding-top">
        <ion-item-group v-for="group in menu" :key="group.title">
          <ion-item-divider>
            <ion-label>{{ group.title }}</ion-label>
          </ion-item-divider>
          <ion-card class="group-card">
            <ion-list lines="full">
              <ion-item
                v-for="item in group.items"
                :key="item.label"
                button
                :router-link="item.path"
                detail
              >
                <ion-icon :icon="item.icon" slot="start" :color="item.color ?? 'primary'" />
                <ion-label>{{ item.label }}</ion-label>
              </ion-item>
            </ion-list>
          </ion-card>
        </ion-item-group>
      </ion-list>

      <!-- Logout -->
      <div class="ion-padding">
        <ion-button expand="block" fill="outline" color="danger" @click="handleLogout">
          <ion-icon slot="start" :icon="logOutOutline" />
          Cerrar sesion
        </ion-button>
      </div>
    </ion-content>
  </ion-page>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'
import {
  IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonList, IonItem,
  IonItemGroup, IonItemDivider, IonLabel, IonIcon, IonButton, IonCard,
  alertController,
} from '@ionic/vue'
import {
  peopleOutline, receiptOutline, cashOutline, cubeOutline,
  barChartOutline, settingsOutline, logOutOutline, documentTextOutline,
} from 'ionicons/icons'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const menu = [
  {
    title: 'Gestion',
    items: [
      { label: 'Clientes', path: '/customers', icon: peopleOutline },
      { label: 'Presupuestos', path: '/estimates', icon: receiptOutline },
      { label: 'Cobros', path: '/payments', icon: cashOutline },
      { label: 'Productos y servicios', path: '/items', icon: cubeOutline },
    ],
  },
  {
    title: 'Reportes',
    items: [
      { label: 'Ver reportes', path: '/reports', icon: barChartOutline, color: 'success' },
    ],
  },
  {
    title: 'Configuracion',
    items: [
      { label: 'Ajustes', path: '/settings', icon: settingsOutline, color: 'medium' },
    ],
  },
]

async function handleLogout() {
  const alert = await alertController.create({
    header: 'Cerrar sesion',
    message: 'Seguro que quieres salir?',
    buttons: [
      { text: 'Cancelar', role: 'cancel' },
      { text: 'Salir', role: 'destructive', handler: async () => {
        await authStore.logout()
        router.replace('/auth/login')
      }},
    ],
  })
  await alert.present()
}
</script>

<style scoped>
.group-card { margin: 0 0 8px; }
</style>
