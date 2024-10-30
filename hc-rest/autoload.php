<?php

function hiecor_my_autoloader($class) {
    $baseClassDir = __DIR__ . DIRECTORY_SEPARATOR;
    if (file_exists($baseClassDir . str_replace('\\', '/', $class) . '.php')) {
        include $baseClassDir . str_replace('\\', '/', $class) . '.php';
    }
}

spl_autoload_register('hiecor_my_autoloader');
?>