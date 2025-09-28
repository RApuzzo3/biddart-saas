<?php
// Simple Laravel bootstrap test
try {
    // Test 1: Basic PHP
    echo "✓ PHP execution working\n";

    // Test 2: Laravel autoloader
    require __DIR__.'/../vendor/autoload.php';
    echo "✓ Composer autoloader loaded\n";

    // Test 3: Laravel application
    $app = require_once __DIR__.'/../bootstrap/app.php';
    echo "✓ Laravel application created\n";

    // Test 4: HTTP Kernel
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    echo "✓ HTTP Kernel created\n";

    // Test 5: Database connection
    $app['db']->connection()->getPdo();
    echo "✓ Database connection working\n";

    echo "\n🎉 All Laravel components working!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
