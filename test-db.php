<?php
echo "Testing database connection...\n";
try {
    $pdo = DB::connection()->getPdo();
    echo "Database connection successful!\n";
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
