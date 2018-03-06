<?php
	//Nonce Check
	if (! isset( $_POST['dup_form_opts_nonce_field'] ) || ! wp_verify_nonce( $_POST['dup_form_opts_nonce_field'], 'dup_form_opts' ) ) {
		DUP_UI_Notice::redirect('admin.php?page=duplicator&tab=new1');
	}

	global $wp_version;
	wp_enqueue_script('dup-handlebars');

	if (empty($_POST)) {
		//F5 Refresh Check
		$redirect = admin_url('admin.php?page=duplicator&tab=new1');
		die("<script>window.location.href = '{$redirect}'</script>");
	}

	$Package = new DUP_Package();
	$Package->saveActive($_POST);
	$Package = DUP_Package::getActive();
	
	$mysqldump_on	 = DUP_Settings::Get('package_mysqldump') && DUP_DB::getMySqlDumpPath();
	$mysqlcompat_on  = isset($Package->Database->Compatible) && strlen($Package->Database->Compatible);
	$mysqlcompat_on  = ($mysqldump_on && $mysqlcompat_on) ? true : false;
	$dbbuild_mode    = ($mysqldump_on) ? 'mysqldump' : 'PHP';
    $zip_check		 = DUP_Util::getZipPath();
?>

<style>
	/*PROGRESS-BAR - RESULTS - ERROR */
	form#form-duplicator {text-align:center; max-width:650px; min-height:200px; margin:0px auto 0px auto; padding:0px;}
	div.dup-progress-title {font-size:22px; padding:5px 0 20px 0; font-weight:bold}
	div#dup-msg-success {padding:0 5px 5px 5px; text-align:left}
	div#dup-msg-success div.details {padding:10px 15px 10px 15px; margin:5px 0 15px 0; background:#fff; border-radius:5px; border:1px solid #ddd; box-shadow:0 8px 6px -6px #999; }
	div#dup-msg-success div.details-title {font-size:20px; border-bottom:1px solid #dfdfdf; padding:5px; margin:0 0 10px 0; font-weight:bold}
	div#dup-msg-success-subtitle {color:#999; margin:0; font-size:11px}
	div.dup-scan-filter-status {display:inline; font-size:11px; margin-right:10px; color:#630f0f;}
	div#dup-msg-error {color:#A62426; padding:5px; max-width:790px;}
	div#dup-msg-error-response-text { max-height:500px; overflow-y:scroll; border:1px solid silver; border-radius:3px; padding:10px;background:#fff}
	div.dup-hdr-error-details {text-align:left; margin:20px 0}
	i[data-tooltip].fa-question-circle {color:#555}

	/*SCAN ITEMS: Sections */
	div.scan-header { font-size:16px; padding:7px 5px 7px 7px; font-weight:bold; background-color:#E0E0E0; border-bottom:0px solid #C0C0C0 }
	div.scan-header-details {float:right; margin-top:-5px}
	div.scan-item {border:1px solid #E0E0E0; border-bottom:none;}
	div.scan-item-first { border-top-right-radius:4px; border-top-left-radius:4px}
	div.scan-item-last {border-bottom:1px solid #E0E0E0}
	div.scan-item div.title {background-color:#F1F1F1; width:100%; padding:4px 0 4px 0; cursor:pointer; height:20px;}
	div.scan-item div.title:hover {background-color:#ECECEC;}
	div.scan-item div.text {font-weight:bold; font-size:14px; float:left;  position:relative; left:10px}
	div.scan-item div.badge {float:right; border-radius:4px; color:#fff; min-width:40px; text-align:center; position:relative; right:10px; font-size:12px; padding:0 3px 0 3px}
	div.scan-item div.badge-pass {background:green;}
	div.scan-item div.badge-warn {background:#630f0f;}
	div.scan-item div.info {display:none; padding:10px; background:#fff}
	div.scan-good {display:inline-block; color:green;font-weight:bold;}
	div.scan-warn {display:inline-block; color:#630f0f;font-weight:bold;}
	div.dup-more-details {float:right; font-size:14px}
	div.dup-more-details a{color:black}
	div.dup-more-details a:hover {color:#777; cursor:pointer}
	div.dup-more-details:hover {color:#777; cursor:pointer}

	/*FILES */
	div#data-arc-size1 {display:inline-block; font-size:11px; margin-right:1px;}
	i.data-size-help { font-size:12px; display:inline-block;  margin:0; padding:0}
	div.dup-data-size-uncompressed {font-size:10px; text-align: right; padding:0; margin:-7px 0 0 0; font-style: italic; font-weight: normal; border:0px solid red; clear:both}
	div.hb-files-style div.container {border:1px solid #E0E0E0; border-radius:4px; margin:5px 0 10px 0}
	div.hb-files-style div.container b {font-weight:bold}
	div.hb-files-style div.container div.divider {margin-bottom:2px; font-weight:bold}
	div.hb-files-style div.data {padding:8px; line-height:21px; height:175px; overflow-y:scroll; }
	div.hb-files-style div.hdrs {padding:0 4px 4px 6px; border-bottom:1px solid #E0E0E0; font-weight:bold}
	div.hb-files-style div.hdrs sup i.fa {font-size:11px}
	div.hb-files-style div.hdrs-up-down {float:right;  margin:2px 12px 0 0}
	div.hb-files-style i.dup-nav-toggle:hover {cursor:pointer; color:#999}
	div.hb-files-style div.directory {margin-left:12px}
	div.hb-files-style div.directory i.size {font-size:11px;  font-style:normal; display:inline-block; min-width:50px}
	div.hb-files-style div.directory i.count {font-size:11px; font-style:normal; display:inline-block; min-width:20px}
	div.hb-files-style div.directory i.empty {width:15px; display:inline-block}
	div.hb-files-style div.directory i.dup-nav {cursor:pointer}
	div.hb-files-style div.directory i.fa {width:8px}
	div.hb-files-style div.directory i.chk-off {width:20px; color:#777; cursor: help; margin:0; font-size:1.25em}
	div.hb-files-style div.directory label {font-weight:bold; cursor:pointer; vertical-align:top;display:inline-block; width:475px; white-space: nowrap; overflow:hidden; text-overflow:ellipsis;}
	div.hb-files-style div.directory label:hover {color:#025d02}
	div.hb-files-style div.files {padding:2px 0 0 35px; font-size:12px; display:none; line-height:18px}
	div.hb-files-style div.files i.size {font-style:normal; display:inline-block; min-width:50px}
	div.hb-files-style div.files label {font-weight: normal; font-size:11px; vertical-align:top;display:inline-block;width:450px; white-space: nowrap; overflow:hidden; text-overflow:ellipsis;}
	div.hb-files-style div.files label:hover {color:#025d02; cursor: pointer}
	div.hb-files-style div.apply-btn {text-align:right; margin: 1px 0 10px 0; width:100%}
	div.hb-files-style div.apply-warn {float:left; font-size:11px; color:maroon; margin-top:-7px; font-style: italic; display:none; text-align: left}

	div#size-more-details {display:none; margin:5px 0 20px 0; border:1px solid #dfdfdf; padding:8px; border-radius: 4px; background-color: #F1F1F1}
	div#size-more-details ul {list-style-type:circle; padding-left:20px; margin:0}
	div#size-more-details li {margin:0}

	/*DATABASE*/
	div#dup-scan-db-info {margin-top:5px}
	div#data-db-tablelist {max-height:250px; overflow-y:scroll; border:1px solid silver; padding:8px; background: #efefef; border-radius: 4px}
	div#data-db-tablelist td{padding:0 5px 3px 20px; min-width:100px}
	div#data-db-size1 {display:inline-block; font-size:11px; margin-right:1px;}
	
	/*WARNING-CONTINUE*/
	div#dup-scan-warning-continue {display:none; text-align:center; padding:0 0 15px 0}
	div#dup-scan-warning-continue div.msg1 label{font-size:16px; color:#630f0f}
	div#dup-scan-warning-continue div.msg2 {padding:2px; line-height:13px}
	div#dup-scan-warning-continue div.msg2 label {font-size:11px !important}
	div.dup-pro-support {text-align:center; font-style:italic; font-size:13px; margin-top:20px;font-weight:bold}

	/*DIALOG WINDOWS*/
	div#arc-details-dlg {font-size:12px}
	div#arc-details-dlg h2 {margin:0; padding:0 0 5px 0; border-bottom:1px solid #dfdfdf;}
	div#arc-details-dlg hr {margin:3px 0 10px 0}
	div#arc-details-dlg table#db-area {margin:0;  width:98%}
	div#arc-details-dlg table#db-area td {padding:0;}
	div#arc-details-dlg table#db-area td:first-child {font-weight:bold;  white-space:nowrap; width:100px}
	div#arc-details-dlg div.filter-area {height:265px; overflow-y:scroll; border:1px solid #dfdfdf; padding:8px; margin:2px 0}
	div#arc-details-dlg div.file-info {padding:0 0 10px 15px; width:500px; white-space:nowrap;}
	div#arc-details-dlg div.file-info i.fa-question-circle { margin-right: 5px;  font-size: 11px;}

	div#arc-paths-dlg textarea.path-dirs,
		textarea.path-files {font-size:12px; border: 1px solid silver; padding: 10px; background: #fff; margin:5px; height:125px; width:100%; white-space:pre}
	div#arc-paths-dlg div.copy-button {float:right;}
	div#arc-paths-dlg div.copy-button button {font-size:12px}
	
	/*FOOTER*/
	div.dup-button-footer {text-align:center; margin:0}
	button.button {font-size:15px !important; height:30px !important; font-weight:bold; padding:3px 5px 5px 5px !important;}
        i.scan-warn {color:#630f0f;}
</style>

<!-- =========================================
TOOL BAR:STEPS -->
<table id="dup-toolbar">
	<tr valign="top">
		<td style="white-space:nowrap">
			<div id="dup-wiz">
				<div id="dup-wiz-steps">
					<div class="completed-step"><a>1-<?php _e('Setup', 'duplicator'); ?></a></div>
					<div class="active-step"><a>2-<?php _e('Scan', 'duplicator'); ?> </a></div>
					<div><a>3-<?php _e('Build', 'duplicator'); ?> </a></div>
				</div>
				<div id="dup-wiz-title">
					<?php _e('Step 2: System Scan', 'duplicator'); ?>
				</div> 
			</div>	
		</td>
		<td>
			<a href="?page=duplicator" class="add-new-h2"><i class="fa fa-archive"></i> <?php _e('Packages', 'duplicator'); ?></a> 
			<span> <?php _e('Create New', 'duplicator'); ?></span>
		</td>
	</tr>
</table>		
<hr class="dup-toolbar-line">


<form id="form-duplicator" method="post" action="?page=duplicator&tab=new3">
<?php wp_nonce_field('dup_form_opts', 'dup_form_opts_nonce_field', false); ?>

	<!--  PROGRESS BAR -->
	<div id="dup-progress-bar-area">
		<div class="dup-progress-title"><i class="fa fa-circle-o-notch fa-spin"></i> <?php _e('Scanning Site', 'duplicator'); ?></div>
		<div id="dup-progress-bar"></div>
		<b><?php _e('Please Wait...', 'duplicator'); ?></b><br/><br/>
		<i><?php _e('Keep this window open during the scan process.', 'duplicator'); ?></i><br/>
		<i><?php _e('This can take several minutes.', 'duplicator'); ?></i><br/>
	</div>

	<!--  ERROR MESSAGE -->
	<div id="dup-msg-error" style="display:none">
		<div class="dup-hdr-error"><i class="fa fa-exclamation-circle"></i> <?php _e('Scan Error', 'duplicator'); ?></div>
		<i><?php _e('Please try again!', 'duplicator'); ?></i><br/>
		<div class="dup-hdr-error-details">
			<b><?php _e("Server Status:", 'duplicator'); ?></b> &nbsp;
			<div id="dup-msg-error-response-status" style="display:inline-block"></div><br/>

			<b><?php _e("Error Message:", 'duplicator'); ?></b>
			<div id="dup-msg-error-response-text"></div>
		</div>
	</div>

	<!--  SUCCESS MESSAGE -->
	<div id="dup-msg-success" style="display:none">

		<div style="text-align:center">
			<div class="dup-hdr-success"><i class="fa fa-check-square-o fa-lg"></i> <?php _e('Scan Complete', 'duplicator'); ?></div>
			<div id="dup-msg-success-subtitle">
				<?php _e('Process Time:', 'duplicator'); ?> <span id="data-rpt-scantime"></span>
			</div>
		</div>

		<div class="details">
			<?php
				include ('s2.scan2.php');
				echo '<br/>';
				include ('s2.scan3.php');
			?>
		</div>

		<!-- WARNING CONTINUE -->
		<div id="dup-scan-warning-continue">
			<div class="msg1">
				<label for="dup-scan-warning-continue-checkbox">
					<?php _e('A notice status has been detected, are you sure you want to continue?', 'duplicator');?>
				</label>
				<div style="padding:8px 0">
					<input type="checkbox" id="dup-scan-warning-continue-checkbox" onclick="Duplicator.Pack.warningContinue(this)"/>
					<label for="dup-scan-warning-continue-checkbox"><?php _e('Yes.  Continue with the build process!', 'duplicator');?></label>
				</div>
			</div>
			<div class="msg2">
				<label for="dup-scan-warning-continue-checkbox">
					<?php
						_e("Scan checks are not required to pass, however they could cause issues on some systems.", 'duplicator');
						echo '<br/>';
						_e("Please review the details for each section by clicking on the detail title.", 'duplicator');
					?>
				</label>
			</div>
		</div>

		<div class="dup-button-footer" style="display:none">
			<input type="button" value="&#9664; <?php _e("Back", 'duplicator') ?>" onclick="window.location.assign('?page=duplicator&tab=new1')" class="button button-large" />
			<input type="button" value="<?php _e("Rescan", 'duplicator') ?>" onclick="Duplicator.Pack.rescan()" class="button button-large" />
			<input type="submit" value="<?php _e("Build", 'duplicator') ?> &#9654" class="button button-primary button-large" id="dup-build-button" />
		</div>
	</div>

</form>

<script>
jQuery(document).ready(function($)
{
	// Performs ajax call to get scanner retults via JSON response
	Duplicator.Pack.runScanner = function()
	{
		var data = {action : 'duplicator_package_scan',file_notice:'<?= $core_file_notice; ?>',dir_notice:'<?= $core_dir_notice; ?>'}
		$.ajax({
			type: "POST",
			cache: false,
			url: ajaxurl,
			dataType: "json",
			timeout: 10000000,
			data: data,
			complete: function() {$('.dup-button-footer').show()},
			success:  function(data) {
				Duplicator.Pack.loadScanData(data);
			},
			error: function(data) {
				$('#dup-progress-bar-area').hide();
				var status = data.status + ' -' + data.statusText;
				$('#dup-msg-error-response-status').html(status)
				$('#dup-msg-error-response-text').html(data.responseText);
				$('#dup-msg-error').show(200);
				console.log(data);
			}
		});
	}

	//Loads the scanner data results into the various sections of the screen
	Duplicator.Pack.loadScanData = function(data)
	{
		$('#dup-progress-bar-area').hide();

		//ERROR: Data object is corrupt or empty return error
		if (data == undefined || data.RPT == undefined) {
			Duplicator.Pack.intErrorView();
			console.log('JSON Report Data:');
			console.log(data);
			return;
		}

		$('#data-rpt-scantime').text(data.RPT.ScanTime || 0);
		Duplicator.Pack.intServerData(data);
		Duplicator.Pack.initArchiveFilesData(data);
		Duplicator.Pack.initArchiveDBData(data);

		//Addon Sites
		$('#data-arc-status-addonsites').html(Duplicator.Pack.setScanStatus(data.ARC.Status.AddonSites));
		if (data.ARC.FilterInfo.Dirs.AddonSites !== undefined && data.ARC.FilterInfo.Dirs.AddonSites.length > 0) {
			$("#addonsites-block").show();
		}

		$('#dup-msg-success').show();

		//Waring Check
		var warnCount = data.RPT.Warnings || 0;
		if (warnCount > 0) {
			$('#dup-scan-warning-continue').show();
			$('#dup-build-button').prop("disabled",true).removeClass('button-primary');
			if ($('#dup-scan-warning-continue-checkbox').is(':checked')) {
				$('#dup-build-button').removeAttr('disabled').addClass('button-primary');
			}
		} else {
			$('#dup-scan-warning-continue').hide();
			$('#dup-build-button').prop("disabled",false).addClass('button-primary');
		}
	}
	
	//Toggles each scan item to hide/show details
	Duplicator.Pack.toggleScanItem = function(item)
	{
		var $info = $(item).parents('div.scan-item').children('div.info');
		var $text = $(item).find('div.text i.fa');
		if ($info.is(":hidden")) {
			$text.addClass('fa-caret-down').removeClass('fa-caret-right');
			$info.show();
		} else {
			$text.addClass('fa-caret-right').removeClass('fa-caret-down');
			$info.hide(250);
		}
	}

	//Returns the scanner without a page refresh
	Duplicator.Pack.rescan = function()
	{
		$('#dup-msg-success,#dup-msg-error,.dup-button-footer').hide();
		$('#dup-progress-bar-area').show();
		Duplicator.Pack.runScanner();
	}

	//Allows user to continue with build if warnings found
	Duplicator.Pack.warningContinue = function(checkbox)
	{
		($(checkbox).is(':checked'))
			?	$('#dup-build-button').prop('disabled',false).addClass('button-primary')
			:	$('#dup-build-button').prop('disabled',true).removeClass('button-primary');
	}

	//Show the error message if the JSON data is corrupted
	Duplicator.Pack.intErrorView = function()
	{
		var html_msg;
		html_msg  = '<?php _e("Unable to perform a full scan, please try the following actions:", 'duplicator') ?><br/><br/>';
		html_msg += '<?php _e("1. Go back and create a root path directory filter to validate the site is scan-able.", 'duplicator') ?><br/>';
		html_msg += '<?php _e("2. Continue to add/remove filters to isolate which path is causing issues.", 'duplicator') ?><br/>';
		html_msg += '<?php _e("3. This message will go away once the correct filters are applied.", 'duplicator') ?><br/><br/>';

		html_msg += '<?php _e("Common Issues:", 'duplicator') ?><ul>';
		html_msg += '<li><?php _e("- On some budget hosts scanning over 30k files can lead to timeout/gateway issues. Consider scanning only your main WordPress site and avoid trying to backup other external directories.", 'duplicator') ?></li>';
		html_msg += '<li><?php _e("- Symbolic link recursion can cause timeouts.  Ask your server admin if any are present in the scan path.  If they are add the full path as a filter and try running the scan again.", 'duplicator') ?></li>';
		html_msg += '</ul>';
		$('#dup-msg-error-response-status').html('Scan Path Error [<?php echo rtrim(DUPLICATOR_WPROOTPATH, '/'); ?>]');
		$('#dup-msg-error-response-text').html(html_msg);
		$('#dup-msg-error').show(200);
	}

	//Sets various can statuses
	Duplicator.Pack.setScanStatus = function(status)
	{
		var result;
		switch (status) {
			case false :    result = '<div class="scan-warn"><i class="fa fa-exclamation-triangle"></i></div>'; break;
			case 'Warn' :   result = '<div class="badge badge-warn"><?php _e("Notice", 'duplicator') ?></div>'; break;
			case true :     result = '<div class="scan-good"><i class="fa fa-check"></i></div>'; break;
			case 'Good' :   result = '<div class="badge badge-pass"><?php _e("Good", 'duplicator') ?></div>'; break;
			default :
				result = 'unable to read';
		}
		return result;
	}

	//PAGE INIT:
	Duplicator.UI.AnimateProgressBar('dup-progress-bar');
	Duplicator.Pack.runScanner();

});
</script>