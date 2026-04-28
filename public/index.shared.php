<?php

/**
 * SHARED HOSTING İÇİN index.php
 *
 * Kullanım:
 * 1. Laravel dosyalarını (app, bootstrap, config, database, resources,
 *    routes, storage, vendor, artisan, composer.json) sunucuya
 *    public_html/ DIŞINDA bir klasöre yükle → örn: /home/USER/kankio/
 *
 * 2. public/ klasörünün İÇİNDEKİ tüm dosyaları public_html/'e kopyala
 *    (.htaccess DAHİL!)
 *
 * 3. Bu dosyayı public_html/index.php olarak yükle
 *    (aşağıdaki yolu sunucuna göre düzelt)
 */

define('LARAVEL_START', microtime(true));

// Bakım modu
if (file_exists($maintenance = __DIR__.'/../kankio/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Composer autoloader — kankio/ klasörüne işaret eder
require __DIR__.'/../kankio/vendor/autoload.php';

// Laravel bootstrap
/** @var \Illuminate\Foundation\Application $app */
$app = require_once __DIR__.'/../kankio/bootstrap/app.php';

$app->handleRequest(\Illuminate\Http\Request::capture());
