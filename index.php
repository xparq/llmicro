<?php error_reporting(-1);
//phpinfo();

$t1 = microtime(true);

require "test.php";
require "test2.php";

echo "Timing: " . (microtime(true) - $t1);
