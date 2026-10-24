<?php

$root = dirname(__DIR__, 2);

it('keeps school billing usable on narrow mobile screens', function () use ($root) {
    $invoice = file_get_contents($root.'/resources/assets/js/views/invoices/Create.vue');
    $payment = file_get_contents($root.'/resources/assets/js/views/payments/Create.vue');
    $item = file_get_contents($root.'/resources/assets/js/views/invoices/Item.vue');
    $settings = file_get_contents($root.'/resources/assets/js/views/settings/SettingsIndex.vue');

    expect($invoice)->not->toContain('style="min-width: 720px"');
    expect($invoice)->not->toContain('min-width: 384px');
    expect($invoice)->toContain('md:table');
    expect($invoice)->toContain('colgroup class="hidden md:table-column-group"');
    expect($item)->toContain('md:hidden">Concepto o arancel');
    expect($item)->toContain('md:hidden">Subtotal');
    expect($invoice)->toContain('student_id: { required }');
    expect($invoice)->toContain('family_member_id: { required }');
    expect($invoice)->not->toContain('$v.selectedCustomer');

    $submitStart = strpos($invoice, 'async submitForm()');
    $submitEnd = strpos($invoice, 'submitCreate(data)', $submitStart);
    $submit = substr($invoice, $submitStart, $submitEnd - $submitStart);

    expect($submit)->toContain('student_id: this.newInvoice.student_id');
    expect($submit)->toContain('family_member_id: this.newInvoice.family_member_id');
    expect($submit)->not->toContain('student_id: null');
    expect($submit)->not->toContain('family_member_id: null');

    expect($payment)->toContain('grid-col-1 md:grid-cols-2');
    expect($payment)->toContain('sm:hidden');
    expect($settings)->toContain('xl:hidden');
});
