<?php

require_once __DIR__ . '/../../zf2/library/Zend/Loader/AutoloaderFactory.php';
Zend\Loader\AutoloaderFactory::factory(array(
    'Zend\Loader\StandardAutoloader' => array(
        'autoregister_zf' => true,
        'namespaces' => array(
            'ZendTest' => __DIR__ . '/ZendTest',
        ),
    ),
));

$standard = new Zend\Loader\StandardAutoloader(array(
    'namespaces' => array(
        'Zend' => __DIR__ . '/../Zend',
    ),
));
$standard->register();