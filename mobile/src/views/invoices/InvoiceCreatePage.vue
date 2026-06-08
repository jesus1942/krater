<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <ion-buttons slot="start">
          <ion-back-button :default-href="isEdit ? `/invoices/${id}` : '/tabs/invoices'" />
        </ion-buttons>
        <ion-title>{{ isEdit ? 'Editar factura' : 'Nueva factura' }}</ion-title>
        <ion-buttons slot="end">
          <ion-button :disabled="saving" @click="save">
            <ion-spinner v-if="saving" name="crescent" />
            <span v-else>Guardar</span>
          </ion-button>
        </ion-buttons>
      </ion-toolbar>
    </ion-header>

    <ion-content color="light">
      <!-- Customer -->
      <ion-card>
        <ion-card-header><ion-card-title>Cliente</ion-card-title></ion-card-header>
        <ion-card-content class="ion-no-padding">
          <ion-item button lines="none" @click="openCustomerSelect">
            <ion-label>{{ form.customer?.name ?? 'Seleccionar cliente' }}</ion-label>
            <ion-icon slot="end" :icon="chevronForwardOutline" color="medium" />
          </ion-item>
        </ion-card-content>
      </ion-card>

      <!-- Dates & Number -->
      <ion-card>
        <ion-card-content class="ion-no-padding">
          <ion-item lines="full">
            <ion-label position="stacked">Numero de factura *</ion-label>
            <ion-input v-model="form.invoice_number" placeholder="FAC-0001" />
          </ion-item>
          <ion-item lines="full">
            <ion-label position="stacked">Fecha de factura *</ion-label>
            <ion-input v-model="form.invoice_date" type="date" />
          </ion-item>
          <ion-item lines="none">
            <ion-label position="stacked">Fecha de vencimiento *</ion-label>
            <ion-input v-model="form.due_date" type="date" />
          </ion-item>
        </ion-card-content>
      </ion-card>

      <!-- Items -->
      <ion-card>
        <ion-card-header>
          <ion-card-title>Items</ion-card-title>
          <ion-button fill="clear" size="small" @click="addItem">+ Agregar item</ion-button>
        </ion-card-header>
        <ion-card-content class="ion-no-padding">
          <div v-for="(item, idx) in form.items" :key="idx" class="line-item">
            <ion-item lines="full">
              <ion-label position="stacked">Descripcion</ion-label>
              <ion-input v-model="item.name" placeholder="Producto o servicio" />
              <ion-button slot="end" fill="clear" color="danger" @click="removeItem(idx)">
                <ion-icon :icon="trashOutline" />
              </ion-button>
            </ion-item>
            <ion-grid>
              <ion-row>
                <ion-col>
                  <ion-item lines="none">
                    <ion-label position="stacked">Cant.</ion-label>
                    <ion-input v-model.number="item.quantity" type="number" min="1" />
                  </ion-item>
                </ion-col>
                <ion-col>
                  <ion-item lines="none">
                    <ion-label position="stacked">Precio</ion-label>
                    <ion-input v-model.number="item.price" type="number" min="0" step="0.01" />
                  </ion-item>
                </ion-col>
              </ion-row>
            </ion-grid>
          </div>
        </ion-card-content>
      </ion-card>

      <!-- Totals summary -->
      <ion-card>
        <ion-card-content class="ion-no-padding">
          <ion-list lines="full">
            <ion-item>
              <ion-label color="medium">Subtotal</ion-label>
              <ion-note slot="end">{{ formatMoney(subtotal) }}</ion-note>
            </ion-item>
            <ion-item lines="none">
              <ion-label><strong>Total</strong></ion-label>
              <ion-note slot="end"><strong>{{ formatMoney(total) }}</strong></ion-note>
            </ion-item>
          </ion-list>
        </ion-card-content>
      </ion-card>

      <!-- Notes -->
      <ion-card>
        <ion-card-content class="ion-no-padding">
          <ion-item lines="none">
            <ion-label position="stacked">Notas</ion-label>
            <ion-textarea v-model="form.notes" rows="3" placeholder="Notas adicionales..." />
          </ion-item>
        </ion-card-content>
      </ion-card>
    </ion-content>

    <!-- Customer select modal -->
    <ion-modal :is-open="showCustomerModal" @didDismiss="showCustomerModal = false">
      <ion-header>
        <ion-toolbar>
          <ion-title>Seleccionar cliente</ion-title>
          <ion-buttons slot="end">
            <ion-button @click="showCustomerModal = false">Cerrar</ion-button>
          </ion-buttons>
        </ion-toolbar>
        <ion-toolbar>
          <ion-searchbar v-model="customerSearch" placeholder="Buscar..." debounce="300" />
        </ion-toolbar>
      </ion-header>
      <ion-content>
        <ion-list>
          <ion-item
            v-for="c in filteredCustomers"
            :key="c.id"
            button
            @click="selectCustomer(c)"
          >
            <ion-label>{{ c.name }}</ion-label>
          </ion-item>
        </ion-list>
      </ion-content>
    </ion-modal>
  </ion-page>
