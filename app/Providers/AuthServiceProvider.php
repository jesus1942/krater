<?php

namespace Crater\Providers;

use Crater\Models\AcademicYear;
use Crater\Models\CourseSection;
use Crater\Models\Division;
use Crater\Models\StudyPlan;
use Crater\Models\Subject;
use Crater\Policies\AcademicPolicy;
use Crater\Services\Access\AccessManager;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Mapeo de policies.
     *
     * Antes esto era el stub por defecto de Laravel: mapeaba `Crater\Model` a
     * `Crater\Policies\ModelPolicy`, dos clases que no existen. Como
     * registerPolicies() resuelve de forma perezosa no rompia en runtime, pero
     * tampoco autorizaba nada.
     *
     * Todos los modelos de estructura comparten AcademicPolicy: las reglas son
     * las mismas y repetirlas por modelo invita a que se desincronicen.
     *
     * @var array
     */
    protected $policies = [
        AcademicYear::class => AcademicPolicy::class,
        Division::class => AcademicPolicy::class,
        CourseSection::class => AcademicPolicy::class,
        Subject::class => AcademicPolicy::class,
        StudyPlan::class => AcademicPolicy::class,
    ];

    public function boot()
    {
        $this->registerPolicies();

        /*
         * Gate generico para preguntar por un permiso suelto, sin objeto:
         *
         *     Gate::allows('permission', Permission::AUDIT_VIEW)
         *
         * Delega en el mismo resolutor que las policies: no hay dos caminos de
         * decision que puedan divergir.
         */
        Gate::define('permission', function ($user, string $permission, ?int $schoolLevelId = null) {
            return app(AccessManager::class)->allows($user, $permission, $schoolLevelId);
        });
    }
}
