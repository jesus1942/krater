<?php

namespace Crater\Traits;

use Crater\Models\SchoolLevel;
use Crater\Support\TenantContext;

/**
 * Alcance por nivel institucional.
 *
 * QUE CAMBIO Y POR QUE
 * --------------------
 * La version anterior resolvia el nivel leyendo
 * `request()->header('school-level')` desde dentro del scope global. Dos
 * problemas serios:
 *
 * 1. **Fallaba abierto fuera de HTTP.** En comandos de consola, jobs de cola y
 *    tests no hay request, `request()->header()` devuelve null, y el `if` no
 *    aplicaba ningun filtro. El aislamiento por nivel no existia en ninguno de
 *    esos contextos.
 *
 * 2. **Consultaba la base en cada query.** El `SchoolLevel::exists()` corria
 *    dentro del scope global, asi que cada consulta a un modelo con este trait
 *    disparaba una consulta extra de validacion.
 *
 * Ahora el contexto lo fija `ValidateTenant` una vez por request, ya validado
 * contra el usuario autenticado, y el scope solo lo lee. Fuera de HTTP se usa
 * `TenantContext::run()` de forma explicita.
 *
 * Sigue sin filtrar cuando no hay contexto fijado, y eso es deliberado: los
 * comandos de mantenimiento necesitan ver todo. Lo que garantiza que una
 * request HTTP nunca quede sin contexto es que `ValidateTenant` siempre fija al
 * menos la empresa.
 */
trait BelongsToSchoolLevel
{
    protected static function bootBelongsToSchoolLevel()
    {
        static::creating(function ($model) {
            $companyId = TenantContext::companyId();
            $levelId = TenantContext::schoolLevelId();

            if (! $model->company_id && $companyId) {
                $model->company_id = $companyId;
            }

            if (! $model->school_level_id && $levelId) {
                $model->school_level_id = $levelId;
            }
        });

        // El nivel es el tenant interno, pero la empresa sigue siendo el
        // tenant externo. Filtrar solo por nivel deja una ventana en el route
        // model binding cuando un administrador global consulta por ID.
        static::addGlobalScope('company', function ($query) {
            $companyId = TenantContext::companyId();

            if ($companyId) {
                $query->where(
                    $query->getModel()->getTable().'.company_id',
                    $companyId
                );
            }
        });

        static::addGlobalScope('school_level', function ($query) {
            $levelId = TenantContext::schoolLevelId();

            if ($levelId) {
                $query->where(
                    $query->getModel()->getTable().'.school_level_id',
                    $levelId
                );
            }
        });
    }

    public function schoolLevel()
    {
        return $this->belongsTo(SchoolLevel::class);
    }

    /**
     * Consulta sin el filtro de nivel. Explicito y facil de encontrar con grep:
     * si aparece en un controlador, casi siempre es un error.
     */
    public static function acrossLevels()
    {
        return static::withoutGlobalScope('school_level');
    }
}
