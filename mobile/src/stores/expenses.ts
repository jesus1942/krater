import { defineStore } from 'pinia'
import { ref } from 'vue'
import http from '@/services/http'

export const useExpensesStore = defineStore('expenses', () => {
  const list = ref<any[]>([])
  const current = ref<any>(null)
  const categories = ref<any[]>([])
  const loading = ref(false)
  const meta = ref<any>({ current_page: 1, last_page: 1, total: 0 })

  async function fetchExpenses(params: Record<string, any> = {}) {
    loading.value = true
    const { data } = await http.get('/expenses', { params })
    list.value = data.expenses.data
    meta.value = { current_page: data.expenses.current_page, last_page: data.expenses.last_page, total: data.expenses.total }
    loading.value = false
  }

  async function fetchExpense(id: number) {
    const { data } = await http.get(`/expenses/${id}`)
    current.value = data.expense
  }

  async function createExpense(payload: any) {
    const { data } = await http.post('/expenses', payload)
    return data.expense
  }

  async function updateExpense(id: number, payload: any) {
    const { data } = await http.put(`/expenses/${id}`, payload)
    return data.expense
  }

  async function deleteExpense(id: number) {
    await http.delete(`/expenses/${id}`)
    list.value = list.value.filter((e) => e.id !== id)
  }

  async function fetchCategories() {
    const { data } = await http.get('/categories')
    categories.value = data.expense_categories
  }

  async function createCategory(payload: any) {
    const { data } = await http.post('/categories', payload)
    categories.value.push(data.expense_category)
    return data.expense_category
  }

  return { list, current, categories, loading, meta, fetchExpenses, fetchExpense, createExpense, updateExpense, deleteExpense, fetchCategories, createCategory }
})
