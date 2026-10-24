<?php

namespace Crater\Support;

/**
 * Contexto de tenant del proceso actual.
 *
 * POR QUE EXISTE
 * --------------
 * El trait `BelongsToSchoolLevel` resolvia el tenant leyendo
 * `request()->header('school-level')` desde dentro de un scope global de
 * Eloquent. Eso funciona en un request HTTP y en ningun otro lado: en comandos
 * de consola, en jobs de cola y en los tests no hay request, asi que el scope
 * no filtraba nada. Falla abierto, que es la peor forma de fallar en un control
 * de acceso.
 *
 * Esta clase separa "quien pregunta" de "como se entero". El middleware lo fija
 * desde el header; un comando lo fija explicitamente; un job lo recibe
 * serializado. El scope global consulta siempre el mismo lugar.
 *
 * En consola el default es null, y los scopes tratan null como "no filtrar" a
 * proposito: un comando de mantenimiento necesita ver todo. Lo que NO puede
 * pasar es que una request HTTP sin header vea todo, y de eso se ocupa
 * ValidateTenant, que siempre fija la empresa.
 */
class TenantContext
{
    /** @var int|null */
    protected static $companyId;

    /** @var int|null */
    protected static $schoolLevelId;

    public static function set(?int $companyId, ?int $schoolLevelId = null): void
    {
        static::$companyId = $companyId;
        static::$schoolLevelId = $schoolLevelId;
    }

    public static function companyId(): ?int
    {
        return static::$companyId;
    }

    public static function schoolLevelId(): ?int
    {
        return static::$schoolLevelId;
    }

    public static function clear(): void
    {
        static::$companyId = null;
        static::$schoolLevelId = null;
    }

    /**
     * Corre un callback dentro de un contexto, y lo restaura al terminar.
     *
     * Es lo que usan los comandos de consola y los tests:
     *
     *     TenantContext::run($empresa->id, $nivel->id, function () {
     *         // aca los scopes globales filtran
     *     });
     */
    public static function run(?int $companyId, ?int $schoolLevelId, callable $callback)
    {
        $empresaPrevia = static::$companyId;
        $nivelPrevio = static::$schoolLevelId;

        static::set($companyId, $schoolLevelId);

        try {
            return $callback();
        } finally {
            static::set($empresaPrevia, $nivelPrevio);
        }
    }

    /**
     * Corre un callback sin ningun filtro de tenant.
     *
     * Para tareas de mantenimiento que legitimamente necesitan cruzar
     * instituciones. Que sea explicito y ruidoso es la intencion: si aparece en
     * un controlador, es un error.
     */
    public static function withoutScope(callable $callback)
    {
        return static::run(null, null, $callback);
    }
}
