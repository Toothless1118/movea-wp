<?php
global $wp_version;
global $wpdb;

$action_updated = null;
$action_response = __("Package Settings Saved", 'duplicator');

//SAVE RESULTS
if (isset($_POST['action']) && $_POST['action'] == 'save') {

	//Nonce Check
	if (! isset( $_POST['dup_settings_save_nonce_field'] ) || ! wp_verify_nonce( $_POST['dup_settings_save_nonce_field'], 'dup_settings_save' )) {
		die('Invalid token permissions to perform this request.');
	}

    //Package
	$mysqldump_enabled		= isset($_POST['package_dbmode']) && $_POST['package_dbmode'] == 'mysql' ? "1" : "0";
	$mysqldump_exe_file		= isset($_POST['package_mysqldump_path']) 
								? trim(DUP_DB::escSQL(strip_tags($_POST['package_mysqldump_path']), true))
								: null;
	$mysqldump_path_valid	= is_file($mysqldump_exe_file) ? true : false;
	
	DUP_Settings::Set('last_updated', date('Y-m-d-H-i-s'));
    DUP_Settings::Set('package_zip_flush', isset($_POST['package_zip_flush']) ? "1" : "0");
	DUP_Settings::Set('package_mysqldump', $mysqldump_enabled ? "1" : "0");
	DUP_Settings::Set('package_phpdump_qrylimit', isset($_POST['package_phpdump_qrylimit']) ? $_POST['package_phpdump_qrylimit'] : "100");
	if ($mysqldump_path_valid) {
		$mysqldump_exe_file = DUP_Util::isWindows() ? realpath($mysqldump_exe_file) : $mysqldump_exe_file;
		DUP_Settings::Set('package_mysqldump_path', $mysqldump_exe_file);
	}
	DUP_Settings::Set('package_ui_created', $_POST['package_ui_created']);
    
	$action_updated = DUP_Settings::Save();
    DUP_Util::initSnapshotDirectory();
}

$package_zip_flush = DUP_Settings::Get('package_zip_flush');
$phpdump_chunkopts = array("20", "100", "500", "1000", "2000");
$phpdump_qrylimit = DUP_Settings::Get('package_phpdump_qrylimit');
$package_mysqldump = DUP_Settings::Get('package_mysqldump');
$package_mysqldump_path = trim(DUP_Settings::Get('package_mysqldump_path'));
$package_ui_created = is_numeric(DUP_Settings::Get('package_ui_created')) ? DUP_Settings::Get('package_ui_created') : 1;
$mysqlDumpPath = DUP_DB::getMySqlDumpPath();
$mysqlDumpFound = ($mysqlDumpPath) ? true : false;

?>

<style>
    form#dup-settings-form input[type=text] {width:500px; }
    div.dup-feature-found {padding:10px 0 5px 0; color:green;}
    div.dup-feature-notfound {color:maroon; width:600px; line-height: 18px}
	select#package_ui_created {font-family: monospace}
</style>

