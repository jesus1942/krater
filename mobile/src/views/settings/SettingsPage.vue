<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <ion-buttons slot="start"><ion-back-button default-href="/tabs/more" /></ion-buttons>
        <ion-title>Configuracion</ion-title>
      </ion-toolbar>
    </ion-header>
    <ion-content color="light">
      <ion-list lines="none" class="ion-padding-horizontal ion-padding-top">
        <ion-card v-for="group in settingsGroups" :key="group.title">
          <ion-item-divider class="group-title"><ion-label>{{ group.title }}</ion-label></ion-item-divider>
          <ion-list lines="full">
            <ion-item v-for="item in group.items" :key="item.label" button :router-link="item.path" detail>
              <ion-icon :icon="item.icon" slot="start" color="primary" />
              <ion-label>{{ item.label }}</ion-label>
            </ion-item>
          </ion-list>
        </ion-card>
      </ion-list>
    </ion-content>
  </ion-page>
</template>

<script setup lang="ts">
import { IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonButtons, IonBackButton, IonList, IonItem, IonItemDivider, IonLabel, IonIcon, IonCard } from '@ionic/vue'
import { businessOutline, personOutline, optionsOutline, cashOutline, pricetagOutline, walletOutline, extensionPuzzleOutline, mailOutline, cloudUploadOutline, saveOutline } from 'ionicons/icons'

const settingsGroups = [
  {
    title: 'Cuenta',
    items: [
      { label: 'Datos de la empresa', path: '/settings/company', icon: businessOutline },
      { label: 'Mi perfil', path: '/settings/profile', icon: personOutline },
      { label: 'Preferencias', path: '/settings/preferences', icon: optionsOutline },
    ],
  },
  {
    title: 'Facturacion',
    items: [
      { label: 'Metodos de pago', path: '/settings/payment-methods', icon: cashOutline },
      { label: 'Tipos de impuesto', path: '/settings/tax-types', icon: pricetagOutline },
      { label: 'Categorias de gasto', path: '/settings/expense-categories', icon: walletOutline },
      { label: 'Campos personalizados', path: '/settings/custom-fields', icon: extensionPuzzleOutline },
    ],
  },
  {
    title: 'Sistema',
    items: [
      { label: 'Configuracion de correo', path: '/settings/mail', icon: mailOutline },
      { label: 'Almacenamiento', path: '/settings/storage', icon: cloudUploadOutline },
      { label: 'Respaldos', path: '/settings/backup', icon: saveOutline },
    ],
  },
]
</script>

<style scoped>
.group-title { --background: transparent; --color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; }
</style>
