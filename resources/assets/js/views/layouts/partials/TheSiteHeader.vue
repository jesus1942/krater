<template>
  <header
    class="ena-site-header fixed top-0 left-0 z-40 flex items-center justify-between w-full px-4 py-3 md:h-16 md:px-8"
  >
    <a
      href="/admin/dashboard"
      class="ena-header-brand float-none not-italic brand-main md:float-left font-base"
      :class="{ 'ena-header-brand--institution': !selectedLevel }"
    >
      <img
        id="ena-header-owl"
        src="/images/ena-owl.svg"
        alt="Escuela Nueva Austral"
        class="ena-header-owl"
      />
      <span v-if="selectedLevel" class="ena-header-brand__copy">
        <strong class="ena-header-brand__institution">Escuela Nueva Austral</strong>
        <span class="ena-header-brand__level">
          {{ selectedLevel.name }}
          <b v-if="selectedLevel.registration_number">
            · N.º {{ selectedLevel.registration_number }}
          </b>
        </span>
      </span>
    </a>

    <div class="flex items-center ml-auto mr-2 md:mr-4">
      <select
        v-if="schoolLevels.length || isTotalAdmin"
        v-model="selectedLevelId"
        aria-label="Nivel institucional activo"
        class="ena-level-select w-24 h-9 px-2 text-xs font-semibold md:w-56 md:text-sm"
        @change="changeLevel"
      >
        <option v-if="isTotalAdmin" value="">Toda la institución</option>
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
    isTotalAdmin() {
      return Boolean(
        this.currentUser &&
          (this.currentUser.is_total_admin === true ||
            this.currentUser.rbac_role === 'total_admin' ||
            this.currentUser.role === 'super admin')
      )
    },
    selectedLevel() {
      return this.schoolLevels.find(
        (level) => String(level.id) === String(this.selectedLevelId)
      ) || null
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
  async created() {
    await this.fetchCurrentUser()
    await this.fetchSchoolLevels()
  },
  methods: {
    async fetchSchoolLevels() {
      const response = await window.axios.get('/api/v1/school-levels')
      this.schoolLevels = response.data.levels.filter((level) => level.enabled)

      const selectedIsAllowed = this.schoolLevels.some(
        (level) => String(level.id) === String(this.selectedLevelId)
      )

      if (this.isTotalAdmin) {
        if (this.selectedLevelId && !selectedIsAllowed) {
          window.Ls.remove('selectedSchoolLevel')
          this.selectedLevelId = ''
        }
        return
      }

      if (!selectedIsAllowed) {
        if (this.schoolLevels.length) {
          this.selectedLevelId = String(this.schoolLevels[0].id)
          window.Ls.set('selectedSchoolLevel', this.selectedLevelId)
          window.location.reload()
        } else {
          window.Ls.remove('selectedSchoolLevel')
          this.selectedLevelId = ''
        }
      }
    },
    changeLevel() {
      if (!this.isTotalAdmin && !this.selectedLevelId) {
        return
      }

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
  gap: 8px;
  min-width: 54px;
  max-width: 180px;
  height: 48px;
  padding: 3px 12px 3px 8px;
  background: #f4f0e7;
  border-left: 4px solid #a5121c;
  border-radius: 0 12px 12px 0;
}

.ena-header-brand--institution {
  width: 46px;
  min-width: 46px;
  padding-right: 7px;
}

.ena-header-owl {
  width: 23px;
  height: 42px;
  flex: 0 0 auto;
  object-fit: contain;
}

.ena-header-brand__copy {
  display: flex;
  min-width: 0;
  flex-direction: column;
  color: #102340;
  line-height: 1.05;
}

.ena-header-brand__institution {
  overflow: hidden;
  font-size: 10px;
  font-weight: 900;
  letter-spacing: 0.045em;
  text-overflow: ellipsis;
  text-transform: uppercase;
  white-space: nowrap;
}

.ena-header-brand__level {
  display: block;
  margin-top: 3px;
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 0.015em;
}

@media (min-width: 768px) {
  .ena-header-brand {
    max-width: 310px;
    gap: 11px;
    padding-right: 18px;
  }

  .ena-header-brand__institution {
    font-size: 12px;
  }

  .ena-header-brand__level {
    font-size: 11px;
  }
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