</template>

<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  IonPage, IonHeader, IonToolbar, IonTitle, IonContent, IonButtons, IonButton,
  IonBackButton, IonIcon, IonCard, IonCardHeader, IonCardTitle, IonCardContent,
  IonList, IonItem, IonLabel, IonNote, IonInput, IonTextarea, IonSpinner,
  IonGrid, IonRow, IonCol, IonModal, IonSearchbar, toastController,
} from '@ionic/vue'
import { chevronForwardOutline, trashOutline } from 'ionicons/icons'
import { useInvoicesStore } from '@/stores/invoices'
import { useCustomersStore } from '@/stores/customers'
import { useAuthStore } from '@/stores/auth'
import http from '@/services/http'

const route = useRoute()
const router = useRouter()
const store = useInvoicesStore()
const customersStore = useCustomersStore()
const authStore = useAuthStore()

const isEdit = computed(() => !!route.params.id)
const id = computed(() => Number(route.params.id))
const saving = ref(false)
const showCustomerModal = ref(false)
const customerSearch = ref('')

const form = ref({
  customer: null as any,
  customer_id: null as number | null,
  invoice_number: '',
  invoice_date: new Date().toISOString().split('T')[0],
  due_date: '',
  notes: '',
  items: [{ name: '', quantity: 1, price: 0, unit_name: 'unidad' }],
})

const filteredCustomers = computed(() =>
  customersStore.list.filter((c) =>
    c.name.toLowerCase().includes(customerSearch.value.toLowerCase())
  )
)

const subtotal = computed(() =>
  form.value.items.reduce((sum, i) => sum + i.quantity * i.price * 100, 0)
)
const total = computed(() => subtotal.value)

const currency = authStore.currency
function formatMoney(amount: number) {
  const sym = currency?.symbol ?? '$'
  const precision = currency?.precision ?? 2
  return `${sym}${(amount / 100).toFixed(precision)}`
}

function addItem() {
  form.value.items.push({ name: '', quantity: 1, price: 0, unit_name: 'unidad' })
}
function removeItem(idx: number) {
  form.value.items.splice(idx, 1)
}

function openCustomerSelect() {
  if (!customersStore.list.length) customersStore.fetchCustomers()
  showCustomerModal.value = true
}

function selectCustomer(c: any) {
  form.value.customer = c
  form.value.customer_id = c.id
  showCustomerModal.value = false
}

async function save() {
  if (!form.value.customer_id) {
    const t = await toastController.create({ message: 'Selecciona un cliente', duration: 2000, color: 'warning' })
    t.present(); return
  }
  saving.value = true
  try {
    const payload = {
      customer_id: form.value.customer_id,
      invoice_number: form.value.invoice_number,
      invoice_date: form.value.invoice_date,
      due_date: form.value.due_date,
      notes: form.value.notes,
      items: form.value.items.map((i) => ({ ...i, price: Math.round(i.price * 100), total: Math.round(i.quantity * i.price * 100) })),
      sub_total: subtotal.value,
      total: total.value,
      tax: 0,
      discount: 0,
      discount_type: 'fixed',
    }
    if (isEdit.value) {
      await store.updateInvoice(id.value, payload)
      router.replace(`/invoices/${id.value}`)
    } else {
      const inv = await store.createInvoice(payload)
      router.replace(`/invoices/${inv.id}`)
    }
  } catch (e: any) {
    const t = await toastController.create({ message: e.response?.data?.message ?? 'Error al guardar', duration: 3000, color: 'danger' })
    t.present()
  } finally {
    saving.value = false
  }
}

async function loadNextNumber() {
  const { data } = await http.get('/next-number', { params: { type: 'invoice' } })
  form.value.invoice_number = data.nextNumber
}

onMounted(async () => {
  if (isEdit.value) {
    await store.fetchInvoice(id.value)
    const inv = store.current
    form.value = {
      customer: inv.customer,
      customer_id: inv.customer_id,
      invoice_number: inv.invoice_number,
      invoice_date: inv.invoice_date,
      due_date: inv.due_date,
      notes: inv.notes ?? '',
      items: inv.items.map((i: any) => ({ name: i.name, quantity: i.quantity, price: i.price / 100, unit_name: i.unit_name ?? 'unidad' })),
    }
  } else {
    await loadNextNumber()
  }
})
</script>
