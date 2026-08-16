<?php

namespace Crater\Models;

use Crater\Traits\Auditable;
use Crater\Traits\BelongsToSchoolLevel;
use Illuminate\Database\Eloquent\Model;

/**
 * Curso: "1.er grado", "3.er anio".
 *
 * `promotes_to_id` es lo que hace posible la promocion automatica: apunta al
 * curso siguiente. En el ultimo anio queda nulo, y ahi el alumno egresa en vez
 * de promocionar.
 */
class GradeLevel extends Model
{
    use Auditable;
    use BelongsToSchoolLevel;

    protected $guarded = ['id'];

    protected $casts = [
        'position' => 'integer',
        'enabled' => 'boolean',
    ];

    public function promotesTo()
    {
        return $this->belongsTo(GradeLevel::class, 'promotes_to_id');
    }

    public function promotesFrom()
    {
        return $this->hasMany(GradeLevel::class, 'promotes_to_id');
    }

    public function divisions()
    {
        return $this->hasMany(Division::class);
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('position');
    }

    /** Ultimo curso del nivel: quien lo aprueba egresa. */
    public function isTerminal(): bool
    {
        return $this->promotes_to_id === null;
    }
}
