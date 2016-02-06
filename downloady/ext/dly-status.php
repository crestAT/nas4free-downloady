<?php
/*
	dstatus.php

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
require_once("config.inc");
if (!isset($config['downloady']['enable'])) header("Location:dly-config.php");

require_once("auth.inc");
require_once("downloady.php");

bindtextdomain("nas4free", "/usr/local/share/locale-dly");
$pgtitle = array(gettext("Extensions"), gettext("Downloady")." ".$config['downloady']['version']);

$html_header = array(
array(
	"visible" => TRUE,
	"desc" => gettext("Downloads"),
	"logfile" => "{$dest}/dfile_list.log",
	"filename" => "dfile_list.log",
	"type" => "plain",
	"pattern" => "/^(\S+\s+\S+\s+\S+)\s+(\S+)\s+(.*)$/",
	"columns" => array(
		array("title" => gettext("File"), "class" => "listlr", "param" => "align=\"left\"", "pmid" => 1),
		array("title" => gettext("Size"), "class" => "listr", "param" => "align=\"center\"", "pmid" => 2),
		array("title" => gettext("Fetched"), "class" => "listr", "param" => "align=\"center\"", "pmid" => 3),
		array("title" => gettext("Speed"), "class" => "listr", "param" => "align=\"center\"", "pmid" => 4),
		array("title" => gettext("Time"), "class" => "listr", "param" => "align=\"center\"", "pmid" => 5),
		array("title" => gettext("Options"), "class" => "listr", "param" => "align=\"center\"", "pmid" => 6)
	))
);

function log_display($loginfo) {
	if (!is_array($loginfo)) return;

	// Create table header
	echo "<tr>";
	foreach ($loginfo['columns'] as $columnk => $columnv) {
		echo "<td {$columnv['param']} class='" . (($columnk == 0) ? "listhdrlr" : "listhdrr") . "'>".htmlspecialchars($columnv['title'])."</td>\n";
	}
	echo "</tr>";
}

if (isset($_POST['start_all']) && $_POST['start_all']) {
    $d = new downloady($dest, $ratelimit);
    $d->StartAll();
}

if (isset($_POST['stop_all']) && $_POST['stop_all']) {
    $d = new downloady($dest, $ratelimit);
    $d->StopAll();
}

if (isset($_POST['remove_all']) && $_POST['remove_all']) {
    $d = new downloady($dest, $ratelimit);
    $d->RemoveAll();
}

if (isset($_POST['remove_delete']) && $_POST['remove_delete']) {
    $d = new downloady($dest, $ratelimit);
    $d->RemoveDelete();
}

if (isset($_POST['download']) && $_POST['download']) {
    $url = (isset($_REQUEST['url'])? $_REQUEST['url'] : NULL);
    $rs = (isset($_REQUEST['rapidshare'])? true : false);
    $pause = (isset($_REQUEST['pause'])? true : false);
    if($url) {
        $urls = explode("\n", $url);
        for($i = 0; $i < count($urls); $i++) {
            $url_parts = parse_url($urls[$i]);
            switch($url_parts['scheme']) { // Make sure the URL is valid
            	case "http":
            	case "https":
            	case "ftp":
                    $d = new downloady($dest, $ratelimit);
            		$d->AddURL($urls[$i], $rs, $pause);
                	$savemsg .= gettext('File').": ".basename($urls[$i])." ".gettext('added.')."<br />";
//                	if ((!$pause) && ($ratelimit != 0)) $savemsg .= "<br />".gettext("Start download(s) with the 'Start all' button.")."<br />";
                break;
                default:
                    if (trim($urls[$i]) == "") break;
                	$savemsg .= gettext('URL').": ".$urls[$i]." ".gettext("is not allowed for download. Only URLs containing the schemes 'http', 'https' and 'ftp' are permitted for download.")."<br />";
            }
        }
    }
}
bindtextdomain("nas4free", "/usr/local/share/locale");                  // to get the right main menu language
include("fbegin.inc");
bindtextdomain("nas4free", "/usr/local/share/locale-dly"); ?>
<form action="dly-status.php" method="post" name="iform" id="iform">
    <table width="100%" border="0" cellpadding="0" cellspacing="0">
    	<tr><td class="tabnavtbl">
    		<ul id="tabnav">
    			<li class="tabact"><a href="dly-status.php"><span><?=gettext("Download");?></span></a></li>
    			<li class="tabinact"><a href="dly-files.php"><span><?=gettext("Files");?></span></a></li>
    			<li class="tabinact"><a href="dly-config.php"><span><?=gettext("Configuration");?></span></a></li>
    			<li class="tabinact"><a href="dly-update_extension.php"><span><?=gettext("Maintenance");?></span></a></li>
    		</ul>
    	</td></tr>
    	<tr><td class="tabcont">
            <?php if (!empty($input_errors)) print_input_errors($input_errors);?>
            <?php if (!empty($savemsg)) print_info_box($savemsg);?>
            <center>
            <link rel="stylesheet" type="text/css" href="ext/downloady/style.css"></link>
            <script type="text/javascript" src="ext/downloady/dstatus.js"></script>
            <script type="text/javascript" src="ext/downloady/rpc.js"></script>
            <body onload="LoadAll();">

    		<form enctype="application/x-form-urlencoded" method="POST" name="download">
    			<textarea name="url" style="width: 100%; font-size: 9pt;" COLS="50" ROWS="2"  type="text" placeholder="&lt;Enter URL(s) here&gt;"></textarea>
                <br /><br />
    		<table  style="width: 100%; white-space: nowrap;">
                <tr><td style="width: 25%;">&nbsp;</td>
                    <td style="width: 50%; text-align: center;">
            			<input name="pause" type="checkbox" value="true" />Start Paused &nbsp;&nbsp;&nbsp;
                        <!-- <input name="rapidshare" type="checkbox" value="true" />Rapidshare &nbsp;&nbsp;&nbsp; -->
            			<input name="download" type="submit" class="formbtn" title="<?=gettext("Add to the job list");?>" value="<?=gettext("Download");?>"/>&nbsp;&nbsp;&nbsp;
            			<input name="start_all" type="submit" class="formbtn" title="<?=gettext("Start all jobs");?>" value="<?=gettext("Start all");?>"/>
            			<input name="stop_all" type="submit" class="formbtn" title="<?=gettext("Stop all jobs");?>" value="<?=gettext("Stop all");?>"/>
        			</td>
        			<td style="width: 25%; text-align: right;">
                        <input name="remove_all" type="submit" class="formbtn" title="<?=gettext("Remove all jobs from the job list");?>" value="<?=gettext("Remove");?>"/>
            			<input name="remove_delete" type="submit" class="formbtn" title="<?=gettext("Remove all jobs and delete downloaded files");?>" value="<?=gettext("Remove & delete");?>"/>
        			</td>
        		</tr>
            </table>
    		</form>
            <div style="width: 100%; background-color:#EEEEEE; ">   <!-- border:1px solid #DEDBD1; border-collapse:separate; border-spacing:2px; -->
                <table id="content" style="width: 100%">
                    <tr><td>
                        <div id="usage" class="progress">
                            <div class="right"></div>
                            <div class="left"></div>
                            <div class="on"></div>
                            <div class="off" style="width: 100%;"></div>
                            <div class="value"></div>
                        </div>
                    </td></tr>
                    <tr><td></td></tr><br />
                    <tr>
                        <td class="background" id="progress">
                            <table id="files" cellspacing="0" cellpadding="0" style="width: 100%;">
                        		<tr style="display: none;">
                        			<td class ="name"></td>
                        			<td class ="size"></td>
                                    <td style="padding: 0px 5px;">
                                        <div class="progress">
                                            <div class="right"></div>
                                            <div class="left"></div>
                                            <div class="on"></div>
                                            <div class="off" style="width: 100%;"></div>
                                            <div class="value"></div>
                                        </div>
                                    </td>
                                	<td class="speed"><span style="white-space: nowrap;">&nbsp;</span></td>
                                	<td class="timerem"><span style="white-space: nowrap;">&nbsp;</span></td>
                                	<td class="actions" style="width: 1%;">
                                		<div>&nbsp;</div>
                                		<div>&nbsp;</div>
                                		<div>&nbsp;</div>
                                		<div>&nbsp;</div>
                                		<div>&nbsp;</div>
                                	</td>
                                </tr>
                                <tr style="display: none;">
                                	<td colspan="4">
                                		<table class="stats">
                                			<tr>
                                				<th style="text-align: left;">Source URL:</th>
                                				<td colspan="6">&nbsp;</td>
                                			</tr>
                                			<tr>
                                				<th style="text-align: left;">Dest file:</th>
                                				<td colspan="6">&nbsp;</td>
                                			</tr>
                                			<tr>
                                				<th style="width: 13%;">Size:</th>
                                				<td style="width: 20%;">&nbsp;</td>
                                				<th style="width: 13%;">Time Remaining:</th>
                                				<td style="width: 20%;" >&nbsp;</td>
                                				<th style="width: 13%;">PID:</th>
                                				<td style="width: 20%;">&nbsp;</td>
                                			</tr>
                                			<tr>
                                				<td colspan="6"><div class="log"><pre></pre></div></td>
                                			</tr>
                                		</table>
                                	</td>
                                </tr>
                                <br /><?php log_display($html_header[0]);?>
                            </table>
                        </td>
                    </tr>
                    <tr>
                    	<td class="background"><br />
                    		<div style="width: 100%; text-align: center;" class="small">Downloady - PHP Download Manager - By CyberLeo |&nbsp;
                    			<a href="http://www.cyberleo.net/cyberleo/Projects/">cyberLeo Projects</a>
                    		</div>
                        </td>
                    </tr>
                </table>
            </div>
            </center>
    		<?php include("formend.inc");?>
         </td></tr>
    </table>
</form>
<?php include("fend.inc");?>
