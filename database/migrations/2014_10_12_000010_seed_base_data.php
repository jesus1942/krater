<?php

use Crater\Models\CompanySetting;
use Crater\Models\Currency;
use Crater\Models\PaymentMethod;
use Crater\Models\Unit;
use Crater\Models\User;
use Illuminate\Database\Migrations\Migration;

class SeedBaseData extends Migration
{
    public function up()
    {
        if (Currency::count() === 0) {
            $currencies = [
                ['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
                ['name' => 'British Pound', 'code' => 'GBP', 'symbol' => '£', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
                ['name' => 'Euro', 'code' => 'EUR', 'symbol' => '€', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => true],
                ['name' => 'Argentine Peso', 'code' => 'ARS', 'symbol' => '$', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','],
                ['name' => 'Brazilian Real', 'code' => 'BRL', 'symbol' => 'R$', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','],
                ['name' => 'Canadian Dollar', 'code' => 'CAD', 'symbol' => 'C$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
                ['name' => 'Australian Dollar', 'code' => 'AUD', 'symbol' => '$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
                ['name' => 'Mexican Peso', 'code' => 'MXN', 'symbol' => '$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
                ['name' => 'Colombian Peso', 'code' => 'COP', 'symbol' => '$', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','],
                ['name' => 'Chilean Peso', 'code' => 'CLP', 'symbol' => '$', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','],
                ['name' => 'Uruguayan Peso', 'code' => 'UYU', 'symbol' => '$', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','],
                ['name' => 'Indian Rupee', 'code' => 'INR', 'symbol' => '₹', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
                ['name' => 'Japanese Yen', 'code' => 'JPY', 'symbol' => '¥', 'precision' => '0', 'thousand_separator' => ',', 'decimal_separator' => '.'],
                ['name' => 'Chinese Renminbi', 'code' => 'CNY', 'symbol' => 'RMB ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ];
            foreach ($currencies as $c) {
                Currency::create($c);
            }
        }

        if (PaymentMethod::count() === 0) {
            PaymentMethod::create(['name' => 'Efectivo', 'company_id' => 1]);
            PaymentMethod::create(['name' => 'Transferencia', 'company_id' => 1]);
            PaymentMethod::create(['name' => 'Tarjeta de credito', 'company_id' => 1]);
            PaymentMethod::create(['name' => 'Cheque', 'company_id' => 1]);
        }

        if (Unit::count() === 0) {
            foreach (['unidad', 'hora', 'kg', 'mes', 'dia'] as $u) {
                Unit::create(['name' => $u, 'company_id' => 1]);
            }
        }

        if (CompanySetting::count() === 0) {
            $user = User::where('role', 'super admin')->first();
            if ($user) {
                $billingFmt = '<h3>{BILLING_ADDRESS_NAME}</h3><p>{BILLING_ADDRESS_STREET_1}</p><p>{BILLING_CITY}  {BILLING_STATE}</p><p>{BILLING_COUNTRY}  {BILLING_ZIP_CODE}</p>';
                $shippingFmt = '<h3>{SHIPPING_ADDRESS_NAME}</h3><p>{SHIPPING_ADDRESS_STREET_1}</p><p>{SHIPPING_CITY}  {SHIPPING_STATE}</p><p>{SHIPPING_COUNTRY}  {SHIPPING_ZIP_CODE}</p>';
                $companyFmt = '<h3><strong>{COMPANY_NAME}</strong></h3><p>{COMPANY_ADDRESS_STREET_1}</p><p>{COMPANY_CITY} {COMPANY_STATE}</p><p>{COMPANY_COUNTRY}  {COMPANY_ZIP_CODE}</p>';
                $paymentFmt = '<h3>{BILLING_ADDRESS_NAME}</h3><p>{BILLING_ADDRESS_STREET_1}</p><p>{BILLING_CITY} {BILLING_STATE} {BILLING_ZIP_CODE}</p><p>{BILLING_COUNTRY}</p>';

                CompanySetting::setSettings([
                    'currency'                              => 1,
                    'time_zone'                             => 'America/Argentina/Buenos_Aires',
                    'language'                              => 'es',
                    'fiscal_year'                           => '1-12',
                    'carbon_date_format'                    => 'd/m/Y',
                    'moment_date_format'                    => 'DD/MM/YYYY',
                    'notification_email'                    => 'noreply@escuelanuevaaustral.com',
                    'notify_invoice_viewed'                 => 'NO',
                    'notify_estimate_viewed'                => 'NO',
                    'tax_per_item'                          => 'NO',
                    'discount_per_item'                     => 'NO',
                    'invoice_prefix'                        => 'FAC',
                    'invoice_auto_generate'                 => 'YES',
                    'invoice_number_length'                 => 6,
                    'invoice_email_attachment'              => 'NO',
                    'estimate_prefix'                       => 'PRE',
                    'estimate_auto_generate'                => 'YES',
                    'estimate_number_length'                => 6,
                    'estimate_email_attachment'             => 'NO',
                    'payment_prefix'                        => 'PAG',
                    'payment_auto_generate'                 => 'YES',
                    'payment_number_length'                 => 6,
                    'payment_email_attachment'              => 'NO',
                    'save_pdf_to_disk'                      => 'NO',
                    'invoice_mail_body'                     => 'Recibiste una nueva factura de <b>{COMPANY_NAME}</b>.',
                    'estimate_mail_body'                    => 'Recibiste un nuevo presupuesto de <b>{COMPANY_NAME}</b>.',
                    'payment_mail_body'                     => 'Gracias por tu pago. Descarga el recibo usando el boton de abajo.',
                    'invoice_company_address_format'        => $companyFmt,
                    'invoice_shipping_address_format'       => $shippingFmt,
                    'invoice_billing_address_format'        => $billingFmt,
                    'estimate_company_address_format'       => $companyFmt,
                    'estimate_shipping_address_format'      => $shippingFmt,
                    'estimate_billing_address_format'       => $billingFmt,
                    'payment_company_address_format'        => $companyFmt,
                    'payment_from_customer_address_format'  => $paymentFmt,
                ], $user->company_id);
            }
        }
    }

    public function down()
    {
        // no revertir datos de seed
    }
}
