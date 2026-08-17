<?php

$root = dirname(__DIR__, 2);

it('keeps school billing usable on narrow mobile screens', function () use ($root) {
    $invoice = file_get_contents($root.'/resources/assets/js/views/invoices/Create.vue');
    $payment = file_get_contents($root.'/resources/assets/js/views/payments/Create.vue');
    $settings = file_get_contents($root.'/resources/assets/js/views/settings/SettingsIndex.vue');

    expect($invoice)->toContain('overflow-x-auto');
    expect($invoice)->toContain('style="min-width: 720px"');
    expect($invoice)->toContain('lg:grid');
    expect($payment)->toContain('grid-col-1 md:grid-cols-2');
    expect($payment)->toContain('sm:hidden');
    expect($settings)->toContain('xl:hidden');
});
