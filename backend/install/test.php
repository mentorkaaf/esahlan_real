<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>PHP Test - OK</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Session test: ";
session_start();
$_SESSION['test'] = 1;
echo "OK<br>";

echo "<hr><h3>Testing installer syntax:</h3>";
// Try to include the installer to catch syntax errors
$file = __DIR__ . '/index.php';
if (file_exists($file)) {
    $code = file_get_contents($file);
    echo "index.php size: " . strlen($code) . " bytes<br>";
    echo "File exists: YES<br>";
} else {
    echo "index.php NOT FOUND<br>";
}

echo "<hr><h3>Root path check:</h3>";
$root = dirname(__DIR__);
echo "Root: $root <br>";
echo "vendor exists: " . (is_dir($root.'/vendor') ? 'YES' : 'NO') . "<br>";
echo "bootstrap/app.php exists: " . (file_exists($root.'/bootstrap/app.php') ? 'YES' : 'NO') . "<br>";
echo ".env exists: " . (file_exists($root.'/.env') ? 'YES' : 'NO') . "<br>";
echo "storage writable: " . (is_writable($root.'/storage') ? 'YES' : 'NO') . "<br>";
