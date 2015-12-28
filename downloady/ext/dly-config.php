<?php
/* 
    dly-config.php

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
require("auth.inc");
require("guiconfig.inc");

// Dummy standard message gettext calls for xgettext retrieval!!!
$dummy = gettext("The changes have been applied successfully.");
$dummy = gettext("The configuration has been changed.<br />You must apply the changes in order for them to take effect.");
$dummy = gettext("The following input errors were detected");

$pgtitle = array(gettext("Extensions"), gettext("Downloady")." ".$config['downloady']['version'], gettext("Configuration"));

if (!isset($config['downloady']) || !is_array($config['downloady'])) $config['downloady'] = array();

/* Check if the directory exists, the mountpoint has at least o=rx permissions and
 * set the permission to 775 for the last directory in the path
 */
function change_perms($dir) {
    global $input_errors;

    $path = rtrim($dir,'/');                                            // remove trailing slash
    if (strlen($path) > 1) {
        if (!is_dir($path)) {                                           // check if directory exists
            $input_errors[] = sprintf(gettext("Directory %s doesn't exist!"), $path);
        }
        else {
            $path_check = explode("/", $path);                          // split path to get directory names
            $path_elements = count($path_check);                        // get path depth
            $fp = substr(sprintf('%o', fileperms("/$path_check[1]/$path_check[2]")), -1);   // get mountpoint permissions for others
            if ($fp >= 5) {                                             // transmission needs at least read & search permission at the mountpoint
                $directory = "/$path_check[1]/$path_check[2]";          // set to the mountpoint
                for ($i = 3; $i < $path_elements - 1; $i++) {           // traverse the path and set permissions to rx
                    $directory = $directory."/$path_check[$i]";         // add next level
                    exec("chmod o=+r+x \"$directory\"");                // set permissions to o=+r+x
                }
                $path_elements = $path_elements - 1;
                $directory = $directory."/$path_check[$path_elements]"; // add last level
                exec("chmod 775 {$directory}");                         // set permissions to 775
                exec("chown {$_POST['who']} {$directory}*");
            }
            else
            {
                $input_errors[] = sprintf(gettext("Downloady needs at least read & execute permissions at the mount point for directory %s! Set the Read and Execute bits for Others (Access Restrictions | Mode) for the mount point %s (in <a href='disks_mount.php'>Disks | Mount Point | Management</a> or <a href='disks_zfs_dataset.php'>Disks | ZFS | Datasets</a>) and hit Save in order to take them effect."), $path, "/{$path_check[1]}/{$path_check[2]}");
            }
        }
    }
}

if (isset($_POST['save']) && $_POST['save']) {
    unset($input_errors);
	if (empty($input_errors)) {
        $config['downloady']['enable'] = isset($_POST['enable']) ? true : false;
        $config['downloady']['who'] = $_POST['who'];
        $config['downloady']['storage_path'] = !empty($_POST['storage_path']) ? $_POST['storage_path'] : $g['media_path'];
        $config['downloady']['storage_path'] = rtrim($config['downloady']['storage_path'],'/');         // ensure to have NO trailing slash
        if (!is_dir($config['downloady']['storage_path'].'/temp')) mkdir($config['downloady']['storage_path'].'/temp', 0700, true);
        change_perms($_POST['storage_path']);
        $config['downloady']['ratelimit'] = trim($_POST['ratelimit']);
        $config['downloady']['resume'] = isset($_POST['resume']) ? true : false;
        $savemsg = get_std_save_message(write_config());
    }   // end of empty input_errors
}

$pconfig['enable'] = isset($config['downloady']['enable']) ? true : false;
$pconfig['who'] = !empty($config['downloady']['who']) ? $config['downloady']['who'] : "";
$pconfig['storage_path'] = !empty($config['downloady']['storage_path']) ? $config['downloady']['storage_path'] : $g['media_path'];
$pconfig['ratelimit'] = !empty($config['downloady']['ratelimit']) ? $config['downloady']['ratelimit'] : "";
$pconfig['resume'] = isset($config['downloady']['resume']);

include("fbegin.inc");?>  
<script type="text/javascript">
<!--
function enable_change(enable_change) {
    var endis = !(document.iform.enable.checked || enable_change);
	document.iform.who.disabled = endis;
	document.iform.storage_path.disabled = endis;
	document.iform.storage_pathbrowsebtn.disabled = endis;
	document.iform.ratelimit.disabled = endis;
	document.iform.resume.disabled = endis;
}
//-->
</script>
<form action="dly-config.php" method="post" name="iform" id="iform">
    <table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr><td class="tabnavtbl">
		<ul id="tabnav">
        <?php if (isset($config['downloady']['enable'])) { ?>
    			<li class="tabinact"><a href="dly-status.php"><span><?=gettext("Download");?></span></a></li>
    			<li class="tabinact"><a href="dly-files.php"><span><?=gettext("Files");?></span></a></li>
    			<li class="tabact"><a href="dly-config.php"><span><?=gettext("Configuration");?></span></a></li>
    			<li class="tabinact"><a href="dly-update_extension.php"><span><?=gettext("Maintenance");?></span></a></li>
        <?php } else { ?>
    			<li class="tabact"><a href="dly-config.php"><span><?=gettext("Configuration");?></span></a></li>
    			<li class="tabinact"><a href="dly-update_extension.php"><span><?=gettext("Maintenance");?></span></a></li>
        <?php } ?>
		</ul>
	</td></tr>
    <tr><td class="tabcont">
        <?php if (!empty($input_errors)) print_input_errors($input_errors);?>
        <?php if (!empty($savemsg)) print_info_box($savemsg);?>
        <table width="100%" border="0" cellpadding="6" cellspacing="0">
            <?php html_titleline_checkbox("enable", gettext("Downloady"), $pconfig['enable'], gettext("Enable"), "enable_change(false)");?>
    		<?php $a_user = array(); foreach (system_get_user_list() as $userk => $userv) { $a_user[$userk] = htmlspecialchars($userk); }?>
            <?php html_combobox("who", gettext("Username"), $pconfig['who'], $a_user, gettext("Specifies the username which the service will run as."), false);?>
			<?php html_filechooser("storage_path", gettext("Download directory"), $pconfig['storage_path'], gettext("Where to save downloaded data."), $g['media_path'], false, 60);?>
            <?php html_inputbox("ratelimit", gettext("Download bandwidth"), $pconfig['ratelimit'], gettext("The maximum download bandwith in KiB/s. An empty field means infinity."), false, 8);?>
            <?php html_checkbox("resume", gettext("Resume"), $pconfig['resume'], gettext("Resume downloads after system startup."), "", false);?>
        </table>
        <div id="submit">
			<input id="save" name="save" type="submit" class="formbtn" value="<?=gettext("Save & Restart");?>"/>
        </div>
	</td></tr>
	</table>
	<?php include("formend.inc");?>
</form>
<script type="text/javascript">
<!--
enable_change(false);
//-->
</script>
<?php include("fend.inc");?>
