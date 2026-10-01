<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/autoload.php';
require_once __DIR__ . '/MemoryTest.php';

use EidCloud\Memory\Tests\MemoryTest;

echo "====================================================\n";
echo "eidcloud-memory Zero-Dependency Test Runner\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "====================================================\n";

$suite = new MemoryTest();
$success = $suite->runAll();

exit($success ? 0 : 1);
