import { defineStore } from 'pinia'
import { ref } from 'vue'
import http from '@/services/http'

export const usePaymentsStore = defineStore('payments', () => {
  const list = ref<any[]>([])
  const current = ref<any>(null)
  const methods = ref<any[]>([])
  const loading = ref(false)
  const meta = ref<any>({ current_page: 1, last_page: 1, total: 0 })

  async function fetchPayments(params: Record<string, any> = {}) {
    loading.value = true
    const { data } = await http.get('/payments', { params })
    list.value = data.payments.data
    meta.value = { current_page: data.payments.current_page, last_page: data.payments.last_page, total: data.payments.total }
    loading.value = false
  }

  async function fetchPayment(id: number) {
    const { data } = await http.get(`/payments/${id}`)
    current.value = data.payment
  }

  async function createPayment(payload: any) {
    const { data } = await http.post('/payments', payload)
    return data.payment
  }

  async function updatePayment(id: number, payload: any) {
    const { data } = await http.put(`/payments/${id}`, payload)
    return data.payment
  }

  async function deletePayment(id: number) {
    await http.delete(`/payments/${id}`)
    list.value = list.value.filter((p) => p.id !== id)
  }

  async function sendReceipt(id: number, payload: any) {
    await http.post(`/payments/${id}/send`, payload)
  }

  async function fetchMethods() {
    const { data } = await http.get('/payment-methods')
    methods.value = data.payment_methods
  }

  return { list, current, methods, loading, meta, fetchPayments, fetchPayment, createPayment, updatePayment, deletePayment, sendReceipt, fetchMethods }
})
