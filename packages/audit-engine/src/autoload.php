<?php

/**
 * Zero-dependency PSR-4 autoloader for the GeoPro\AuditEngine namespace.
 *
 * Lets the plain-PHP audit API load the package without Composer.
 */

spl_autoload_register(static function (string $class): void {
    $prefix = 'GeoPro\\AuditEngine\\';
    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $path = __DIR__.'/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
    if (is_file($path)) {
        require $path;
    }
});
