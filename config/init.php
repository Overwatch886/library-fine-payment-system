<?php
/*
 * Include this ONE file at the top of any PHP page that needs the backend:
 *
 *     require_once __DIR__ . '/config/init.php';
 *
 * It loads the settings and tells PHP where to find our classes, so
 * `new Loan()` automatically loads classes/Loan.php the first time it is used.
 */

require_once __DIR__ . '/config.php';

spl_autoload_register(function (string $className): void {
    $file = __DIR__ . '/../classes/' . $className . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});
