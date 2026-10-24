<template>
  <div>
    <sw-transition type="fade">
      <div
        v-show="isSidebarOpen"
        class="fixed top-0 left-0 z-20 w-full h-full"
        style="background: rgba(48, 75, 88, 0.5)"
        @click.prevent="toggleSidebar"
      ></div>
    </sw-transition>

    <div
      class="hidden w-56 h-screen pb-32 overflow-y-auto bg-white border-r border-gray-200 border-solid xl:w-64 sw-scroll md:block"
    >
      <div v-for="group in menuGroups" :key="group.title" class="mb-2">
        <div
          class="px-5 pt-5 pb-1 text-xs font-semibold tracking-wider text-gray-400 uppercase"
        >
          {{ group.title }}
        </div>
        <sw-list variant="sidebar">
          <sw-list-item
            v-for="item in group.items"
            :title="item.title"
            :key="item.route"
            :active="hasActiveUrl(item.route)"
            :to="item.route"
            tag-name="router-link"
          >
            <component slot="icon" :is="item.icon" class="h-5" />
          </sw-list-item>
        </sw-list>
      </div>
    </div>

    <transition
      enter-class="-translate-x-full"
      enter-active-class="transition duration-300 ease-in-out transform"
      enter-to-class="translate-x-0"
      leave-active-class="transition duration-300 ease-in-out transform"
      leave-class="translate-x-0"
      leave-to-class="-translate-x-full"
    >
      <div
        v-show="isSidebarOpen"
        class="fixed top-0 z-30 w-64 h-screen pt-16 pb-32 overflow-y-auto bg-white border-r border-gray-200 border-solid sw-scroll md:hidden"
      >
        <div v-for="group in menuGroups" :key="group.title" class="mb-2">
          <div
            class="px-5 pt-5 pb-1 text-xs font-semibold tracking-wider text-gray-400 uppercase"
          >
            {{ group.title }}
          </div>
          <sw-list variant="sidebar">
            <sw-list-item
              v-for="item in group.items"
              :title="item.title"
              :key="item.route"
              :active="hasActiveUrl(item.route)"
              :to="item.route"
              tag-name="router-link"
              @click.native="toggleSidebar"
            >
              <component slot="icon" :is="item.icon" class="h-5" />
            </sw-list-item>
          </sw-list>
        </div>
      </div>
    </transition>
  </div>
</template>

<script type="text/babel">
import { navigationLabel, settingsLabel } from '../../../helpers/navigation'
import { can, canSeeSchoolDashboard, canSeeAttendancePanel } from '../../../helpers/access'
import {
  HomeIcon,
  UserIcon,
  StarIcon,
  DocumentIcon,
  DocumentTextIcon,
  CreditCardIcon,
  CalculatorIcon,
  ChartBarIcon,
  CogIcon,
  UsersIcon,
  AcademicCapIcon,
  ClipboardCheckIcon,
  UserGroupIcon,
  PresentationChartBarIcon,
} from '@vue-hero-icons/outline'
import { mapGetters, mapActions } from 'vuex'

