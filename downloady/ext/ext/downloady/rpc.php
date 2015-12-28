<?php
/*
	rpc.php

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
require_once("downloady.php");

$namechop = 80;
$refresh = 5;

$d = new downloady($dest, $ratelimit);

$sid = (isset($_REQUEST['sid']) ? $_REQUEST['sid']: NULL);
$cmd = strtolower(isset($_REQUEST['action']) ? $_REQUEST['action']: NULL);


function HumanReadableSize($size) {
   $filesizename = array(" Bytes", " KB", " MB", " GB", " TB", " PB", " EB", " ZB", " YB");
   if ($size > 0) return round($size/pow(1024, ($i = floor(log($size, 1024)))), 2) . $filesizename[$i]; 
   else return $size;  
}


function ShortenName($name, $maxlength) { // needs improvements
	if (strlen($name) <= $maxlength) return($name);

	$extension = substr($name,-10,10); // last 10 chars.
	if (($pointer = strrpos($extension,".")) !== FALSE) {
		$extension = substr($extension,$pointer);
		}
	else {
		$extension = "";
		}
	return (substr($name,0,$maxlength)."...".$extension);
}


if (!empty($sid)) {
	$running = $d->GetStats('is_running', $sid);

	switch ($cmd) {
		case 'done': 
			print ((int)(!$running && $d->RemoveJob($sid)));
			exit(0);
			break;

		case 'pause':
			if (!$running) {
				print(0); exit(0);
				}
			$d->PauseJob($sid);
			$running = $d->GetStats('is_running', $sid, false);
			break;

		case 'resume':
			if ($running) {
				print(0); exit(0);
				}
			$d->Resume($sid);
			$running = $d->GetStats('is_running', $sid, false);
			break;

		case "trash":
			if ($running) {
				print(0); exit(0);
				}
			$d->RemoveFile($sid);
			break;

		case "log":
			readfile($d->GetStatfile($sid));
			die();

		}

	$details = $d->GetDetails($sid);
/* @A001    $size = number_format($details['size'] + 0, 0, ',', '.'); */
   	$size = HumanReadableSize($details['size']);
	$exists = file_exists($details['savefile']);

	switch ($cmd) {
		case 'get':
			if ($details && $details['done']) {

				if (!function_exists('mime_content_type')) {
				   function mime_content_type($f) {
				       $f = escapeshellarg($f);
				       return trim( `file -bi $f` );
					   }
					}

				if ($exists) {
					header('Content-type: '.mime_content_type($details['savefile']));
					header('Content-disposition: attachment; filename="'.basename($details['savefile']).'"');
					header('Content-length: '.sprintf('%u',filesize($details['savefile'])));
					readfile($details['savefile']);
					}
				}
			die();
		case 'info':
			$pid = $d->GetStats('pid', $sid);
			print("{$sid}\t{$details['url']}\t{$details['savefile']}\t{$size}\t{$pid}");
			die();
		}

 	$name = ShortenName(basename($details['savefile']), $namechop);

//    $size_fetched = $details['fetched'];
 	if ($details['percent'] == 100) $size_fetched = $size;
    else $size_fetched = HumanReadableSize($details['fetched']*1024);

	// print stats
	print("{$sid}\t{$name}\t{$size}\t{$details['speed']}\t{$details['percent']}\t");
	print("{$details['percent']}% ({$size_fetched})\t");
	print("{$details['done']}\t{$running}\t{$exists}\t{$details['timerem']}");
	}
else {
	$list = $d->GetJobList();
	$any_running = false;

	foreach($list as $sid) {
		$running = $d->GetStats('is_running', $sid);
		$any_running = $any_running || $running;
		$details = $d->GetDetails($sid);
 		$name = ShortenName(basename($details['savefile']), $namechop);
/* @A001    $size = number_format($details['size'] + 0, 0, ',', '.'); */
     	$size = HumanReadableSize($details['size']);
//    $size_fetched = $details['fetched'];
     	if ($details['percent'] == 100) $size_fetched = $size;
        else $size_fetched = HumanReadableSize($details['fetched']*1024);
		$exists = file_exists($details['savefile']);

		print("{$sid}\t{$name}\t{$size}\t{$details['speed']}\t{$details['percent']}\t");
		print("{$details['percent']}% ({$size_fetched})\t");
		print("{$details['done']}\t{$running}\t{$exists}\t{$details['timerem']}\n");
		}

	$usage = $d->GetDiskUsage();
	if (!$any_running) $refresh *= 4;
	print("{$refresh}\t{$usage['percent']}\t<b>{$usage['destdir']}</b> -> {$usage['percent']}% used (".HumanReadableSize($usage['free'])." free)");
	}

?>