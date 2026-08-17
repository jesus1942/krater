import { defineStore } from 'pinia'
import { ref } from 'vue'
import http from '@/services/http'

export const useInvoicesStore = defineStore('invoices', () => {
  const list = ref<any[]>([])
  const current = ref<any>(null)
  const templates = ref<any[]>([])
  const loading = ref(false)
  const meta = ref<any>({ current_page: 1, last_page: 1, total: 0 })

  async function fetchInvoices(params: Record<string, any> = {}) {
    loading.value = true
    const { data } = await http.get('/invoices', { params })
    list.value = data.invoices.data
    meta.value = { current_page: data.invoices.current_page, last_page: data.invoices.last_page, total: data.invoices.total }
    loading.value = false
  }

  async function fetchInvoice(id: number) {
    const { data } = await http.get(`/invoices/${id}`)
    current.value = data.invoice
  }

  async function createInvoice(payload: any) {
    const { data } = await http.post('/invoices', payload)
    return data.invoice
  }

  async function updateInvoice(id: number, payload: any) {
    const { data } = await http.put(`/invoices/${id}`, payload)
    return data.invoice
  }

  async function deleteInvoice(id: number) {
    await http.delete(`/invoices/${id}`)
    list.value = list.value.filter((i) => i.id !== id)
  }

  async function sendInvoice(id: number, payload: any) {
    await http.post(`/invoices/${id}/send`, payload)
  }

  async function changeStatus(id: number, status: string) {
    await http.post(`/invoices/${id}/status`, { status })
  }

  async function cloneInvoice(id: number) {
    const { data } = await http.post(`/invoices/${id}/clone`)
    return data.invoice
  }

  async function fetchTemplates() {
    const { data } = await http.get('/invoices/templates')
    templates.value = data.invoice_templates
  }

  return { list, current, templates, loading, meta, fetchInvoices, fetchInvoice, createInvoice, updateInvoice, deleteInvoice, sendInvoice, changeStatus, cloneInvoice, fetchTemplates }
})
