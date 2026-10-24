import { defineStore } from 'pinia'
import { ref } from 'vue'
import http from '@/services/http'

export const useDashboardStore = defineStore('dashboard', () => {
  const loaded = ref(false)
  const totalDueAmount = ref(0)
  const contacts = ref(0)
  const invoices = ref(0)
  const estimates = ref(0)
  const totalSales = ref(0)
  const totalReceipts = ref(0)
  const totalExpenses = ref(0)
  const netProfit = ref(0)
  const recentInvoices = ref<any[]>([])
  const recentEstimates = ref<any[]>([])
  const chartData = ref<any>({ months: [], invoiceTotals: [], expenseTotals: [], netProfits: [] })

  async function fetchDashboard() {
    const { data } = await http.get('/dashboard')
    totalDueAmount.value = data.due_amount
    contacts.value = data.contacts_count
    invoices.value = data.invoices_count
    estimates.value = data.estimates_count
    totalSales.value = data.sales_total
    totalReceipts.value = data.total_receipts
    totalExpenses.value = data.total_expenses
    netProfit.value = data.net_profit
    recentInvoices.value = data.recent_invoices ?? []
    recentEstimates.value = data.recent_estimates ?? []
    chartData.value = data.chart_data ?? chartData.value
    loaded.value = true
  }

  return {
    loaded,
    totalDueAmount,
    contacts,
    invoices,
    estimates,
    totalSales,
    totalReceipts,
    totalExpenses,
    netProfit,
    recentInvoices,
    recentEstimates,
    chartData,
    fetchDashboard,
  }
})
