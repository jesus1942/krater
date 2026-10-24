import { defineStore } from 'pinia'
import { ref } from 'vue'
import http from '@/services/http'

export const useItemsStore = defineStore('items', () => {
  const list = ref<any[]>([])
  const units = ref<any[]>([])
  const loading = ref(false)
  const meta = ref<any>({ current_page: 1, last_page: 1, total: 0 })

  async function fetchItems(params: Record<string, any> = {}) {
    loading.value = true
    const { data } = await http.get('/items', { params })
    list.value = data.items.data
    meta.value = { current_page: data.items.current_page, last_page: data.items.last_page, total: data.items.total }
    loading.value = false
  }

  async function createItem(payload: any) {
    const { data } = await http.post('/items', payload)
    return data.item
  }

  async function updateItem(id: number, payload: any) {
    const { data } = await http.put(`/items/${id}`, payload)
    return data.item
  }

  async function deleteItem(id: number) {
    await http.delete(`/items/${id}`)
    list.value = list.value.filter((i) => i.id !== id)
  }

  async function fetchUnits() {
    const { data } = await http.get('/units')
    units.value = data.units
  }

  return { list, units, loading, meta, fetchItems, createItem, updateItem, deleteItem, fetchUnits }
})
