<?php

// Excepciones acotadas y revisadas en Git. No existe --force para omitir el smoke.
// Cada entrada: ['migration' => basename sin .php, 'reason' => motivo,
// 'allowances' => ['invoices:1:2' => 3, 'invoices:1:all' => 3, 'invoices:all:all' => 3]].
// Solo vale si esa migracion existe, fue ejecutada y no estaba en la foto anterior.
return [];