<form id="dup-settings-form" action="<?php echo admin_url('admin.php?page=duplicator-settings&tab=package'); ?>" method="post">
    <?php wp_nonce_field('dup_settings_save', 'dup_settings_save_nonce_field', false); ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="page"   value="duplicator-settings">

    <?php if ($action_updated) : ?>
        <div id="message" class="notice notice-success is-dismissible dup-wpnotice-box"><p><?php echo $action_response; ?></p></div>
    <?php endif; ?>

    <h3 class="title"><?php _e("Visual", 'duplicator') ?> </h3>
    <hr size="1" />
    <table class="form-table">
        <tr>
            <th scope="row"><label><?php _e("Created Format", 'duplicator'); ?></label></th>
            <td>
                <select name="package_ui_created" id="package_ui_created">
					<!-- YEAR -->
					<optgroup label="<?php _e("By Year", 'duplicator'); ?>">
						<option value="1">Y-m-d H:i &nbsp;	[2000-01-05 12:00]</option>
						<option value="2">Y-m-d H:i:s		[2000-01-05 12:00:01]</option>
						<option value="3">y-m-d H:i &nbsp;	[00-01-05   12:00]</option>
						<option value="4">y-m-d H:i:s		[00-01-05   12:00:01]</option>
					</optgroup>
					<!-- MONTH -->
					<optgroup label="<?php _e("By Month", 'duplicator'); ?>">
						<option value="5">m-d-Y H:i  &nbsp; [01-05-2000 12:00]</option>
						<option value="6">m-d-Y H:i:s		[01-05-2000 12:00:01]</option>
						<option value="7">m-d-y H:i  &nbsp; [01-05-00   12:00]</option>
						<option value="8">m-d-y H:i:s		[01-05-00   12:00:01]</option>
					</optgroup>
					<!-- DAY -->
					<optgroup label="<?php _e("By Day", 'duplicator'); ?>">
						<option value="9"> d-m-Y H:i &nbsp;	[05-01-2000 12:00]</option>
						<option value="10">d-m-Y H:i:s		[05-01-2000 12:00:01]</option>
						<option value="11">d-m-y H:i &nbsp;	[05-01-00	12:00]</option>
						<option value="12">d-m-y H:i:s		[05-01-00	12:00:01]</option>
					</optgroup>
				</select>
                <p class="description">
                    <?php _e("The date format shown in the 'Created' column on the Packages screen.", 'duplicator'); ?>
                </p>
            </td>
        </tr>
		</table>
		<br/>

		<h3 class="title"><?php _e("Processing", 'duplicator') ?> </h3>
		<hr size="1" />
		<table class="form-table">
        <tr>
            <th scope="row"><label><?php _e("SQL Script", 'duplicator'); ?></label></th>
            <td>
                <?php if (!DUP_Util::hasShellExec()) : ?>
					<input type="radio" disabled="true" />
                    <label><?php _e("Mysqldump", 'duplicator'); ?> <i style="font-size:12px">(<?php _e("recommended", 'duplicator'); ?>)</i></label>
                    <p class="description" style="width:550px; margin:5px 0 0 20px">
                        <?php
							_e("This server does not support the PHP shell_exec function which is required for mysqldump to run. ", 'duplicator');
							_e("Please contact the host or server administrator to enable this feature.", 'duplicator');
                        ?>
						<br/>
						<small>
							<i style="cursor: pointer"
								data-tooltip-title="<?php _e("Host Recommendation:", 'duplicator'); ?>"
								data-tooltip="<?php _e('Duplicator recommends going with the high performance pro plan or better from our recommended list', 'duplicator'); ?>">
							<i class="fa fa-lightbulb-o" aria-hidden="true"></i>
								<?php
									printf("%s <a target='_blank' href='//snapcreek.com/wordpress-hosting/'>%s</a> %s",
										__("Please visit our recommended", 'duplicator'),
										__("host list", 'duplicator'),
										__("for reliable access to mysqldump", 'duplicator'));
								?>
							</i>
						</small>
						<br/><br/>
                    </p>
                <?php else : ?>
                    <input type="radio" name="package_dbmode" value="mysql" id="package_mysqldump" <?php echo ($package_mysqldump) ? 'checked="checked"' : ''; ?> />
                    <label for="package_mysqldump"><?php _e("Mysqldump", 'duplicator'); ?></label>
                    <i style="font-size:12px">(<?php _e("recommended", 'duplicator'); ?>)</i> <br/>

                    <div style="margin:5px 0px 0px 25px">
                        <?php if ($mysqlDumpFound) : ?>
                            <div class="dup-feature-found">
								<i class="fa fa-check-circle"></i>
                                <?php _e("Successfully Found:", 'duplicator'); ?> &nbsp;
                                <i><?php echo $mysqlDumpPath ?></i>
                            </div><br/>
                        <?php else : ?>
                            <div class="dup-feature-notfound">
								<i class="fa fa-exclamation-triangle"></i>
                                <?php
									_e('Mysqldump was not found at its default location or the location provided.  Please enter a custom path to a valid location where mysqldump can run.  '
										. 'If the problem persist contact your host or server administrator.  ', 'duplicator');
           
									printf("%s <a target='_blank' href='//snapcreek.com/wordpress-hosting/'>%s</a> %s",
										__("See the", 'duplicator'),
										__("host list", 'duplicator'),
										__("for reliable access to mysqldump.", 'duplicator'));
																
								?>
                            </div><br/>

                        <?php endif; ?>

						<i class="fa fa-question-circle"
								data-tooltip-title="<?php _e("mysqldump path:", 'duplicator'); ?>"
								data-tooltip="<?php _e('An optional path to the mysqldump program.  Add a custom path if the path to mysqldump is not properly detected or needs to be changed.', 'duplicator'); ?>"></i>
                        <label><?php _e("Custom Path:", 'duplicator'); ?></label><br/>
                        <input type="text" name="package_mysqldump_path" id="package_mysqldump_path" value="<?php echo $package_mysqldump_path; ?>" placeholder="<?php _e("/usr/bin/mypath/mysqldump.exe", 'duplicator'); ?>" />
						<div class="dup-feature-notfound">
						<?php
							if ($action_updated && $mysqldump_path_valid === false) {
								$mysqldump_path = DUP_Util::isWindows() ? stripslashes($_POST['package_mysqldump_path']) : $_POST['package_mysqldump_path'];
								if (strlen($mysqldump_path)) {
									_e('<i class="fa fa-exclamation-triangle"></i> The custom path provided is not recognized as a valid mysqldump file:<br/>', 'duplicator');
									$mysqldump_path = esc_html($mysqldump_path);
									echo "'{$mysqldump_path}'";
								}
							}
						?>
						</div>
						<br/><br/>
                    </div>

                <?php endif; ?>

				<!-- PHP MODE -->
				<?php if (! $mysqlDumpFound) : ?>
					<input type="radio" name="package_dbmode" id="package_phpdump" value="php" checked="checked" />
				<?php else : ?>
					<input type="radio" name="package_dbmode" id="package_phpdump" value="php" <?php echo (! $package_mysqldump) ? 'checked="checked"' : ''; ?> />
				<?php endif; ?>

                <label for="package_phpdump"><?php _e("PHP Code", 'duplicator'); ?></label> &nbsp;

				<div style="margin:5px 0px 0px 25px">
					<i class="fa fa-question-circle"
					   data-tooltip-title="<?php _e("PHP Query Limit Size", 'duplicator'); ?>"
					   data-tooltip="<?php _e('A higher limit size will speed up the database build time, however it will use more memory.  If your host has memory caps start off low.', 'duplicator'); ?>"></i>
					<label for="package_phpdump_qrylimit"><?php _e("Query Limit Size", 'duplicator'); ?></label> &nbsp;
					<select name="package_phpdump_qrylimit" id="package_phpdump_qrylimit">
						<?php
							foreach($phpdump_chunkopts as $value) {
								$selected = ( $phpdump_qrylimit == $value ? "selected='selected'" : '' );
								echo "<option {$selected} value='{$value}'>" . number_format($value)  . '</option>';
							}
						?>
					</select>
				</div><br/>
            </td>
        </tr>
        <tr>
            <th scope="row"><label><?php _e("Archive Flush", 'duplicator'); ?></label></th>
            <td>
                <input type="checkbox" name="package_zip_flush" id="package_zip_flush" <?php echo ($package_zip_flush) ? 'checked="checked"' : ''; ?> />
                <label for="package_zip_flush"><?php _e("Attempt Network Keep Alive", 'duplicator'); ?></label>
                <i style="font-size:12px">(<?php _e("enable only for large archives", 'duplicator'); ?>)</i>
                <p class="description">
                    <?php _e("This will attempt to keep a network connection established for large archives.", 'duplicator'); ?>
                </p>
            </td>
        </tr>
    </table>


    <p class="submit" style="margin: 20px 0px 0xp 5px;">
		<br/>
		<input type="submit" name="submit" id="submit" class="button-primary" value="<?php _e("Save Package Settings", 'duplicator') ?>" style="display: inline-block;" />
	</p>

</form>

<script>
jQuery(document).ready(function($)
{
	$('#package_ui_created').val(<?php echo $package_ui_created ?> );
});
</script>