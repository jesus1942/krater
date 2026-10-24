/** Filtro propio del informe. Con un nivel activo permanece fijo en ese nivel. */
export default {
  data() { return { reportLevelId: window.Ls.get('selectedSchoolLevel') || '', reportLevels: [], institutionReports: !window.Ls.get('selectedSchoolLevel') } },
  async created() {
    if (this.institutionReports) {
      const response = await window.axios.get('/api/v1/school-levels')
      this.reportLevels = response.data.levels.filter(level => level.enabled)
    }
  },
}
