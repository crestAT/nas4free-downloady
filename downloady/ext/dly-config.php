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

function cronjob_process_updatenotification($mode, $data) {
	global $config;
	$retval = 0;
	switch ($mode) {
		case UPDATENOTIFY_MODE_NEW:
		case UPDATENOTIFY_MODE_MODIFIED:
			break;
		case UPDATENOTIFY_MODE_DIRTY:
			if (is_array($config['cron']['job'])) {
				$index = array_search_ex($data, $config['cron']['job'], "uuid");
				if (false !== $index) {
					unset($config['cron']['job'][$index]);
					write_config();
				}
			}
			break;
	}
	return $retval;
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
        $config['downloady']['enable_schedule'] = isset($_POST['enable_schedule']) ? true : false;
        $config['downloady']['schedule_startup'] = $_POST['startup'];
        $config['downloady']['schedule_closedown'] = $_POST['closedown'];
        $config['downloady']['full_bandwidth'] = isset($_POST['full_bandwidth']) ? true : false;

        if (isset($_POST['enable_schedule']) && ($_POST['startup'] == $_POST['closedown'])) { $input_errors[] = gettext("Startup and closedown hour must be different!"); }
        else {
            if (isset($_POST['enable_schedule'])) {
                $config['downloady']['enable_schedule'] = isset($_POST['enable_schedule']) ? true : false;
                $config['downloady']['schedule_startup'] = $_POST['startup'];
                $config['downloady']['schedule_closedown'] = $_POST['closedown'];
    
                $cronjob = array();
                $a_cronjob = &$config['cron']['job'];
                $uuid = isset($config['downloady']['schedule_uuid_startup']) ? $config['downloady']['schedule_uuid_startup'] : false;
                if (isset($uuid) && (FALSE !== ($cnid = array_search_ex($uuid, $a_cronjob, "uuid")))) {
                	$cronjob['enable'] = true;
                	$cronjob['uuid'] = $a_cronjob[$cnid]['uuid'];
                	$cronjob['desc'] = "Downloady startup (@ {$config['downloady']['schedule_startup']}:00)";
                	$cronjob['minute'] = $a_cronjob[$cnid]['minute'];
                	$cronjob['hour'] = $config['downloady']['schedule_startup'];
                	$cronjob['day'] = $a_cronjob[$cnid]['day'];
                	$cronjob['month'] = $a_cronjob[$cnid]['month'];
                	$cronjob['weekday'] = $a_cronjob[$cnid]['weekday'];
                	$cronjob['all_mins'] = $a_cronjob[$cnid]['all_mins'];
                	$cronjob['all_hours'] = $a_cronjob[$cnid]['all_hours'];
                	$cronjob['all_days'] = $a_cronjob[$cnid]['all_days'];
                	$cronjob['all_months'] = $a_cronjob[$cnid]['all_months'];
                	$cronjob['all_weekdays'] = $a_cronjob[$cnid]['all_weekdays'];
                	$cronjob['who'] = 'root';
                	$cronjob['command'] = "{$config['downloady']['rootfolder']}downloady_start.php && logger downloady: scheduled startup";
                } else {
                	$cronjob['enable'] = true;
                	$cronjob['uuid'] = uuid();
                	$cronjob['desc'] = "Downloady startup (@ {$config['downloady']['schedule_startup']}:00)";
                	$cronjob['minute'] = 0;
                	$cronjob['hour'] = $config['downloady']['schedule_startup'];
                	$cronjob['day'] = true;
                	$cronjob['month'] = true;
                	$cronjob['weekday'] = true;
                	$cronjob['all_mins'] = 0;
                	$cronjob['all_hours'] = 0;
                	$cronjob['all_days'] = 1;
                	$cronjob['all_months'] = 1;
                	$cronjob['all_weekdays'] = 1;
                	$cronjob['who'] = 'root';
                	$cronjob['command'] = "{$config['downloady']['rootfolder']}downloady_start.php && logger downloady: scheduled startup";
                    $config['downloady']['schedule_uuid_startup'] = $cronjob['uuid'];
                }
                if (isset($uuid) && (FALSE !== $cnid)) {
                		$a_cronjob[$cnid] = $cronjob;
                		$mode = UPDATENOTIFY_MODE_MODIFIED;
                	} else {
                		$a_cronjob[] = $cronjob;
                		$mode = UPDATENOTIFY_MODE_NEW;
                	}
                updatenotify_set("cronjob", $mode, $cronjob['uuid']);
//                write_config();
    
                unset ($cronjob);
                $cronjob = array();
                $a_cronjob = &$config['cron']['job'];
                $uuid = isset($config['downloady']['schedule_uuid_closedown']) ? $config['downloady']['schedule_uuid_closedown'] : false;
                if (isset($uuid) && (FALSE !== ($cnid = array_search_ex($uuid, $a_cronjob, "uuid")))) {
                	$cronjob['enable'] = true;
                	$cronjob['uuid'] = $a_cronjob[$cnid]['uuid'];
                	$cronjob['desc'] = "Downloady closedown (@ {$config['downloady']['schedule_closedown']}:00)";
                	$cronjob['minute'] = $a_cronjob[$cnid]['minute'];
                	$cronjob['hour'] = $config['downloady']['schedule_closedown'];
                	$cronjob['day'] = $a_cronjob[$cnid]['day'];
                	$cronjob['month'] = $a_cronjob[$cnid]['month'];
                	$cronjob['weekday'] = $a_cronjob[$cnid]['weekday'];
                	$cronjob['all_mins'] = $a_cronjob[$cnid]['all_mins'];
                	$cronjob['all_hours'] = $a_cronjob[$cnid]['all_hours'];
                	$cronjob['all_days'] = $a_cronjob[$cnid]['all_days'];
                	$cronjob['all_months'] = $a_cronjob[$cnid]['all_months'];
                	$cronjob['all_weekdays'] = $a_cronjob[$cnid]['all_weekdays'];
                	$cronjob['who'] = 'root';
                	$cronjob['command'] = "{$config['downloady']['rootfolder']}downloady_stop.php && logger downloady: scheduled closedown";
                } else {
                	$cronjob['enable'] = true;
                	$cronjob['uuid'] = uuid();
                	$cronjob['desc'] = "Downloady closedown (@ {$config['downloady']['schedule_closedown']}:00)";
                	$cronjob['minute'] = 0;
                	$cronjob['hour'] = $config['downloady']['schedule_closedown'];
                	$cronjob['day'] = true;
                	$cronjob['month'] = true;
                	$cronjob['weekday'] = true;
                	$cronjob['all_mins'] = 0;
                	$cronjob['all_hours'] = 0;
                	$cronjob['all_days'] = 1;
                	$cronjob['all_months'] = 1;
                	$cronjob['all_weekdays'] = 1;
                	$cronjob['who'] = 'root';
                	$cronjob['command'] = "{$config['downloady']['rootfolder']}downloady_stop.php && logger downloady: scheduled closedown";
                    $config['downloady']['schedule_uuid_closedown'] = $cronjob['uuid'];
                }
                if (isset($uuid) && (FALSE !== $cnid)) {
                		$a_cronjob[$cnid] = $cronjob;
                		$mode = UPDATENOTIFY_MODE_MODIFIED;
                	} else {
                		$a_cronjob[] = $cronjob;
                		$mode = UPDATENOTIFY_MODE_NEW;
                	}
                updatenotify_set("cronjob", $mode, $cronjob['uuid']);
//                write_config();
            }   // end of enable_schedule
            else {
                $config['downloady']['enable_schedule'] = isset($_POST['enable_schedule']) ? true : false;
            	updatenotify_set("cronjob", UPDATENOTIFY_MODE_DIRTY, $config['downloady']['schedule_uuid_startup']);
            	if (is_array($config['cron']['job'])) {
            				$index = array_search_ex($data, $config['cron']['job'], "uuid");
            				if (false !== $index) {
            					unset($config['cron']['job'][$index]);
            				}
            			}
//            	write_config();
            	updatenotify_set("cronjob", UPDATENOTIFY_MODE_DIRTY, $config['downloady']['schedule_uuid_closedown']);
            	if (is_array($config['cron']['job'])) {
            				$index = array_search_ex($data, $config['cron']['job'], "uuid");
            				if (false !== $index) {
            					unset($config['cron']['job'][$index]);
            				}
            			}
//            	write_config();
            }   // end of disable_schedule -> remove cronjobs
    		$retval = 0;
    		if (!file_exists($d_sysrebootreqd_path)) {
    			$retval |= updatenotify_process("cronjob", "cronjob_process_updatenotification");
    			config_lock();
    			$retval |= rc_update_service("cron");
    			config_unlock();
    		}
//    		$savemsg .= get_std_save_message($retval).'<br />';
    		if ($retval == 0) {
    			updatenotify_delete("cronjob");
    		}
        }   // end of schedule change

        $savemsg .= get_std_save_message(write_config());
    }   // end of empty input_errors
}

