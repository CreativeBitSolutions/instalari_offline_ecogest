<?php
// Copiaza acest fisier ca includes/woo_sync_config.local.php.
// Fisierul .local.php este ignorat de Git si nu trebuie publicat.
return [
    'api_key' => 'CHEIA_GENERATA_IN_PLUGINUL_WORDPRESS',
    'api_secret' => 'SECRETUL_GENERAT_IN_PLUGINUL_WORDPRESS',
    // Activeaza doar daca HMAC este activat si in pluginul WordPress.
    'use_hmac' => false,
];
