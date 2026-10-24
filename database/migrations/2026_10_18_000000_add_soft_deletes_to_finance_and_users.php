<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fila 5: baja logica. Solo agrega columnas; no toca filas existentes.
 */
class AddSoftDeletesToFinanceAndUsers extends Migration
{
    const TABLES = ['invoices', 'estimates', 'payments', 'expenses', 'items', 'users'];

    public function up()
    {
        foreach (self::TABLES as $name) {
            if (Schema::hasTable($name) && ! Schema::hasColumn($name, 'deleted_at')) {
                Schema::table($name, function (Blueprint $table) {
                    $table->softDeletes();
                    $table->index('deleted_at');
                });
            }
        }
    }

    public function down()
    {
        // Quitar la columna devolveria a la vista registros dados de baja.
        throw new RuntimeException('La baja logica no se revierte por migracion.');
    }
}