$pconfig['enable'] = isset($config['downloady']['enable']) ? true : false;
$pconfig['who'] = !empty($config['downloady']['who']) ? $config['downloady']['who'] : "";
$pconfig['storage_path'] = !empty($config['downloady']['storage_path']) ? $config['downloady']['storage_path'] : $g['media_path'];
$pconfig['ratelimit'] = !empty($config['downloady']['ratelimit']) ? $config['downloady']['ratelimit'] : "";
$pconfig['resume'] = isset($config['downloady']['resume']);
$pconfig['enable_schedule'] = isset($config['downloady']['enable_schedule']) ? true : false;
$pconfig['full_bandwidth'] = isset($config['downloady']['full_bandwidth']);

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
	document.iform.enable_schedule.disabled = endis;
	document.iform.startup.disabled = endis;
	document.iform.closedown.disabled = endis;
	document.iform.full_bandwidth.disabled = endis;
}

function schedule_change() {
	switch(document.iform.enable_schedule.checked) {
		case true:
			showElementById('startup_tr','show');
			showElementById('closedown_tr','show');
			showElementById('full_bandwidth_tr','show');
			break;

		case false:
			showElementById('startup_tr','hide');
			showElementById('closedown_tr','hide');
			showElementById('full_bandwidth_tr','hide');
			break;
	}
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
            <?php html_text("installation_directory", gettext("Installation directory"), sprintf(gettext("The extension is installed in %s."), $config['downloady']['rootfolder']));?>
    		<?php $a_user = array(); foreach (system_get_user_list() as $userk => $userv) { $a_user[$userk] = htmlspecialchars($userk); }?>
            <?php html_combobox("who", gettext("Username"), $pconfig['who'], $a_user, gettext("Specifies the username which the service will run as."), true);?>
			<?php html_filechooser("storage_path", gettext("Download directory"), $pconfig['storage_path'], gettext("Where to save downloaded data."), $g['media_path'], true, 60);?>
            <?php html_inputbox("ratelimit", gettext("Download bandwidth"), $pconfig['ratelimit'], gettext("The maximum download bandwith in KiB/s. An empty field means infinity."), false, 8);?>
            <?php html_checkbox("resume", gettext("Resume"), $pconfig['resume'], gettext("Resume downloads after system startup."), "", false);?>
            <?php html_checkbox("enable_schedule", gettext("Daily schedule"), $pconfig['enable_schedule'], gettext("Enable scheduler for downloads."), "", false, "schedule_change()");?>
    		<?php $hours = array(0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23); ?>
            <?php html_combobox("startup", gettext("Startup"), $config['downloady']['schedule_startup'], $hours, gettext("Choose a startup hour for")." ".$config['downloady']['appname'], true);?>
            <?php html_combobox("closedown", gettext("Closedown"), $config['downloady']['schedule_closedown'], $hours, gettext("Choose a closedown hour for")." ".$config['downloady']['appname'], true);?>
            <?php html_checkbox("full_bandwidth", gettext("Full bandwidth"), $pconfig['full_bandwidth'], gettext("Use full bandwidth on scheduled startup."), "", false);?>
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
schedule_change();
//-->
</script>
<?php include("fend.inc");?>
