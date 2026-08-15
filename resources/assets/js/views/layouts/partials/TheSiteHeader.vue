<template>
  <header
    class="ena-site-header fixed top-0 left-0 z-40 flex items-center justify-between w-full px-4 py-3 md:h-16 md:px-8"
  >
    <a
      href="/admin/dashboard"
      class="ena-header-brand float-none text-lg not-italic font-black tracking-wider text-white brand-main md:float-left font-base"
    >
      <img
        v-if="companyLogo"
        id="logo-white"
        :src="companyLogo"
        alt="Escuela Nueva Austral"
        @error="useFallback($event, '/images/ena-logo.svg')"
        class="ena-header-lockup hidden md:block"
      />
      <span v-else class="hidden md:block">ENA srl</span>
      <img
        v-if="companyLogo"
        id="logo-mobile"
        src="/images/ena-owl.svg"
        alt="Escuela Nueva Austral"
        @error="useFallback($event, '/images/ena-logo.svg')"
        class="ena-header-owl block md:hidden"
      />
      <span v-else class="block md:hidden">ENA</span>
    </a>

    <div class="flex items-center ml-auto mr-2 md:mr-4">
      <select
        v-model="selectedLevelId"
        aria-label="Nivel institucional activo"
        class="ena-level-select w-32 h-9 px-2 text-xs font-semibold md:w-56 md:text-sm"
        @change="changeLevel"
      >
        <option value="">Toda la institución</option>
        <option v-for="level in schoolLevels" :key="level.id" :value="String(level.id)">
          {{ level.name }}
        </option>
      </select>
    </div>

    <ul class="float-right h-8 m-0 list-none md:h-9">
      <global-search class="hidden float-left mr-2 md:block" />

      <a
        :class="{ 'is-active': isSidebarOpen }"
        href="#"
        class="flex float-left p-1 ml-3 overflow-visible text-sm text-black ease-linear bg-white border-0 rounded cursor-pointer md:hidden md:ml-0 hamburger hamburger--arrowturn"
        @click="toggleSidebar"
      >
        <div class="relative inline-block w-6 h-6">
          <div class="block hamburger-inner top-1/2" />
        </div>
      </a>

      <li class="relative hidden float-left m-0 md:block">
        <sw-dropdown>
          <a
            slot="activator"
            href="#"
            style="padding: 6px"
            class="inline-block text-sm text-black bg-white rounded-sm"
          >
            <plus-icon class="w-6 h-6" />
          </a>

          <sw-dropdown-item tag-name="router-link" to="/admin/invoices/create">
            <document-text-icon class="h-5 mr-2 text-gray-600" />
            {{ $t('invoices.new_invoice') }}
          </sw-dropdown-item>

          <sw-dropdown-item tag-name="router-link" to="/admin/estimates/create">
            <document-icon class="h-5 mr-2 text-gray-600" />
            {{ $t('estimates.new_estimate') }}
          </sw-dropdown-item>

          <sw-dropdown-item tag-name="router-link" to="/admin/customers/create">
            <user-icon class="h-5 mr-2 text-gray-600" />
            {{ $t('customers.new_customer') }}
          </sw-dropdown-item>
        </sw-dropdown>
      </li>

      <li class="relative block float-left ml-2">
        <sw-dropdown>
          <a
            slot="activator"
            href="#"
            data-toggle="dropdown"
            aria-haspopup="true"
            aria-expanded="false"
            class="inline-block text-sm text-black bg-white rounded-sm avatar"
          >
            <img
              :src="profilePicture"
              alt="Avatar"
              @error="useFallback($event, '/images/ena-owl-avatar.png')"
              class="w-8 h-8 rounded-sm md:h-9 md:w-9"
            />
          </a>

          <sw-dropdown-item tag-name="router-link" to="/admin/settings">
            <cog-icon class="w-4 h-4 mr-2 text-gray-600" />
            {{ $t('navigation.settings') }}
          </sw-dropdown-item>

          <sw-dropdown-item @click="logout">
            <logout-icon class="w-4 h-4 mr-2 text-gray-600" />
            {{ $t('navigation.logout') }}
          </sw-dropdown-item>
        </sw-dropdown>
      </li>
    </ul>
  </header>
</template>

<script type="text/babel">
import { mapGetters, mapActions } from 'vuex'
import {
  PlusIcon,
  DocumentTextIcon,
  DocumentIcon,
  UserIcon,
  CogIcon,
} from '@vue-hero-icons/solid'

