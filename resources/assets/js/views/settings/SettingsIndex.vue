<template>
  <base-page>
    <div class="pb-6">
      <sw-page-header :title="$tc('settings.setting', 1)">
        <sw-breadcrumb slot="breadcrumbs">
          <sw-breadcrumb-item
            :title="$t('general.home')"
            to="/admin/dashboard"
          />
          <sw-breadcrumb-item
            :title="$tc('settings.setting', 2)"
            to="/admin/settings/user-profile"
            active
          />
        </sw-breadcrumb>
      </sw-page-header>
    </div>

    <div class="w-full mb-6 select-wrapper xl:hidden">
      <sw-select
        :options="visibleMenuItems"
        v-model="currentSetting"
        :searchable="true"
        :show-labels="false"
        :allow-empty="false"
        :custom-label="getCustomLabel"
        @input="navigateToSetting"
      />
    </div>

    <div class="grid md:grid-cols-12">
      <div class="hidden col-span-3 mt-1 xl:block">
        <sw-list>
          <sw-list-item
            v-for="(menuItem, index) in visibleMenuItems"
            :title="$t(menuItem.title)"
            :key="index"
            :to="menuItem.link"
            :active="hasActiveUrl(menuItem.link)"
            tag-name="router-link"
            class="py-3"
          >
            <component slot="icon" :is="menuItem.icon" class="h-5" />
          </sw-list-item>
        </sw-list>
      </div>

      <div class="col-span-12 xl:col-span-9">
        <transition name="fade" mode="out-in">
          <router-view />
        </transition>
      </div>
    </div>
  </base-page>
</template>

<script>
import {
  UserIcon,
  OfficeBuildingIcon,
  AcademicCapIcon,
  CalendarIcon,
  TemplateIcon,
  UserAddIcon,
  BellIcon,
  CheckCircleIcon,
  ClipboardListIcon,
  CubeIcon,
  ClipboardCheckIcon,
} from '@vue-hero-icons/outline'

import {
  RefreshIcon,
  CogIcon,
  MailIcon,
  PencilAltIcon,
  CloudUploadIcon,
  FolderIcon,
  DatabaseIcon,
  CreditCardIcon,
} from '@vue-hero-icons/solid'

export default {
  components: {
    UserIcon,
    OfficeBuildingIcon,
    AcademicCapIcon,
    CalendarIcon,
    TemplateIcon,
    UserAddIcon,
    PencilAltIcon,
    CogIcon,
    CheckCircleIcon,
    ClipboardListIcon,
    MailIcon,
    BellIcon,
    FolderIcon,
    RefreshIcon,
    CubeIcon,
    CloudUploadIcon,
    DatabaseIcon,
    CreditCardIcon,
    ClipboardCheckIcon,
  },

  data() {
    return {
      currentSetting: {
        link: '/admin/settings/user-profile',
        title: 'settings.menu_title.account_settings',
        icon: 'user-icon',
      },
      menuItems: [
        {
          link: '/admin/settings/user-profile',
          title: 'settings.menu_title.account_settings',
          icon: 'user-icon',
        },
        {
          link: '/admin/settings/company-info',
          title: 'settings.menu_title.company_information',
          icon: 'office-building-icon',
        },
        {
          link: '/admin/settings/school-levels',
          title: 'settings.menu_title.school_levels',
          icon: 'academic-cap-icon',
          totalAdminOnly: true,
        },
        {
          link: '/admin/settings/academic-years',
          title: 'settings.menu_title.academic_years',
          icon: 'calendar-icon',
        },
        {
          link: '/admin/settings/academic-structure',
          title: 'Estructura académica',
          icon: 'template-icon',
        },
        {
          link: '/admin/settings/enrollments',
          title: 'settings.menu_title.enrollments',
          icon: 'user-add-icon',
        },
        {
          link: '/admin/settings/audit-logs',
          title: 'settings.menu_title.audit_logs',
          icon: 'clipboard-list-icon',
        },
        {
          link: '/admin/settings/preferences',
          title: 'settings.menu_title.preferences',
          icon: 'cog-icon',
        },
        {
          link: '/admin/settings/customization',
          title: 'settings.menu_title.customization',
          icon: 'pencil-alt-icon',
        },
        {
          link: '/admin/settings/notifications',
          title: 'settings.menu_title.notifications',
          icon: 'bell-icon',
        },
        {
          link: '/admin/settings/tax-types',
          title: 'settings.menu_title.tax_types',
          icon: 'check-circle-icon',
        },
        {
          link: '/admin/settings/payment-mode',
          title: 'settings.menu_title.payment_modes',
          icon: 'credit-card-icon',
        },
        {
          link: '/admin/settings/custom-fields',
          title: 'settings.menu_title.custom_fields',
          icon: 'cube-icon',
        },
        {
          link: '/admin/settings/notes',
          title: 'settings.menu_title.notes',
          icon: 'clipboard-check-icon',
        },
        {
          link: '/admin/settings/expense-category',
          title: 'settings.menu_title.expense_category',
          icon: 'clipboard-list-icon',
        },
        {
          link: '/admin/settings/mail-configuration',
          title: 'settings.mail.mail_config',
          icon: 'mail-icon',
        },
        {
          link: '/admin/settings/file-disk',
          title: 'settings.menu_title.file_disk',
          icon: 'folder-icon',
        },
        {
          link: '/admin/settings/backup',
          title: 'settings.menu_title.backup',
          icon: 'database-icon',
        },
        {
          link: '/admin/settings/update-app',
          title: 'settings.menu_title.update_app',
          icon: 'refresh-icon',
        },
      ],
    }
  },

  computed: {
    isTotalAdmin() {
      const currentUser = this.$store.state.user.currentUser
      return Boolean(
        currentUser &&
          (currentUser.is_total_admin === true ||
            currentUser.rbac_role === 'total_admin' ||
            currentUser.role === 'super admin')
      )
    },
    visibleMenuItems() {
      return this.menuItems.filter(
        (item) => !item.totalAdminOnly || this.isTotalAdmin
      )
    },
  },

  watch: {
    '$route.path'(newValue) {
      if (newValue === '/admin/settings') {
        this.$router.push('/admin/settings/user-profile')
        return
      }

      this.currentSetting = this.visibleMenuItems.find(
        (item) => item.link === newValue
      ) || this.currentSetting
    },
  },

  mounted() {
    this.currentSetting = this.visibleMenuItems.find(
      (item) => item.link == this.$route.path
    ) || this.currentSetting
  },

  created() {
    if (this.$route.path === '/admin/settings') {
      this.$router.push('/admin/settings/user-profile')
    }
  },

  methods: {
    getCustomLabel({ title }) {
      return this.$t(title)
    },
    hasActiveUrl(url) {
      return this.$route.path.indexOf(url) > -1
    },
    navigateToSetting(setting) {
      this.$router.push(setting.link)
    },
  },
}
</script>
