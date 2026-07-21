<?php

use Magento\Framework\Component\ComponentRegistrar;

if (class_exists(ComponentRegistrar::class)) {
    ComponentRegistrar::register(
        ComponentRegistrar::MODULE,
        'Iwoca_Iwocapay',
        __DIR__
    );
}
