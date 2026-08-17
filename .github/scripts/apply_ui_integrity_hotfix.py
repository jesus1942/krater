from pathlib import Path

# routes
p = Path('routes/api.php')
s = p.read_text()
if 'StudentRelocationController' not in s:
    s = s.replace(
        'use Crater\\Http\\Controllers\\V1\\Student\\StudentsController;\n',
        'use Crater\\Http\\Controllers\\V1\\Student\\StudentsController;\nuse Crater\\Http\\Controllers\\V1\\Student\\StudentRelocationController;\n',
    )
if "students/{student}/relocate" not in s:
    s = s.replace(
        "        Route::get('/students/placement-options', [StudentsController::class, 'placementOptions']);\n        Route::apiResource('students', StudentsController::class);",
        "        Route::get('/students/placement-options', [StudentsController::class, 'placementOptions']);\n        Route::get('/students/{student}/relocation-options', [StudentRelocationController::class, 'options']);\n        Route::put('/students/{student}/relocate', [StudentRelocationController::class, 'relocate']);\n        Route::apiResource('students', StudentsController::class);",
    )
p.write_text(s)

p = Path('resources/assets/js/views/students/Index.vue')
s = p.read_text()

old_filter = '''        <select v-model="filters.level" class="h-10 px-3 bg-white border border-gray-300 rounded" @change="fetchStudents">
          <option value="">Todos los niveles</option>
          <option v-for="level in levels" :key="level" :value="level">{{ level }}</option>
        </select>'''
new_filter = '''        <select v-if="canViewAllLevels" v-model="filters.all_levels" class="h-10 px-3 bg-white border border-gray-300 rounded" @change="fetchStudents">
          <option :value="false">Nivel activo</option>
          <option :value="true">Todos los niveles · administración</option>
        </select>
        <div v-else class="flex items-center h-10 px-3 text-sm text-gray-600 bg-gray-50 border border-gray-200 rounded">{{ activeLevelName }}</div>'''
s = s.replace(old_filter, new_filter)

old_actions = '''        <div class="flex justify-end gap-3 mt-4">
          <button class="text-sm font-medium text-primary-500" @click="openEdit(student)">Editar</button>
          <button class="text-sm font-medium text-red-500" @click="remove(student)">Eliminar</button>
        </div>'''
new_actions = '''        <div class="flex justify-end gap-3 mt-4">
          <button v-if="!filters.all_levels" class="text-sm font-medium text-primary-500" @click="openEdit(student)">Editar</button>
          <button v-if="canViewAllLevels" class="text-sm font-medium text-indigo-600" @click="openRelocate(student)">Reubicar</button>
        </div>'''
s = s.replace(old_actions, new_actions)

modal = '''

    <div v-if="showRelocation" class="fixed inset-0 z-50 flex items-start justify-center p-4 overflow-y-auto bg-black bg-opacity-50">
      <form class="w-full max-w-2xl p-6 my-8 bg-white rounded shadow-xl" @submit.prevent="saveRelocation">
        <div class="flex items-center justify-between mb-5">
          <div>
            <h2 class="text-xl font-semibold">Reubicar alumno</h2>
            <p class="mt-1 text-sm text-gray-500">{{ relocationStudent ? relocationStudent.full_name : '' }}</p>
          </div>
          <button type="button" class="text-2xl text-gray-400" @click="closeRelocation">×</button>
        </div>
        <p class="p-3 mb-4 text-sm text-blue-800 bg-blue-50 rounded">
          Corrige nivel, ciclo, curso y división sin borrar el legajo, la familia ni el historial.
        </p>
        <div class="grid gap-4 md:grid-cols-2">
          <label class="text-sm">Nivel *
            <select v-model="relocationForm.school_level_id" required class="w-full h-10 px-3 mt-1 bg-white border border-gray-300 rounded" @change="onRelocationLevelChange">
              <option value="">Seleccionar nivel</option>
              <option v-for="level in relocationOptions.levels" :key="level.id" :value="level.id">{{ level.name }}</option>
            </select>
          </label>
          <label class="text-sm">Ciclo lectivo *
            <select v-model="relocationForm.academic_year_id" required class="w-full h-10 px-3 mt-1 bg-white border border-gray-300 rounded" @change="relocationForm.division_id = ''">
              <option value="">Seleccionar ciclo</option>
              <option v-for="year in relocationYears" :key="year.id" :value="year.id">{{ year.name || year.year }}</option>
            </select>
          </label>
          <label class="text-sm">Curso/Año *
            <select v-model="relocationForm.grade_level_id" required class="w-full h-10 px-3 mt-1 bg-white border border-gray-300 rounded" @change="relocationForm.division_id = ''">
              <option value="">Seleccionar curso</option>
              <option v-for="grade in relocationGrades" :key="grade.id" :value="grade.id">{{ grade.name }}</option>
            </select>
          </label>
          <label class="text-sm">División *
            <select v-model="relocationForm.division_id" required class="w-full h-10 px-3 mt-1 bg-white border border-gray-300 rounded">
              <option value="">Seleccionar división</option>
              <option v-for="division in relocationDivisions" :key="division.id" :value="division.id">{{ division.name }}</option>
            </select>
          </label>
        </div>
        <p v-if="relocationError" class="mt-4 text-sm text-red-600">{{ relocationError }}</p>
        <div class="flex justify-end gap-3 mt-6">
          <sw-button type="button" variant="primary-outline" @click="closeRelocation">Cancelar</sw-button>
          <sw-button :loading="relocating" :disabled="relocating" variant="primary">Guardar ubicación</sw-button>
        </div>
      </form>
    </div>'''
