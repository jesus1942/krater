<?php

namespace Crater\Services\Attendance;

use Crater\Models\CompanySetting;
use Crater\Models\SchoolLevel;

/**
 * Reglas de asistencia por nivel, en company_settings (clave-valor, sin
 * migraciones). Predeterminados: Primario y Secundario toman lista por dia y
 * division con tope de 25 inasistencias en el ciclo; Terciario toma por
 * materia y exige 75 % de asistencia en cada una.
 */
class AttendanceRules
{
    const DAILY = 'daily';
    const SUBJECT = 'subject';

    public function forLevel(SchoolLevel $level): array
    {
        $prefix = 'attendance.level.'.$level->id.'.';
        $stored = CompanySetting::getSettings([$prefix.'mode', $prefix.'max_absences', $prefix.'min_percentage'], $level->company_id);
        $mode = $stored[$prefix.'mode'] ?? ($level->code === 'tertiary' ? self::SUBJECT : self::DAILY);

        return [
            'mode' => in_array($mode, [self::DAILY, self::SUBJECT], true) ? $mode : self::DAILY,
            'max_absences' => (float) ($stored[$prefix.'max_absences'] ?? 25),
            'min_percentage' => (float) ($stored[$prefix.'min_percentage'] ?? 75),
            // Una carga de dias anteriores mas vieja que esto exige permiso de correccion.
            'edit_window_days' => 2,
        ];
    }

    public function save(SchoolLevel $level, array $values): array
    {
        $prefix = 'attendance.level.'.$level->id.'.';
        CompanySetting::setSettings(collect($values)->only(['mode', 'max_absences', 'min_percentage'])
            ->mapWithKeys(fn ($value, $key) => [$prefix.$key => $value])->all(), $level->company_id);

        return $this->forLevel($level);
    }
}
