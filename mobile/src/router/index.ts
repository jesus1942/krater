import { createRouter, createWebHashHistory } from '@ionic/vue-router'
import type { RouteRecordRaw } from 'vue-router'

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    redirect: '/tabs/dashboard',
  },
  {
    path: '/auth/login',
    component: () => import('@/views/auth/LoginPage.vue'),
    meta: { public: true },
  },
  {
    path: '/auth/forgot-password',
    component: () => import('@/views/auth/ForgotPasswordPage.vue'),
    meta: { public: true },
  },
  {
    path: '/tabs',
    component: () => import('@/views/TabsPage.vue'),
    children: [
      { path: '', redirect: '/tabs/dashboard' },
      {
        path: 'dashboard',
        component: () => import('@/views/dashboard/DashboardPage.vue'),
      },
      {
        path: 'invoices',
        component: () => import('@/views/invoices/InvoicesPage.vue'),
      },
      {
        path: 'expenses',
        component: () => import('@/views/expenses/ExpensesPage.vue'),
      },
      {
        path: 'more',
        component: () => import('@/views/MorePage.vue'),
      },
    ],
  },
  {
    path: '/invoices/create',
    component: () => import('@/views/invoices/InvoiceCreatePage.vue'),
  },
  {
    path: '/invoices/:id',
    component: () => import('@/views/invoices/InvoiceViewPage.vue'),
  },
  {
    path: '/invoices/:id/edit',
    component: () => import('@/views/invoices/InvoiceCreatePage.vue'),
  },
  {
    path: '/estimates',
    component: () => import('@/views/estimates/EstimatesPage.vue'),
  },
  {
    path: '/estimates/create',
    component: () => import('@/views/estimates/EstimateCreatePage.vue'),
  },
  {
    path: '/estimates/:id',
    component: () => import('@/views/estimates/EstimateViewPage.vue'),
  },
  {
    path: '/estimates/:id/edit',
    component: () => import('@/views/estimates/EstimateCreatePage.vue'),
  },
  {
    path: '/expenses/create',
    component: () => import('@/views/expenses/ExpenseCreatePage.vue'),
  },
  {
    path: '/expenses/:id/edit',
    component: () => import('@/views/expenses/ExpenseCreatePage.vue'),
  },
  {
    path: '/payments',
    component: () => import('@/views/payments/PaymentsPage.vue'),
  },
  {
    path: '/payments/create',
    component: () => import('@/views/payments/PaymentCreatePage.vue'),
  },
  {
    path: '/payments/:id',
    component: () => import('@/views/payments/PaymentViewPage.vue'),
  },
  {
    path: '/customers',
    component: () => import('@/views/customers/CustomersPage.vue'),
  },
  {
    path: '/customers/create',
    component: () => import('@/views/customers/CustomerCreatePage.vue'),
  },
  {
    path: '/customers/:id',
    component: () => import('@/views/customers/CustomerViewPage.vue'),
  },
  {
    path: '/customers/:id/edit',
    component: () => import('@/views/customers/CustomerCreatePage.vue'),
  },
  {
    path: '/items',
    component: () => import('@/views/items/ItemsPage.vue'),
  },
  {
    path: '/items/create',
    component: () => import('@/views/items/ItemCreatePage.vue'),
  },
  {
    path: '/items/:id/edit',
    component: () => import('@/views/items/ItemCreatePage.vue'),
  },
  {
    path: '/reports',
    component: () => import('@/views/reports/ReportsPage.vue'),
  },
  {
    path: '/reports/sales',
    component: () => import('@/views/reports/SalesReportPage.vue'),
  },
  {
    path: '/reports/expenses',
    component: () => import('@/views/reports/ExpensesReportPage.vue'),
  },
  {
    path: '/reports/profit-loss',
    component: () => import('@/views/reports/ProfitLossPage.vue'),
  },
  {
    path: '/reports/taxes',
    component: () => import('@/views/reports/TaxReportPage.vue'),
  },
  {
    path: '/settings',
    component: () => import('@/views/settings/SettingsPage.vue'),
  },
  {
    path: '/settings/company',
    component: () => import('@/views/settings/CompanyInfoPage.vue'),
  },
  {
    path: '/settings/profile',
    component: () => import('@/views/settings/UserProfilePage.vue'),
  },
  {
    path: '/settings/preferences',
    component: () => import('@/views/settings/PreferencesPage.vue'),
  },
  {
    path: '/settings/tax-types',
    component: () => import('@/views/settings/TaxTypesPage.vue'),
  },
  {
    path: '/settings/payment-methods',
    component: () => import('@/views/settings/PaymentMethodsPage.vue'),
  },
  {
    path: '/settings/expense-categories',
    component: () => import('@/views/settings/ExpenseCategoriesPage.vue'),
  },
  {
    path: '/settings/custom-fields',
    component: () => import('@/views/settings/CustomFieldsPage.vue'),
  },
]

const router = createRouter({
  history: createWebHashHistory(import.meta.env.BASE_URL),
  routes,
})

router.beforeEach((to) => {
  const token = localStorage.getItem('auth_token')
  if (!to.meta.public && !token) {
    return '/auth/login'
  }
  if (to.meta.public && token && to.path.startsWith('/auth')) {
    return '/tabs/dashboard'
  }
})

export default router
