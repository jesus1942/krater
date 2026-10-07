import { defineStore } from 'pinia'
import { ref } from 'vue'
import http from '@/services/http'

export const useCustomersStore = defineStore('customers', () => {
  const list = ref<any[]>([])
  const current = ref<any>(null)
  const loading = ref(false)
  const meta = ref<any>({ current_page: 1, last_page: 1, total: 0 })

  async function fetchCustomers(params: Record<string, any> = {}) {
    loading.value = true
    const { data } = await http.get('/customers', { params })
    list.value = data.customers.data
    meta.value = { current_page: data.customers.current_page, last_page: data.customers.last_page, total: data.customers.total }
    loading.value = false
  }

  async function fetchCustomer(id: number) {
    const { data } = await http.get(`/customers/${id}`)
    current.value = data.customer
  }

  async function createCustomer(payload: any) {
    const { data } = await http.post('/customers', payload)
    list.value.unshift(data.customer)
    return data.customer
  }

  async function updateCustomer(id: number, payload: any) {
    const { data } = await http.put(`/customers/${id}`, payload)
    return data.customer
  }

  async function deleteCustomer(id: number) {
    await http.delete(`/customers/${id}`)
    list.value = list.value.filter((c) => c.id !== id)
  }

  async function fetchStats(id: number) {
    const { data } = await http.get(`/customers/${id}/stats`)
    return data
  }

  return { list, current, loading, meta, fetchCustomers, fetchCustomer, createCustomer, updateCustomer, deleteCustomer, fetchStats }
})
