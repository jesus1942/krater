<template>
  <div v-if="isAppLoaded && accessReady && mustChangePassword" class="h-full">
    <base-notification />
    <change-password />
  </div>
  <div v-else-if="isAppLoaded && accessReady" class="h-full">
    <base-modal />
    <base-notification />
    <site-header />
    <div class="flex h-screen pt-16 pb-14 overflow-hidden">
      <site-sidebar />
      <router-view v-if="accessReady && routeAllowed" />
    </div>
    <site-footer />
  </div>
  <div v-else class="h-full">
    <refresh-icon class="h-6 animate-spin" />
  </div>
</template>

<script type="text/babel">
import SiteHeader from './partials/TheSiteHeader.vue'
import SiteFooter from './partials/TheSiteFooter.vue'
import SiteSidebar from './partials/TheSiteSidebar.vue'
import BaseModal from '../../components/base/modal/BaseModal'
import ChangePassword from '../auth/ChangePassword.vue'
import { RefreshIcon } from '@vue-hero-icons/solid'
import { mapActions, mapGetters } from 'vuex'
import { canVisit, landingPath } from '../../helpers/access'

export default {
  data: () => ({ accessReady: false }),
  components: {
    SiteHeader,
    SiteSidebar,
    SiteFooter,
    BaseModal,
    RefreshIcon,
    ChangePassword,
  },

  computed: {
    ...mapGetters(['isAppLoaded']),
    ...mapGetters('user', ['currentUser']),
    routeAllowed() { return this.accessReady && canVisit(this.currentUser, this.$route.path) },
    // Fila E1: con contrasenia inicial solo se muestra el cambio de clave.
    mustChangePassword() { return Boolean(this.currentUser && this.currentUser.must_change_password) },

    ...mapGetters('company', {
      selectedCompany: 'getSelectedCompany',
    }),

    isShow() {
      return true
    },
  },

  async created() {
    await this.bootstrap()
    this.setInitialCompany()
    // Una seleccion guardada por otra cuenta no habilita su nivel al ingresar.
    if (!this.currentUser.is_total_admin) {
      const levels = Object.keys(this.currentUser.permissions_by_level || {})
      if (!levels.includes(String(window.Ls.get('selectedSchoolLevel')))) {
        if (levels.length) window.Ls.set('selectedSchoolLevel', levels[0])
        else window.Ls.remove('selectedSchoolLevel')
      }
    }
    this.accessReady = true
    this.redirectToAllowedRoute()
  },

  watch: {
    '$route.path'() { if (this.accessReady) this.redirectToAllowedRoute() },
  },

  methods: {
    /** Resuelve inicio y enlaces viejos antes de montar sus componentes. */
    redirectToAllowedRoute() {
      if (this.mustChangePassword) return
      if (!this.routeAllowed || this.$route.path === '/admin') {
        this.$router.replace(landingPath(this.currentUser))
      }
    },
    ...mapActions(['bootstrap']),

    ...mapActions('company', ['setSelectedCompany']),

    setInitialCompany() {
      this.setSelectedCompany(this.selectedCompany)
    },
  },
}
</script>
