<?php
/*
	dly-files.php
	
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
require("dly-files.inc");

if (isset($_GET['log'])) $log = $_GET['log'];
if (isset($_POST['log'])) $log = $_POST['log'];
if (empty($log)) $log = 0;

bindtextdomain("nas4free", "/usr/local/share/locale-dly");
$pgtitle = array(gettext("Extensions"), gettext("Downloady")." ".$config['downloady']['version'], gettext("Files"));
$pgperm['allowuser'] = TRUE;

$return_val = mwexec("fetch -o {$config['downloady']['rootfolder']}version_server.txt https://raw.github.com/crestAT/nas4free-downloady/master/downloady/version.txt", false);
if ($return_val == 0) {
    $server_version = exec("cat {$config['downloady']['rootfolder']}version_server.txt");
    if ($server_version != $config['downloady']['version']) { $savemsg = sprintf(gettext("New extension version %s available, push '%s' button to install the new version!"), $server_version, gettext("Update Extension")); }
}   //EOversion-check

bindtextdomain("nas4free", "/usr/local/share/locale");                  // to get the right main menu language
include("fbegin.inc");
bindtextdomain("nas4free", "/usr/local/share/locale-dly"); ?>
<script type="text/javascript">
<!--
function log_change() {
	// Reload page
	window.document.location.href = 'dly-files.php?log=' + document.iform.log.value;
}
//-->
</script>
<form action="dly-files.php" method="post" name="iform" id="iform">
    <table width="100%" border="0" cellpadding="0" cellspacing="0">
    	<tr><td class="tabnavtbl">
    		<ul id="tabnav">
    			<li class="tabinact"><a href="dly-status.php"><span><?=gettext("Download");?></span></a></li>
    			<li class="tabact"><a href="dly-files.php"><span><?=gettext("Files");?></span></a></li>
    			<li class="tabinact"><a href="dly-config.php"><span><?=gettext("Configuration");?></span></a></li>
    			<li class="tabinact"><a href="dly-update_extension.php"><span><?=gettext("Maintenance");?></span></a></li>
    		</ul>
    	</td></tr>
    	<tr><td class="tabcont">
            <?php if (!empty($input_errors)) print_input_errors($input_errors);?>
            <?php if (!empty($savemsg)) print_info_box($savemsg);?>
    		<input name="refresh" type="submit" class="formbtn" value="<?=gettext("Refresh");?>" />
			<span class="label">&nbsp;&nbsp;&nbsp;<?=gettext("Search string");?></span>
			<input size="30" id="searchstring" name="searchstring" value="<?=$searchstring;?>" />
			<input name="search" type="submit" class="formbtn" value="<?=gettext("Search");?>" />
            <br /><br />
    		<table width="100%" border="0" cellpadding="0" cellspacing="0">
                <?php exec("cd {$dest} && ls -hal *.* | awk '{print $6,$7,$8,$5,$9}' > {$dest}/temp/dfile_list.log"); ?>
                <?php log_display($loginfo[$log]);?>
    		</table>
    		<?php include("formend.inc");?>
         </td></tr>
    </table>
</form>
<?php include("fend.inc");?>