if 'v-if="showRelocation"' not in s:
    s = s.replace('    </div>\n  </base-page>\n</template>', '    </div>' + modal + '\n  </base-page>\n</template>')

s = s.replace(
    "      filters: { search: '', level: '', status: '', school_year: new Date().getFullYear() },\n      levels: ['Primario', 'Secundario', 'Terciario'],",
    "      filters: { search: '', all_levels: !window.Ls.get('selectedSchoolLevel'), status: '', school_year: new Date().getFullYear() },\n      canViewAllLevels: false,"
)
s = s.replace(
    "      saving: false,\n      error: '',",
    "      saving: false,\n      error: '',\n      showRelocation: false,\n      relocationStudent: null,\n      relocationOptions: { levels: [], academic_years: [], grade_levels: [], divisions: [] },\n      relocationForm: { school_level_id: '', academic_year_id: '', grade_level_id: '', division_id: '' },\n      relocationError: '',\n      relocating: false,"
)
s = s.replace(
'''    activeLevelName() {
      const selectedId = window.Ls.get('selectedSchoolLevel')
      const level = this.schoolLevels.find((item) => String(item.id) === String(selectedId))
      return level ? level.name : 'Nivel activo'
    },''',
'''    activeLevelName() {
      const selectedId = window.Ls.get('selectedSchoolLevel')
      const level = this.schoolLevels.find((item) => String(item.id) === String(selectedId))
      return level ? level.name : 'Sin nivel seleccionado'
    },
    relocationYears() {
      return this.relocationOptions.academic_years.filter((item) => String(item.school_level_id) === String(this.relocationForm.school_level_id))
    },
    relocationGrades() {
      return this.relocationOptions.grade_levels.filter((item) => String(item.school_level_id) === String(this.relocationForm.school_level_id))
    },
    relocationDivisions() {
      return this.relocationOptions.divisions.filter((item) =>
        String(item.school_level_id) === String(this.relocationForm.school_level_id) &&
        String(item.academic_year_id) === String(this.relocationForm.academic_year_id) &&
        String(item.grade_level_id) === String(this.relocationForm.grade_level_id)
      )
    },'''
)
s = s.replace(
'''        const response = await window.axios.get('/api/v1/students', { params: this.filters })
        this.students = response.data.students.data || response.data.students
        this.summary = response.data.summary''',
'''        const response = await window.axios.get('/api/v1/students', { params: { ...this.filters, all_levels: this.filters.all_levels ? 1 : 0 } })
        this.students = response.data.students.data || response.data.students
        this.summary = response.data.summary
        this.canViewAllLevels = Boolean(response.data.can_view_all_levels)
        if (!this.canViewAllLevels) this.filters.all_levels = false'''
)

marker = '''    onAcademicYearChange() {
      this.form.division_id = ''
    },'''
methods = '''    async openRelocate(student) {
      this.relocationStudent = student
      this.relocationError = ''
      this.relocationForm = { school_level_id: student.school_level_id || '', academic_year_id: '', grade_level_id: '', division_id: '' }
      const response = await window.axios.get(`/api/v1/students/${student.id}/relocation-options`)
      this.relocationOptions = response.data
      this.showRelocation = true
    },
    closeRelocation() {
      this.showRelocation = false
      this.relocationStudent = null
    },
    onRelocationLevelChange() {
      this.relocationForm.academic_year_id = ''
      this.relocationForm.grade_level_id = ''
      this.relocationForm.division_id = ''
    },
    async saveRelocation() {
      this.relocating = true
      this.relocationError = ''
      try {
        await window.axios.put(`/api/v1/students/${this.relocationStudent.id}/relocate`, this.relocationForm)
        this.closeRelocation()
        await this.fetchStudents()
      } catch (error) {
        const response = error.response && error.response.data
        if (response && response.errors) {
          const first = Object.values(response.errors)[0]
          this.relocationError = Array.isArray(first) ? first[0] : first
        } else {
          this.relocationError = (response && response.message) || 'No se pudo reubicar el alumno.'
        }
      } finally {
        this.relocating = false
      }
    },
'''+marker
s = s.replace(marker, methods)

# remove destructive UI method button no longer references remove; method can stay harmlessly but remove it too
start = s.find('    async remove(student) {')
if start >= 0:
    end = s.find('    familySummary(student) {', start)
    if end >= 0:
        s = s[:start] + s[end:]

p.write_text(s)
