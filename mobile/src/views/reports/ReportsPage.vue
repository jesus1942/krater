<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <ion-buttons slot="start"><ion-back-button default-href="/tabs/more" /></ion-buttons>
        <ion-title>Reportes</ion-title>
      </ion-toolbar>
    </ion-header>

    <ion-content color="light">
      <ion-list lines="none" class="ion-padding-horizontal ion-padding-top">
        <ion-card>
          <ion-list lines="full">
            <ion-item button router-link="/reports/sales" detail>
              <ion-icon slot="start" :icon="trendingUpOutline" color="success" />
              <ion-label>
                <h3>Reporte de ventas</h3>
                <p>Ventas totales por periodo</p>
              </ion-label>
            </ion-item>
            <ion-item button router-link="/reports/expenses" detail>
              <ion-icon slot="start" :icon="trendingDownOutline" color="danger" />
              <ion-label>
                <h3>Reporte de gastos</h3>
                <p>Gastos por categoria y periodo</p>
              </ion-label>
            </ion-item>
            <ion-item button router-link="/reports/profit-loss" detail>
              <ion-icon slot="start" :icon="barChartOutline" color="primary" />
              <ion-label>
                <h3>Ganancia y perdida</h3>
                <p>Balance del negocio</p>
              </ion-label>
            </ion-item>
            <ion-item button router-link="/reports/taxes" detail lines="none">
              <ion-icon slot="start" :icon="receiptOutline" color="warning" />
              <ion-label>
                <h3>Reporte de impuestos</h3>
                <p>Impuestos por tipo y periodo</p>
              </ion-label>
            </ion-item>
          </ion-list>
        </ion-card>
      </ion-list>
    </ion-content>
  </ion-page>
</template>

<script setup lang="ts">
import { IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonButtons, IonBackButton, IonList, IonItem, IonLabel, IonIcon, IonCard } from '@ionic/vue'
import { trendingUpOutline, trendingDownOutline, barChartOutline, receiptOutline } from 'ionicons/icons'
</script>
