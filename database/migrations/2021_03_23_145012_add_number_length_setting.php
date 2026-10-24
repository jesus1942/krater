<?php

// Fila 5: sin scopes globales, porque deleted_at todavia no existe en este
// punto de una instalacion nueva. En bases existentes esta migracion ya corrio.

use Crater\Models\CompanySetting;
use Crater\Models\User;
use Illuminate\Database\Migrations\Migration;

class AddNumberLengthSetting extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $user = User::withoutGlobalScopes()->where('role', 'super admin')->first();

        if ($user) {
            $invoice_number_length = CompanySetting::getSetting('invoice_number_length', $user->company_id);
            if (empty($invoice_number_length)) {
                CompanySetting::setSettings(['invoice_number_length' => '6'], $user->company_id);
            }

            $estimate_number_length = CompanySetting::getSetting('estimate_number_length', $user->company_id);
            if (empty($estimate_number_length)) {
                CompanySetting::setSettings(['estimate_number_length' => '6'], $user->company_id);
            }

            $payment_number_length = CompanySetting::getSetting('payment_number_length', $user->company_id);
            if (empty($payment_number_length)) {
                CompanySetting::setSettings(['payment_number_length' => '6'], $user->company_id);
            }
        }
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
