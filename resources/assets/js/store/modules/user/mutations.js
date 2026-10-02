import * as types from './mutation-types'

export default {
  [types.RESET_CURRENT_USER](state, user) {
    state.currentUser = null
  },

  [types.BOOTSTRAP_CURRENT_USER](state, user) {
    state.currentUser = { ...state.currentUser, ...user }
  },

  [types.UPDATE_CURRENT_USER](state, user) {
    state.currentUser = { ...state.currentUser, ...user }
  },

  [types.UPDATE_USER_AVATAR](state, data) {
    if (state.currentUser) {
      state.currentUser.avatar = data.avatar
    }
  },

  [types.SET_DEFAULT_LANGUAGE](state, data) {
    // Una preferencia ausente, vacia o no soportada nunca deja el locale indefinido.
    window.i18n.locale = window.i18n.availableLocales.includes(data) ? data : 'es'
  },
}
