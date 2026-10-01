<?php

namespace Crater\Services\Access;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Dataset ficticio permanente para probar aislamiento real en staging. */
class StagingAccessFixtures
{
    public function prepare(int $companyId): void
    {
        if (! app()->environment('staging') || DB::connection()->getDatabaseName() !== 'krater_staging') {
            throw new \RuntimeException('Las cuentas de prueba exigen staging y krater_staging.');
        }
        if (! DB::table('companies')->where('id', $companyId)->where('unique_hash', 'suiteena-staging-fixture')->exists()) {
            throw new \RuntimeException('Las pruebas solo se preparan en la institucion ficticia.');
        }
        $secret = config('staging.admin_password');
        if (! is_string($secret) || strlen($secret) < 24) {
            throw new \RuntimeException('Falta STAGING_ADMIN_PASSWORD para preparar las cuentas ficticias.');
        }

        DB::transaction(function () use ($companyId, $secret) {
            $primary = DB::table('school_levels')->where('company_id', $companyId)->where('code', 'primary')->value('id');
            $secondary = DB::table('school_levels')->where('company_id', $companyId)->where('code', 'secondary')->value('id');
            if (! $primary || ! $secondary) {
                throw new \RuntimeException('Faltan los niveles ficticios de staging.');
            }
            $preceptor = $this->account($companyId, 'preceptor.primary.staging', 'staff', $secret, 'preceptor', $primary);
            $this->account($companyId, 'finance.primary.staging', 'staff', $secret, 'finance_admin', $primary);
            $familyPrimary = $this->account($companyId, 'family.primary.staging', 'customer', $secret);
            $familySecondary = $this->account($companyId, 'family.secondary.staging', 'customer', $secret);
            foreach ([$primary => $familyPrimary, $secondary => $familySecondary] as $levelId => $familyId) {
                $yearId = $this->ensureRow('academic_years', ['company_id' => $companyId, 'school_level_id' => $levelId, 'year' => 2026],
                    ['name' => 'Ciclo ficticio 2026', 'starts_on' => '2026-03-01', 'ends_on' => '2026-12-31', 'status' => 'active']);
                $gradeId = $this->ensureRow('grade_levels', ['company_id' => $companyId, 'school_level_id' => $levelId, 'position' => 99],
                    ['name' => 'Curso ficticio R1']);
                $divisionId = $this->ensureRow('divisions', ['company_id' => $companyId, 'school_level_id' => $levelId,
                    'academic_year_id' => $yearId, 'grade_level_id' => $gradeId, 'name' => 'R1 prueba'], []);
                $studentId = $this->ensureRow('students', ['company_id' => $companyId, 'dni' => 'FICTICIO-R1-'.$levelId],
                    ['school_level_id' => $levelId, 'guardian_id' => $familyId, 'first_name' => 'Alumno ficticio',
                        'last_name' => 'Prueba R1', 'school_year' => 2026, 'status' => 'active']);
                $this->ensureRow('enrollments', ['company_id' => $companyId, 'school_level_id' => $levelId,
                    'academic_year_id' => $yearId, 'student_id' => $studentId], ['division_id' => $divisionId, 'enrolled_on' => '2026-03-01']);
                if ((int) $levelId === (int) $primary) {
                    $this->ensureRow('user_scopes', ['user_id' => $preceptor, 'company_id' => $companyId,
                        'scope_type' => 'division', 'scope_id' => $divisionId], []);
                }
            }
        });
    }

    private function account(int $companyId, string $name, string $legacyRole, string $secret, ?string $role = null, ?int $level = null): int
    {
        $email = $name.'@example.invalid';
        $existing = DB::table('users')->where('email', $email)->first();
        if ($existing) {
            if ((int) $existing->company_id !== $companyId) {
                throw new \RuntimeException('Una cuenta ficticia existe fuera de la institucion de prueba.');
            }

            return (int) $existing->id; // no restablecer claves, roles o bajas
        }
        $id = DB::table('users')->insertGetId(['company_id' => $companyId, 'name' => $name.' (prueba)', 'email' => $email,
            'role' => $legacyRole, 'currency_id' => DB::table('currencies')->where('code', 'ARS')->value('id'),
            'password' => Hash::make(hash_hmac('sha256', $email, $secret)), 'created_at' => now(), 'updated_at' => now()]);
        if ($role) {
            $roleId = DB::table('roles')->where('company_id', $companyId)->where('name', $role)->value('id');
            if (! $roleId) {
                throw new \RuntimeException('Falta el catalogo RBAC para la prueba.');
            }
            DB::table('role_user')->insert(['user_id' => $id, 'company_id' => $companyId, 'role_id' => $roleId,
                'school_level_id' => $level, 'granted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }

        return $id;
    }

    private function ensureRow(string $table, array $key, array $values): int
    {
        $existing = DB::table($table)->where($key)->value('id');

        return $existing ? (int) $existing : DB::table($table)->insertGetId(array_merge($key, $values,
            ['created_at' => now(), 'updated_at' => now()]));
    }
}
