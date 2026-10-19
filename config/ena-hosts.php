<?php

/*
 * Dominios publicos. Los hosts de la landing (dominio principal y www) solo
 * sirven la portada; cualquier otra ruta web se redirige a la Suite, que vive
 * en APP_URL (app.<dominio>). Vacio: un solo host para todo, como hasta ahora.
 */
return [
    'landing_hosts' => array_values(array_filter(array_map(
        fn ($host) => strtolower(trim($host)),
        explode(',', (string) env('LANDING_HOSTS', ''))
    ))),
];
