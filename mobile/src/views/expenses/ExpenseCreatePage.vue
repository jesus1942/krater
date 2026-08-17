<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <ion-buttons slot="start">
          <ion-back-button default-href="/tabs/expenses" />
        </ion-buttons>
        <ion-title>{{ isEdit ? 'Editar gasto' : 'Nuevo gasto' }}</ion-title>
        <ion-buttons slot="end">
          <ion-button :disabled="saving" @click="save">
            <ion-spinner v-if="saving" name="crescent" />
            <span v-else>Guardar</span>
          </ion-button>
        </ion-buttons>
      </ion-toolbar>
    </ion-header>

    <ion-content color="light">
      <ion-card>
        <ion-card-content class="ion-no-padding">
          <ion-item lines="full">
            <ion-label position="stacked">Monto *</ion-label>
            <ion-input v-model.number="form.amount" type="number" min="0" step="0.01" placeholder="0.00" />
          </ion-item>
          <ion-item lines="full">
            <ion-label position="stacked">Fecha *</ion-label>
            <ion-input v-model="form.expense_date" type="date" />
          </ion-item>
          <ion-item lines="full" button @click="openCategorySelect">
            <ion-label position="stacked">Categoria</ion-label>
            <ion-input :value="form.category?.name ?? ''" readonly placeholder="Seleccionar..." />
            <ion-icon slot="end" :icon="chevronForwardOutline" color="medium" />
          </ion-item>
          <ion-item lines="none">
            <ion-label position="stacked">Notas</ion-label>
            <ion-textarea v-model="form.notes" :rows="3" placeholder="Descripcion del gasto..." />
          </ion-item>
        </ion-card-content>
      </ion-card>
    </ion-content>

    <ion-modal :is-open="showCategoryModal" @didDismiss="showCategoryModal = false">
      <ion-header>
        <ion-toolbar>
          <ion-title>Categoria</ion-title>
          <ion-buttons slot="end">
            <ion-button @click="showCategoryModal = false">Cerrar</ion-button>
          </ion-buttons>
        </ion-toolbar>
      </ion-header>
      <ion-content>
        <ion-list>
          <ion-item v-for="cat in store.categories" :key="cat.id" button @click="selectCategory(cat)">
            <ion-label>{{ cat.name }}</ion-label>
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
  IonBackButton, IonIcon, IonCard, IonCardContent, IonItem, IonLabel, IonInput,
  IonTextarea, IonSpinner, IonModal, IonList, toastController,
} from '@ionic/vue'
import { chevronForwardOutline } from 'ionicons/icons'
import { useExpensesStore } from '@/stores/expenses'

const route = useRoute()
const router = useRouter()
const store = useExpensesStore()

const isEdit = computed(() => !!route.params.id)
const id = computed(() => Number(route.params.id))
const saving = ref(false)
const showCategoryModal = ref(false)

const form = ref({
  amount: 0,
  expense_date: new Date().toISOString().split('T')[0],
  category: null as any,
  expense_category_id: null as number | null,
  notes: '',
})

function openCategorySelect() {
  if (!store.categories.length) store.fetchCategories()
  showCategoryModal.value = true
}

function selectCategory(cat: any) {
  form.value.category = cat
  form.value.expense_category_id = cat.id
  showCategoryModal.value = false
}

async function save() {
  saving.value = true
  try {
    const payload = {
      amount: Math.round(form.value.amount * 100),
      expense_date: form.value.expense_date,
      expense_category_id: form.value.expense_category_id,
      notes: form.value.notes,
    }
    if (isEdit.value) {
      await store.updateExpense(id.value, payload)
    } else {
      await store.createExpense(payload)
    }
    router.back()
  } catch (e: any) {
    const t = await toastController.create({ message: e.response?.data?.message ?? 'Error al guardar', duration: 3000, color: 'danger' })
    t.present()
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  if (isEdit.value) {
    await store.fetchExpense(id.value)
    const exp = store.current
    form.value = {
      amount: exp.amount / 100,
      expense_date: exp.expense_date,
      category: exp.category,
      expense_category_id: exp.expense_category_id,
      notes: exp.notes ?? '',
    }
  }
})
</script>
