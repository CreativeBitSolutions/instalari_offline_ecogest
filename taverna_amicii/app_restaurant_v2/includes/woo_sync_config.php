<?php
return [
    'base_url' => 'https://restaurantgrandplazasb.ro/wp-json/restaurant-sync/v1',
    'api_key' => 'Hcpkszv9EQVSmkd65rmXNCpZ',
    'api_secret' => '/,S8aDsm<[Ku#tw&X={:DUt0s,|aUL+?&U+/{VsU$>W9A|o}',
    'timeout' => 20,
    'verify_ssl' => true,


    'order_json_base_url' => 'https://restaurantgrandplazasb.ro/wp-content/uploads/comenzi',
    'printer_queue_base_dir' => defined('RESTAURANT_OFFLINE_API_DIR')
        ? RESTAURANT_OFFLINE_API_DIR
        : dirname(dirname(dirname(__DIR__))) . '/api_offline_taverna_amicii',

    // Endpoint securizat WordPress pentru detalii complete comandă WooCommerce.
    'wp_order_details_endpoint' => 'https://restaurantgrandplazasb.ro/wp-json/grandplaza-pos/v1/order-details',
    'wp_order_details_api_key' => 'gp_pos_LrVUUiycQE4MV3s7pPuQeZyRxbDgGKISovolUIP39sYH20okaDnvfZlrZP1Jymo4',

    // Taxă transport aplicată automat la importul comenzilor Woo în POS.
    // Se aplică doar dacă NU este comandă cu ridicare de la restaurant.
    // Pluginul Woo trebuie să trimită transportul clar: shipping_total_excl_tax, shipping_tax și shipping_total_incl_tax.
    'delivery_fee' => [
        'enabled' => true,

        // Toleranță la comparația sumelor venite din WooCommerce, pentru diferențe de rotunjire.
        'amount_tolerance' => 0.01,

        'pickup_keywords' => [
            'ridicare',
            'ridică',
            'ridica',
            'ridicare restaurant',
            'ridicare de la restaurant',
            'pickup',
            'pick-up',
            'pick up',
            'local_pickup',
            'local pickup',
            'takeaway',
            'take away',
            'take-away',
        ],

        // Mapare configurabilă: valoarea transportului cu TVA inclus -> produs POS pentru transport.
        // Pentru o taxă nouă se adaugă o intrare nouă aici, nu se mai modifică logica din helpers.
        'mappings' => [
            [
                'label' => 'Livrare 10 lei',
                'amount' => 10.00,
                'cod_produs' => 436,
                'price' => 10.00,
            ],
            [
                'label' => 'Livrare 15 lei',
                'amount' => 15.00,
                'cod_produs' => 1680,
                'price' => 15.00,
            ],
        ],
    ],

    // Dacă HMAC este activ în plugin, pune true.
    'use_hmac' => false,
];
