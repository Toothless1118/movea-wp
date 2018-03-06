<?php

require_once(DUPLICATOR_PLUGIN_PATH . '/ctrls/ctrl.base.php');
require_once(DUPLICATOR_PLUGIN_PATH . '/classes/utilities/class.u.scancheck.php');
require_once(DUPLICATOR_PLUGIN_PATH . '/classes/utilities/class.u.json.php');
require_once(DUPLICATOR_PLUGIN_PATH . '/classes/package/class.pack.php');

/**
 *  DUPLICATOR_PACKAGE_SCAN
 *  Returns a JSON scan report object which contains data about the system
 *  
 *  @return json   JSON report object
 *  @example	   to test: /wp-admin/admin-ajax.php?action=duplicator_package_scan
 */
function duplicator_package_scan() {
	
	header('Content-Type: application/json;');
	DUP_Util::hasCapability('export');
	
	@set_time_limit(0);
	$errLevel = error_reporting();
	error_reporting(E_ERROR);
	DUP_Util::initSnapshotDirectory();
	
	$package = DUP_Package::getActive();
	$report = $package->runScanner();
	
	$package->saveActiveItem('ScanFile', $package->ScanFile);
	$json_response = DUP_JSON::safeEncode($report);
	
	DUP_Package::tempFileCleanup();
	error_reporting($errLevel);
    die($json_response);
}

/**
 *  duplicator_package_build
 *  Returns the package result status
 *  
 *  @return json   JSON object of package results
 */
function duplicator_package_build() {
	
	DUP_Util::hasCapability('export');
	
	check_ajax_referer( 'dup_package_build', 'nonce');
	
	header('Content-Type: application/json');
	
	@set_time_limit(0);
	$errLevel = error_reporting();
	error_reporting(E_ERROR);
	DUP_Util::initSnapshotDirectory();

	$Package = DUP_Package::getActive();
	
	if (!is_readable(DUPLICATOR_SSDIR_PATH_TMP . "/{$Package->ScanFile}")) {
		die("The scan result file was not found.  Please run the scan step before building the package.");
	}
	
	$Package->runBuild();
	
	//JSON:Debug Response
	//Pass = 1, Warn = 2, Fail = 3
	$json = array();
	$json['Status']   = 1;
	$json['Package']  = $Package;
	$json['Runtime']  = $Package->Runtime;
	$json['ExeSize']  = $Package->ExeSize;
	$json['ZipSize']  = $Package->ZipSize;
	$json_response = json_encode($json);

	//Simulate a Host Build Interrupt
	//die(0);

	error_reporting($errLevel);
    die($json_response);
}

/**
 *  DUPLICATOR_PACKAGE_DELETE
 *  Deletes the files and database record entries
 *
 *  @return json   A JSON message about the action.
 *				   Use console.log to debug from client
 */
