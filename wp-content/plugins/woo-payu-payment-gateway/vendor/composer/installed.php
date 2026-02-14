<?php return array(
    'root' => array(
        'name' => 'payu/payment_gateway',
        'pretty_version' => '2.9.0',
        'version' => '2.9.0.0',
        'reference' => null,
        'type' => 'woocommerce-plugin',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => false,
    ),
    'versions' => array(
        'openpayu/openpayu' => array(
            'pretty_version' => '2.3.6',
            'version' => '2.3.6.0',
            'reference' => '3d5a609147777e2ba64d72957492457cdd67d239',
            'type' => 'library',
            'install_path' => __DIR__ . '/../openpayu/openpayu',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'payu/payment_gateway' => array(
            'pretty_version' => '2.9.0',
            'version' => '2.9.0.0',
            'reference' => null,
            'type' => 'woocommerce-plugin',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
    ),
);