import { LogoutIcon } from '@vue-hero-icons/outline'

export default {
  components: {
    PlusIcon,
    DocumentTextIcon,
    DocumentIcon,
    UserIcon,
    CogIcon,
    LogoutIcon,
  },
  data() {
    return {
      schoolLevels: [],
      selectedLevelId: window.Ls.get('selectedSchoolLevel') || '',
    }
  },
  computed: {
    ...mapGetters('user', ['currentUser']),
    ...mapGetters(['isSidebarOpen']),
    ...mapGetters('company', {
      selectedCompany: 'getSelectedCompany',
    }),
    companyLogo() {
      return this.selectedCompany && this.selectedCompany.logo
        ? this.selectedCompany.logo
        : '/images/ena-logo.svg'
    },
    profilePicture() {
      if (
        this.currentUser &&
        this.currentUser.avatar !== null &&
        this.currentUser.avatar !== 0
      ) {
        return this.currentUser.avatar
      } else {
        return '/images/ena-owl-avatar.png'
      }
    },
  },
  created() {
    this.fetchCurrentUser()
    this.fetchSchoolLevels()
  },
  methods: {
    async fetchSchoolLevels() {
      const response = await window.axios.get('/api/v1/school-levels')
      this.schoolLevels = response.data.levels.filter((level) => level.enabled)
      if (
        this.selectedLevelId &&
        !this.schoolLevels.some((level) => String(level.id) === String(this.selectedLevelId))
      ) {
        window.Ls.remove('selectedSchoolLevel')
        this.selectedLevelId = ''
      }
    },
    changeLevel() {
      if (this.selectedLevelId) {
        window.Ls.set('selectedSchoolLevel', this.selectedLevelId)
      } else {
        window.Ls.remove('selectedSchoolLevel')
      }
      window.location.reload()
    },
    useFallback(event, path) {
      event.target.onerror = null
      event.target.src = path
    },
    ...mapActions('user', ['fetchCurrentUser']),
    ...mapActions('auth', ['logout']),
    ...mapActions('modal', ['openModal']),
    ...mapActions(['toggleSidebar']),
  },
}
</script>
<style lang="scss">
.ena-site-header {
  background: #102340;
  border-bottom: 3px solid #a5121c;
  box-shadow: 0 8px 24px rgba(7, 16, 29, 0.18);
}

.ena-header-brand {
  display: flex;
  align-items: center;
  min-width: 54px;
  padding: 4px 14px 4px 9px;
  background: #f4f0e7;
  border-left: 4px solid #a5121c;
  border-radius: 0 999px 999px 0;
}

.ena-header-lockup {
  width: 215px;
  height: 42px;
  object-fit: contain;
  object-position: left center;
}

.ena-header-owl {
  width: 25px;
  height: 42px;
  object-fit: contain;
}

.ena-level-select {
  color: #102340;
  background: rgba(244, 240, 231, 0.96);
  border: 1px solid rgba(255, 255, 255, 0.65);
  border-radius: 999px;
  box-shadow: 0 6px 18px rgba(0, 0, 0, 0.14);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.ena-level-select:hover,
.ena-level-select:focus {
  transform: translateY(-1px);
  box-shadow: 0 9px 22px rgba(0, 0, 0, 0.2);
  outline: none;
}

.hamburger {
  transition-property: opacity, filter;
  transition-duration: 0.15s;
}
.hamburger-inner {
  top: 50%;
  left: 4.5px;
  right: 4.5px;
}
.hamburger-inner,
.hamburger-inner::before,
.hamburger-inner::after {
  height: 2px;
  background-color: black;
  border-radius: 2px;
  position: absolute;
  transition-property: transform;
  transition-duration: 0.15s;
  transition-timing-function: ease;
}

.hamburger-inner::before,
.hamburger-inner::after {
  content: '';
  display: block;
  width: 100%;
}

.hamburger-inner::before {
  top: -5px;
}

.hamburger-inner::after {
  bottom: -5px;
}

.hamburger--arrowturn.is-active .hamburger-inner {
  transform: rotate(-180deg);
}

.hamburger--arrowturn.is-active .hamburger-inner::before {
  transform: translate3d(5px, 3px, 0) rotate(45deg) scale(0.5, 1);
}

.hamburger--arrowturn.is-active .hamburger-inner::after {
  transform: translate3d(5px, -3px, 0) rotate(-45deg) scale(0.5, 1);
}
</style>
