#!/usr/local/bin/php-cgi -f
<?php
require_once("config.inc");

// check FreeBSD release for fetch options >= 9.3
$release = explode("-", exec("uname -r"));
if ($release[0] >= 9.3) $verify_hostname = "--no-verify-hostname";
else $verify_hostname = "";

$dirname = dirname(__FILE__);
if (!is_dir("{$dirname}/downloady/log")) { mkdir("{$dirname}/downloady/log", 0775, true); }
$return_val = mwexec("fetch {$verify_hostname} -vo {$dirname}/downloady/downloady-install.php 'https://raw.github.com/crestAT/nas4free-downloady/master/downloady/downloady-install.php'", true);
if ($return_val == 0) { 
    chmod("{$dirname}/downloady/downloady-install.php", 0775);
    require_once("{$dirname}/downloady/downloady-install.php"); 
}
else { echo "\nInstallation file 'downloady-install.php' not found, installation aborted!\n"; }
?>
