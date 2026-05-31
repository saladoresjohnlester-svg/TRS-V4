<?php
// Simple test to verify PHP syntax and force cache refresh
echo "PHP syntax check: ";
if (php_syntax_check('c:\xampp\htdocs\trs\adviser\homepage\titleproposal\route1.php')) {
    echo "✅ PASSED\n";
} else {
    echo "❌ FAILED\n";
}

// Test database connection
include '../../../connection.php';
if ($conn) {
    echo "Database connection: ✅ OK\n";
} else {
    echo "Database connection: ❌ FAILED\n";
}

// Test vendor autoloader
if (file_exists('../../../vendor/autoload.php')) {
    echo "Vendor autoloader: ✅ FOUND\n";
} else {
    echo "Vendor autoloader: ❌ NOT FOUND\n";
}

echo "Current time: " . date('Y-m-d H:i:s') . "\n";
?>
