<?php

namespace Crater\Services\Data;

use Carbon\CarbonImmutable;

class BackupRetention
{
    /** Conserva el ultimo backup de cada uno de los 7 dias, 4 semanas y 6 meses. */
    public function keep(array $records): array
    {
        usort($records, function ($a, $b) { return strcmp($b['created_at'], $a['created_at']); });
        $kept = [];
        foreach (['Y-m-d' => 7, 'o-W' => 4, 'Y-m' => 6] as $format => $maximum) {
            $periods = [];
            foreach ($records as $record) {
                $period = CarbonImmutable::parse($record['created_at'])->utc()->format($format);
                if (! isset($periods[$period]) && count($periods) < $maximum) {
                    $periods[$period] = true;
                    $kept[$record['key']] = true;
                }
            }
        }

        return array_keys($kept);
    }
}
