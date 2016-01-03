#!/usr/local/bin/php-cgi -f
<?php
require_once("config.inc");
$extension_dir = "/usr/local/www";
require_once("{$extension_dir}/downloady.php");

$d = new downloady($dest, $ratelimit);
$d->StopAll();
?>
