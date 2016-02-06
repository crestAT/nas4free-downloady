<?php
/*
	dly-files.inc

    Copyright (c) 2015 - 2016 Andreas Schmidhuber
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice, this
       list of conditions and the following disclaimer.
    2. Redistributions in binary form must reproduce the above copyright notice,
       this list of conditions and the following disclaimer in the documentation
       and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
    ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
    WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
    DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
    ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
    (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
    LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
    ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
    (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
    SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

    The views and conclusions contained in the software and documentation are those
    of the authors and should not be interpreted as representing official policies,
    either expressed or implied, of the FreeBSD Project.
*/
require_once("auth.inc");
require_once("globals.inc");
require_once("rc.inc");
require_once("downloady.php");

bindtextdomain("nas4free", "/usr/local/share/locale-dly");

// Get the location of the log files (may be modified via rc.conf variables).
$clogdir = rc_getenv_ex("clog_logdir", $g['varlog_path']);

// The log configuration
$loginfo = array(
	array(
		"visible" => TRUE,
		"desc" => gettext("Downloads"),
		"logfile" => "{$dest}/temp/dfile_list.log",
		"filename" => "dfile_list.log",
		"type" => "plain",
		"pattern" => "/^(\S+\s+\S+\s+\S+)\s+(\S+)\s+(.*)$/",
		"columns" => array(
			array("title" => gettext("Date & Time"), "class" => "listlr", "param" => "nowrap=\"nowrap\"", "pmid" => 1),
			array("title" => gettext("Size"), "class" => "listr", "param" => "nowrap", "pmid" => 2),
			array("title" => gettext("File"), "class" => "listr", "param" => "", "pmid" => 3)
		))
);

function log_get_contents($logfile, $type) {
	global $config;

	$content = array();

	$param = "";   //(isset($config['syslogd']['reverse']) ? "-r " : "");

	switch ($type) {
		case "plain":
		    $filter_str = ($_POST['searchstring'] == '') ? "" : "| grep -i \"".htmlspecialchars($_POST['searchstring'])."\"";
			exec("/bin/cat {$logfile} {$filter_str}", $content);
	}

	return $content;
}

function log_display($loginfo) {
	if (!is_array($loginfo))
		return;

	// Create table header
	echo "<tr>";
	foreach ($loginfo['columns'] as $columnk => $columnv) {
		echo "<td {$columnv['param']} class='" . (($columnk == 0) ? "listhdrlr" : "listhdrr") . "'>".htmlspecialchars($columnv['title'])."</td>\n";
	}
	echo "</tr>";

	// Get log file content
	$content = log_get_contents($loginfo['logfile'], $loginfo['type']);
	if (empty($content))
		return;

	// Create table data
	foreach ($content as $contentv) {
		// Skip invalid pattern matches
		$result = preg_match($loginfo['pattern'], $contentv, $matches);
		if ((FALSE === $result) || (0 == $result))
			continue;

		// Skip empty lines
		if (count($loginfo['columns']) == 1 && empty($matches[1]))
			continue;

		echo "<tr valign=\"top\">\n";
		foreach ($loginfo['columns'] as $columnk => $columnv) {
			echo "<td {$columnv['param']} class='{$columnv['class']}'>" . htmlspecialchars($matches[$columnv['pmid']]) . "</td>\n";
		}
		echo "</tr>\n";
	}
}
?>
