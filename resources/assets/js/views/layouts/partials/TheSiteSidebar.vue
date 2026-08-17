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
            :title="$t(item.title)"
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
              :title="$t(item.title)"
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
  ShieldCheckIcon,
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
    ShieldCheckIcon,
  },

  computed: {
    ...mapGetters(['isSidebarOpen']),
    ...mapGetters('user', ['currentUser']),

    isTotalAdmin() {
      return Boolean(
        this.currentUser &&
          (this.currentUser.is_total_admin === true ||
            this.currentUser.rbac_role === 'total_admin' ||
            this.currentUser.role === 'super admin')
      )
    },

    canManageStaff() {
      return Boolean(
        this.isTotalAdmin ||
          (this.currentUser &&
            ['admin', 'super admin'].includes(this.currentUser.role))
      )
    },

    menuGroups() {
      const groups = [
        {
          title: 'Inicio',
          items: [
            {
              title: 'navigation.dashboard',
              icon: 'home-icon',
              route: '/admin/dashboard',
            },
          ],
        },
        {
          title: 'Gestión académica',
          items: [
            {
              title: 'navigation.students',
              icon: 'academic-cap-icon',
              route: '/admin/students',
            },
            {
              title: 'navigation.customers',
              icon: 'user-icon',
              route: '/admin/customers',
            },
          ],
        },
        {
          title: 'Administración económica',
          items: [
            {
              title: 'navigation.items',
              icon: 'star-icon',
              route: '/admin/items',
            },
            {
              title: 'navigation.estimates',
              icon: 'document-icon',
              route: '/admin/estimates',
            },
            {
              title: 'navigation.invoices',
              icon: 'document-text-icon',
              route: '/admin/invoices',
            },
            {
              title: 'navigation.payments',
              icon: 'credit-card-icon',
              route: '/admin/payments',
            },
            {
              title: 'navigation.expenses',
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
          ],
        })
      }

      const systemItems = []
      if (this.isTotalAdmin) {
        systemItems.push({
          title: 'navigation.users',
          icon: 'shield-check-icon',
          route: '/admin/users',
        })
      }
      systemItems.push({
        title: 'navigation.settings',
        icon: 'cog-icon',
        route: '/admin/settings',
      })

      groups.push({
        title: 'Informes',
        items: [
          {
            title: 'navigation.reports',
            icon: 'chart-bar-icon',
            route: '/admin/reports',
          },
        ],
      })

      groups.push({
        title: 'Sistemas y accesos',
        items: systemItems,
      })

      return groups.filter((group) => group.items.length > 0)
    },
  },

  methods: {
    ...mapActions(['toggleSidebar']),

    hasActiveUrl(url) {
      this.isActive = true
      return this.$route.path.indexOf(url) > -1
    },
  },
}
</script>