function duplicator_package_delete() {
	
    DUP_Util::hasCapability('export');    
    check_ajax_referer( 'package_list', 'nonce' );
    
    try {
		global $wpdb;
		$json		= array();
		$post		= stripslashes_deep($_POST);
		$tblName	= $wpdb->prefix . 'duplicator_packages';
		$postIDs	= isset($post['duplicator_delid']) ? $post['duplicator_delid'] : null;
		$list		= explode(",", $postIDs);
		$delCount	= 0;
		
        if ($postIDs != null) {
            
            foreach ($list as $id) {
				
				$getResult = $wpdb->get_results($wpdb->prepare("SELECT name, hash FROM `{$tblName}` WHERE id = %d", $id), ARRAY_A);
				
				if ($getResult) {
					$row		=  $getResult[0];
					$nameHash	= "{$row['name']}_{$row['hash']}";
					$delResult	= $wpdb->query($wpdb->prepare( "DELETE FROM `{$tblName}` WHERE id = %d", $id ));
					if ($delResult != 0) {
						//Perms
						@chmod(DUP_Util::safePath(DUPLICATOR_SSDIR_PATH_TMP . "/{$nameHash}_archive.zip"), 0644);
						@chmod(DUP_Util::safePath(DUPLICATOR_SSDIR_PATH_TMP . "/{$nameHash}_database.sql"), 0644);
						@chmod(DUP_Util::safePath(DUPLICATOR_SSDIR_PATH_TMP . "/{$nameHash}_installer.php"), 0644);						
						@chmod(DUP_Util::safePath(DUPLICATOR_SSDIR_PATH . "/{$nameHash}_archive.zip"), 0644);
						@chmod(DUP_Util::safePath(DUPLICATOR_SSDIR_PATH . "/{$nameHash}_database.sql"), 0644);
						@chmod(DUP_Util::safePath(DUPLICATOR_SSDIR_PATH . "/{$nameHash}_installer.php"), 0644);
						@chmod(DUP_Util::safePath(DUPLICATOR_SSDIR_PATH . "/{$nameHash}_scan.json"), 0644);
						@chmod(DUP_Util::safePath(DUPLICATOR_SSDIR_PATH . "/{$nameHash}.log"), 0644);
						//Remove
						@unlink(DUP_Util::safePath(DUPLICATOR_SSDIR_PATH_TMP . "/{$nameHash}_archive.zip"));
						@unlink(DUP_Util::safePath(DUPLICATOR_SSDIR_PATH_TMP . "/{$nameHash}_database.sql"));
						@unlink(DUP_Util::safePath(DUPLICATOR_SSDIR_PATH_TMP . "/{$nameHash}_installer.php"));
						@unlink(DUP_Util::safePath(DUPLICATOR_SSDIR_PATH . "/{$nameHash}_archive.zip"));
						@unlink(DUP_Util::safePath(DUPLICATOR_SSDIR_PATH . "/{$nameHash}_database.sql"));
						@unlink(DUP_Util::safePath(DUPLICATOR_SSDIR_PATH . "/{$nameHash}_installer.php"));
						@unlink(DUP_Util::safePath(DUPLICATOR_SSDIR_PATH . "/{$nameHash}_scan.json"));
						@unlink(DUP_Util::safePath(DUPLICATOR_SSDIR_PATH . "/{$nameHash}.log"));
						//Unfinished Zip files
						$tmpZip = DUPLICATOR_SSDIR_PATH_TMP . "/{$nameHash}_archive.zip.*";
						if ($tmpZip !== false) {
							array_map('unlink', glob($tmpZip));
						}
						$delCount++;
					} 
				}
            }
        }

    } catch (Exception $e) {
		$json['error'] = "{$e}";
        die(json_encode($json));
    }
	
	$json['ids'] = "{$postIDs}";
	$json['removed'] = $delCount;
    die(json_encode($json));
}



/**
 * Controller for Tools
 * @package Duplicator\ctrls
 */
class DUP_CTRL_Package extends DUP_CTRL_Base
{
	/**
     *  Init this instance of the object
     */
	function __construct()
	{
		add_action('wp_ajax_DUP_CTRL_Package_addQuickFilters', array($this, 'addQuickFilters'));
	}


	/**
     * Removed all reserved installer files names
	 *
	 * @param string $_POST['dir_paths']		A semi-colon separated list of directory paths
	 *
	 * @return string	Returns all of the active directory filters as a ";" separated string
     */
	public function addQuickFilters($post)
	{
		$post = $this->postParamMerge($post);
		check_ajax_referer($post['action'], 'nonce');
		$result = new DUP_CTRL_Result($this);

		try {
			//CONTROLLER LOGIC
			$package = DUP_Package::getActive();

			//DIRS
			$dir_filters = $package->Archive->FilterDirs.';' . $post['dir_paths'];
			$dir_filters = $package->Archive->parseDirectoryFilter($dir_filters);
			$changed = $package->Archive->saveActiveItem($package, 'FilterDirs', $dir_filters);

			//FILES
			$file_filters = $package->Archive->FilterFiles.';' . $post['file_paths'];
			$file_filters = $package->Archive->parseFileFilter($file_filters);
			$changed = $package->Archive->saveActiveItem($package, 'FilterFiles', $file_filters);

			
			$changed = $package->Archive->saveActiveItem($package, 'FilterOn', 1);

			//Result
			$package = DUP_Package::getActive();
			$payload['dirs-in'] = $post['dir_paths'];
			$payload['dir-out'] = $package->Archive->FilterDirs;
			$payload['files-in'] = $post['file_paths'];
			$payload['files-out'] = $package->Archive->FilterFiles;

			//RETURN RESULT
			$test = ($changed) ? DUP_CTRL_Status::SUCCESS : DUP_CTRL_Status::FAILED;
			$result->process($payload, $test);

		} catch (Exception $exc) {
			$result->processError($exc);
		}
	}

}