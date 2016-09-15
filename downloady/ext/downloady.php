<?php
/*
	downloady.php

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
require_once("guiconfig.inc");

// download rate in bytes/s, use of 'k' and 'M' postfix is possible
$dest = $config['downloady']['storage_path'];
if ($config['downloady']['ratelimit'] == '') $ratelimit = '0';
else $ratelimit = $config['downloady']['ratelimit'].'k';

function my_file_put_contents($filename, $data, $append = false) {
	$fp = fopen($filename, $append ? 'ab' : 'wb');
	chmod($filename, 0600); // This file may contain passwords
	fwrite($fp, $data."\n");
	fclose($fp);
	}

class downloady {
	// Configuration
	// This is where the downloads and status files go. Make sure this directory exists and is WRITABLE by the webserver process!
 	var $destdir;
	var $tmpdir;
	var $logfile;
	var $ratelimit = '';
        
	// Path and name of your server's Wget-compatible binary
	var $wget = '/usr/local/bin/wget';

	// Extra options to Wget go here. man wget for details.
	// @A001: add --no-iri, damit alle Ausgaben angezeigt werden
	var $wgetoptions = '--no-iri --continue --user-agent="MyBrowse/1.1 (GS/OS 6.0.5; AppleIIgs)" --tries="10" --random-wait --waitretry="10"'; //--limit-rate="25k"
	var $rsoptions = '--load-cookies /mnt/DATA/TEST/ftp/cookies';

	var $stats_cache = Array();

    function __construct($dir, $rate) {
        $this->destdir = $dir;
        $this->tmpdir = $dir.'/temp';
        $this->logfile = $dir.'/temp/downloady.log';
        $this->ratelimit = $rate;
    }
     
	function downloady() {
		if (!file_exists($this->destdir)) @mkdir($this->destdir, 0700, true); // attempt to create; it may fail...
		if (!file_exists($this->tmpdir)) @mkdir($this->tmpdir, 0700, true); // attempt to create; it may fail...

		$stat = stat($this->tmpdir);
		if ($stat['mode'] & 0007) {
			print("WARNING: {$this->tmpdir} is publicly accessible! This is a security risk, as temporary files may contain passwords.<br>");
			}
	}

	function GetDiskUsage() {
		$res = Array();
		$res['total'] = disk_total_space($this->destdir);
		$res['free'] = disk_free_space($this->destdir);
		$res['used'] = $res['total'] - $res['free'];
		$res['percent'] = number_format(100 * $res['used'] / $res['total'], 2);
		$res['destdir'] = $this->destdir;
		return($res);
	}

	function GetJobList() {
		$res = Array();
		foreach(glob("{$this->tmpdir}/*.stat") as $filename)
			if($filename) $res[] = basename($filename, ".stat");
		sort($res);       // sort filenames
		return($res);
	}

	function GetStats($what, $sid = '', $cache = true) {
		if ($cache)
			if(!empty($this->stats_cache[$what.$sid])) return($this->stats_cache[$what.$sid]);

		switch($what) {
			case 'pid':
				$pidfile = "{$this->tmpdir}/{$sid}.pid";
				$res = file_exists($pidfile) ? (int)file_get_contents($pidfile) : -1;
				break;
			case 'is_running':
				$pid = $this->GetStats('pid', $sid, $cache);
				$res = intval(`ps axopid,command |grep -v grep |grep {$pid} |grep -c wget`);
				break;
			}
		return($this->stats_cache[$what.$sid] = $res);
	}

	function AddURL($url, $rs = false, $pause = false) {
		$url = trim($url);
        $download_url = $url;
//		$sid = md5($url);                                               //@A use the file name as id
        list($file, $rest) = explode("?r=", $url, 2);                   //@A get rid of redirection data
        $url = $file;
		$sid = basename($url);

		$urlfile = "{$this->tmpdir}/{$sid}.url";
		$statfile = "{$this->tmpdir}/{$sid}.stat";
		$pidfile = "{$this->tmpdir}/{$sid}.pid";
		
		if($rs){
			$this->wgetoptions = "{$this->wgetoptions} {$this->rsoptions}";
			$rsfile = "{$this->tmpdir}/{$sid}.rs";
			my_file_put_contents($rsfile, $rs, false);
		}
		
		my_file_put_contents($urlfile, $download_url);
		my_file_put_contents($this->logfile, $url, true);

		$safe_urlfile = escapeshellarg($urlfile);
		$safe_url = escapeshellarg($url);
		$safe_destdir = escapeshellarg($this->destdir);
		$safe_statfile = escapeshellarg($statfile);
		$safe_ratelimit = escapeshellarg($this->ratelimit);
        //@A set download filename explicit with -O option -> must include also the download directory name!
		exec("{$this->wget} {$this->wgetoptions} --limit-rate={$safe_ratelimit} --referer={$safe_url} --background --input-file={$safe_urlfile} --progress=dot --append-output={$safe_statfile} -O {$safe_destdir}/{$sid}", $output);
		
		preg_match('/[0-9]+/', $output[0], $output);

		my_file_put_contents($pidfile, $output[0]);
		if($pause) $this->PauseJob($sid);
		else if ($this->ratelimit != 0) $this->StopAll();
		return(true);
	}

	function RemoveFile($sid) {
		if($this->GetStats('is_running', $sid)) return(false);
		$details = $this->GetDetails($sid);
		if (file_exists($details['savefile']))
			unlink($details['savefile']);
		return(true);
	}

	function PauseJob($sid) {
		if(!@is_file("{$this->tmpdir}/{$sid}.stat")) return(false);
		return ($this->GetStats('is_running', $sid) && posix_kill($this->GetStats('pid', $sid), 15));
	}

	function Resume($sid) {
		if($this->GetStats('is_running', $sid)) return(false);
	
		$details = $this->GetDetails($sid);
		if($details['done'] && file_exists($details['savefile'])) return(false);		
		
		return($this->AddURL($details['url'],$this->GetRapidShare($sid),false));
	}

	function StartAll() {
        $list = $this->GetJobList();
    	if ($this->ratelimit != 0) {
        	$entries = count($list);
        	foreach($list as $sid) {
        		$details = $this->GetDetails($sid);
        		if($details['done'] && file_exists($details['savefile'])) --$entries;
            }
        	if ($entries > 1) $this->ratelimit = floor($this->ratelimit / $entries).'k';
        }
    	foreach($list as $sid) $this->Resume($sid); 
    }

	function StopAll() {
    	$list = $this->GetJobList();
    	foreach($list as $sid) $this->PauseJob($sid);
    }

	function RemoveAll() {
        $this->StopAll();
    	$list = $this->GetJobList();
    	foreach($list as $sid) {
            $this->RemoveJob($sid);
        }
    }

	function RemoveDelete() {
        $this->StopAll();
        sleep(5);
    	$list = $this->GetJobList();
    	foreach($list as $sid)  {
            $this->RemoveFile($sid);
            $this->RemoveJob($sid);
        }
    }

	function RemoveJob($sid) {
		@unlink("{$this->tmpdir}/{$sid}.url");
		@unlink("{$this->tmpdir}/{$sid}.stat");
		@unlink("{$this->tmpdir}/{$sid}.pid");
		@unlink("{$this->tmpdir}/{$sid}.rs");
		return(true);
	}

	function GetStatfile($sid) {
		return("{$this->tmpdir}/{$sid}.stat");
	}
	
	function GetRapidShare($sid){
		$statfile = "{$this->tmpdir}/{$sid}.rs";
		if(!@is_file($statfile)) return(false);
				
		$fp = fopen($statfile, 'rb');
		$rs = false;

		if(!feof($fp)) {
			$line = fgets($fp, 2048);
			if(preg_match("/^1/i",$line)){
				$rs=true;
			}
		}
		return $rs;
	}

	function GetDetails($sid, $verbose = false) {
		$statfile = "{$this->tmpdir}/{$sid}.stat";
		if(!@is_file($statfile)) return(false);

		$fp = fopen($statfile, 'rb');
		$res = Array(
						'done' => 0,
						'url' => '',
						'savefile' => '',
						'size' => 0,
						'percent' => 0,
						'fetched' => 0,
						'speed' => 0,
						'timerem' => 0
					);

		$log = Array();
		$count = 0;

		while(!feof($fp)) {
			$count++;
			$line = fgets($fp, 2048); // read a line

			if($count == 1) { // URL
				$tmp = explode(" ", $line, 3);
				$res['url'] = trim($tmp[2]);
				} 
			elseif(preg_match("/^\S+: ([0-9,\s]+)\(/", $line, $regs)){ // Length
				$res['size'] = str_replace(Array(' ', ','), '', $regs[1]);
				}
            elseif(preg_match("/^Saving to:\s*[`](.*)[']/i", $line, $regs)){ // Destination file
//				$res['savefile'] = basename($regs[1]);
				}
			elseif(preg_match("/^[ \t]*(\d+\w*)\w*[ .,]+(\d+)[%]*\s*(\d*[.]*\d*\w*)\s*[=]*\s*(\d*[.]*\d*\w*)/i", $line, $regs)){
				$res['fetched'] = $regs[1];
				$res['percent'] = floatval($regs[2]);
				$res['speed'] = $regs[3];
				if($regs[4]!=null && $regs[4]!="undefined"){				
					if(preg_match("/[=]/i",$line)){
						$res['timerem'] = "Done in: {$regs[4]}";
					} else {
						$res['timerem'] = $regs[4];
					}
				} else {
					$res['timerem'] = "-";
				}				
			} elseif(preg_match("/^.*?\(([^)]+)[^']+ saved \[([^\]]+)]$/i", $line, $regs)){
				$res['fetched'] = $regs[2];
				$res['percent'] = 100;
				$res['speed'] = $regs[1];				
			} elseif(preg_match("/\s+saved\s+/i", $line, $regs)){
				$res['percent'] = 100;
				$res['done'] = 1;
			}
            list($file, $rest) = explode("?r=", basename($res['url']), 2);  //@A get rid of redirection data
            $clean_url = $file;                                             //@A create a clean file name
            $res['savefile'] = $this->destdir."/".$clean_url;               //@A set from url to prevent language conflicts
		}
		fclose($fp);
		return $res;
	}
}   // Eof class downloady
?>