export default {
  components: {
    HomeIcon,
    UserIcon,
    StarIcon,
    DocumentIcon,
    DocumentTextIcon,
    CreditCardIcon,
    CalculatorIcon,
    ChartBarIcon,
    CogIcon,
    UsersIcon,
    AcademicCapIcon,
    ClipboardCheckIcon,
    UserGroupIcon,
    PresentationChartBarIcon,
  },

  computed: {
    ...mapGetters(['isSidebarOpen']),
    ...mapGetters('user', ['currentUser']),

    isTotalAdmin() {
      return Boolean(
        this.currentUser &&
          this.currentUser.is_total_admin === true
      )
    },

    canManageStaff() {
      return can(this.currentUser, 'hr.staff.view') || can(this.currentUser, 'hr.staff.onboard')
        || can(this.currentUser, 'hr.payroll.view')
    },

    menuGroups() {
      const groups = [
        {
          title: 'Inicio',
          items: [
            {
              title: navigationLabel('navigation.dashboard'),
              icon: 'home-icon',
              route: '/admin/dashboard',
            },
          ],
        },
        {
          title: 'Gestión académica',
          items: [
            {
              title: navigationLabel('navigation.students'),
              icon: 'academic-cap-icon',
              route: '/admin/students',
            },
            {
              title: navigationLabel('navigation.customers'),
              icon: 'user-icon',
              route: '/admin/customers',
            },
          ],
        },
        {
          title: 'Asistencia',
          items: [
            { title: 'Panel de asistencia', icon: 'presentation-chart-bar-icon', route: '/admin/attendance/panel' },
            { title: 'Tomar asistencia', icon: 'clipboard-check-icon', route: '/admin/attendance' },
            { title: 'Asistencia del personal', icon: 'user-group-icon', route: '/admin/attendance/staff' },
          ],
        },
        {
          title: 'Administración económica',
          items: [
            {
              title: navigationLabel('navigation.items'),
              icon: 'star-icon',
              route: '/admin/items',
            },
            {
              title: navigationLabel('navigation.estimates'),
              icon: 'document-icon',
              route: '/admin/estimates',
            },
            {
              title: navigationLabel('navigation.invoices'),
              icon: 'document-text-icon',
              route: '/admin/invoices',
            },
            {
              title: navigationLabel('navigation.payments'),
              icon: 'credit-card-icon',
              route: '/admin/payments',
            },
            {
              title: navigationLabel('navigation.expenses'),
              icon: 'calculator-icon',
              route: '/admin/expenses',
            },
          ],
        },
      ]

      if (this.canManageStaff) {
        groups.push({
          title: 'Recursos Humanos',
          items: [
            {
              title: 'Personal',
              icon: 'users-icon',
              route: '/admin/staff',
            },
            {
              title: 'Liquidaciones y pagos',
              icon: 'credit-card-icon',
              route: '/admin/payroll',
            },
          ],
        })
      }

      // Fila E1: el acceso se administra desde la ficha de Personal.
      const systemItems = []
      systemItems.push({
        title: settingsLabel(this.currentUser),
        icon: 'cog-icon',
        route: '/admin/settings',
      })

      groups.push({
        title: 'Informes',
        items: [
          {
            title: navigationLabel('navigation.reports'),
            icon: 'chart-bar-icon',
            route: '/admin/reports',
          },
        ],
      })

      groups.push({
        title: 'Sistemas y accesos',
        items: systemItems,
      })

      const required = {
        '/admin/students': 'students.view_basic',
        '/admin/attendance': 'attendance.record', '/admin/attendance/staff': 'hr.attendance.view',
        '/admin/customers': 'finance.view', '/admin/items': 'finance.view',
        '/admin/estimates': 'finance.view', '/admin/invoices': 'finance.view',
        '/admin/payments': 'finance.view', '/admin/expenses': 'finance.view',
        '/admin/staff': ['hr.staff.view', 'hr.staff.onboard'], '/admin/payroll': 'hr.payroll.view',
        '/admin/reports': 'finance.report.view',
      }
      return groups.map(group => ({ ...group, items: group.items.filter(item =>
        item.route === '/admin/dashboard' ? canSeeSchoolDashboard(this.currentUser)
          : item.route === '/admin/attendance/panel' ? canSeeAttendancePanel(this.currentUser)
          : !required[item.route] || [].concat(required[item.route]).some(permission => can(this.currentUser, permission)))
      })).filter(group => group.items.length > 0)
    },
  },

  methods: {
    ...mapActions(['toggleSidebar']),

    hasActiveUrl(url) {
      this.isActive = true
      // Tomar asistencia comparte prefijo con el panel y el personal: solo exacta.
      if (url === '/admin/attendance') return this.$route.path === url
      return this.$route.path.indexOf(url) > -1
    },
  },
}
</script>
