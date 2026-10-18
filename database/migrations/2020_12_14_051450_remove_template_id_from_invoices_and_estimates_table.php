<?php

// Fila 5: sin scopes globales, porque deleted_at todavia no existe en este
// punto de una instalacion nueva. En bases existentes esta migracion ya corrio.

use Crater\Models\Estimate;
use Crater\Models\Invoice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveTemplateIdFromInvoicesAndEstimatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasColumn('invoices', 'invoice_template_id')) {
            $invoices = Invoice::withoutGlobalScopes()->get();

            $invoices->map(function ($invoice) {
                $invoice->template_name = 'invoice'.$invoice->invoice_template_id;
                $invoice->save();
            });

            Schema::table('invoices', function (Blueprint $table) {
                if (config('database.default') !== 'sqlite') {
                    $table->dropForeign(['invoice_template_id']);
                }
                $table->dropColumn('invoice_template_id');
            });
        }

        if (Schema::hasColumn('estimates', 'estimate_template_id')) {
            $estimates = Estimate::withoutGlobalScopes()->get();

            $estimates->map(function ($estimate) {
                $estimate->template_name = 'estimate'.$estimate->estimate_template_id;
                $estimate->save();
            });

            Schema::table('estimates', function (Blueprint $table) {
                if (config('database.default') !== 'sqlite') {
                    $table->dropForeign(['estimate_template_id']);
                }
                $table->dropColumn('estimate_template_id');
            });
        }

        Schema::dropIfExists('invoice_templates');
        Schema::dropIfExists('estimate_templates');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
