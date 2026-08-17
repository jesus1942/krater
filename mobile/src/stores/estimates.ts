import { defineStore } from 'pinia'
import { ref } from 'vue'
import http from '@/services/http'

export const useEstimatesStore = defineStore('estimates', () => {
  const list = ref<any[]>([])
  const current = ref<any>(null)
  const loading = ref(false)
  const meta = ref<any>({ current_page: 1, last_page: 1, total: 0 })

  async function fetchEstimates(params: Record<string, any> = {}) {
    loading.value = true
    const { data } = await http.get('/estimates', { params })
    list.value = data.estimates.data
    meta.value = { current_page: data.estimates.current_page, last_page: data.estimates.last_page, total: data.estimates.total }
    loading.value = false
  }

  async function fetchEstimate(id: number) {
    const { data } = await http.get(`/estimates/${id}`)
    current.value = data.estimate
  }

  async function createEstimate(payload: any) {
    const { data } = await http.post('/estimates', payload)
    return data.estimate
  }

  async function updateEstimate(id: number, payload: any) {
    const { data } = await http.put(`/estimates/${id}`, payload)
    return data.estimate
  }

  async function deleteEstimate(id: number) {
    await http.delete(`/estimates/${id}`)
    list.value = list.value.filter((e) => e.id !== id)
  }

  async function sendEstimate(id: number, payload: any) {
    await http.post(`/estimates/${id}/send`, payload)
  }

  async function changeStatus(id: number, status: string) {
    await http.post(`/estimates/${id}/status`, { status })
  }

  async function convertToInvoice(id: number) {
    const { data } = await http.post(`/estimates/${id}/convert-to-invoice`)
    return data.invoice
  }

  return { list, current, loading, meta, fetchEstimates, fetchEstimate, createEstimate, updateEstimate, deleteEstimate, sendEstimate, changeStatus, convertToInvoice }
})
