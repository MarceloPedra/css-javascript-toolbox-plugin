<?php
/**
* 
*/

// Disallow direct access.
defined('ABSPATH') or die("Access denied");

/**
* 
*/
class CJTInstallerModel {
	
	/**
	* 
	*/
	const OPERATION_STATE_INSTALLED = 'installed';
	
	/**
	* 
	*/
	const INSTALLATION_STATE = 'state.CJTInstallerModel.operations';
	
	/**
	* 
	*/
	const NOTICED_DISMISSED_OPTION_NAME = 'settings.CJTInstallerModel.noticeDismissed';
	
	/**
	* put your comment there...
	* 
	* @var mixed
	*/
	protected $input;
	
	/**
	* put your comment there...
	* 
	* @var mixed
	*/
	protected $installedDbVersion;
	
	/**
	* put your comment there...
	* 
	*/
	public function __construct() {
		$this->installedDbVersion = get_option(CJTPlugin::DB_VERSION_OPTION_NAME);
	}
	
	/**
	* put your comment there...
	* 
	* @param mixed $dismiss
	*/
	public function dismissNotice($dismiss = null) {
		// Read current value!
		$currentValue = get_user_option(self::NOTICED_DISMISSED_OPTION_NAME);
		if ($dismiss !== null) {
			// Dismiss can' be reverted!
			update_user_option(get_current_user_id(), self::NOTICED_DISMISSED_OPTION_NAME, true);
		}
		return $currentValue;
	}
	
	/**
	* put your comment there...
	* 
	*/
	public function getInstalledDbVersion() {
		return $this->installedDbVersion;
	}
	
	/**
	* put your comment there...
	* 
	*/
	public function getInternalVersionName() {
		return str_replace('.', '', $this->installedDbVersion);
	}

	/**
	* put your comment there...
	* 
	* @param Boolean is to cache the operations if not yet cached!
	* @return array Operations list metadata.
	*/
	public function getOperations($doCache = false) {
		// If installation is didn't never run before thise would be unset!
		if (!($operations = get_option(self::INSTALLATION_STATE))) {
			// Import installer reflection!
			cssJSToolbox::import('framework:installer:reflection.class.php');
			// Get Installer operations.
			cssJSToolbox::import('includes:installer:installer:installer.class.php');
			$operations['operations']['install'] = CJTInstallerReflection::getInstance('CJTInstaller', 'CJTInstaller')->getOperations();
			if ($this->isUpgrade()) {
				// Get upgrade operations , Also cache upgrader info for later use!
				$operations['upgrader'] = $upgrader = $this->getUpgrader();
				cssJSToolbox::import($upgrader['file']);
				$operations['operations']['upgrade'] = CJTInstallerReflection::getInstance($upgrader['class'], 'CJTUpgradeNonTabledVersions')->getOperations();				
			}
			if ($doCache) {
				// Cache operations!
				update_option(self::INSTALLATION_STATE, $operations);				
			}
		}
		return $operations;
	}

	/**
	* put your comment there...
	* 
	*/
	public function getUpgrader() {
		// Upgrader file.
		$upgrader['file'] = "includes:installer:upgrade:{$this->getInstalledDbVersion()}:upgrade.class.php";
		// Upgrader class name.
		$upgrader['class'] = "CJTV{$this->getInternalVersionName()}Upgrade";
		return $upgrader;
	}
	
	/**
	* Allow executing of a single installation operation!
	* Both Install and Upgrade operations can be executed throught here
	* 
	* 
	* @return void
	*/
	public function install() {
		// Read input!
		$rOperation = $this->input['operation'];
		$type = $rOperation['type'];
		// Get allowed operations with thier state!
		$operations = $this->getOperations(true);
		// Invalid operation!
		if (!isset($operations['operations'][$type][$rOperation['name']])) {
			throw new Exception('Invalid operation');
		}
		else {
			// Install only if not installed!
			$operation =& $operations['operations'][$type][$rOperation['name']];
			if ($operation['state'] != self::OPERATION_STATE_INSTALLED) {
				// Import installer and get installer object!
				switch ($type) {
					case 'install':
						cssJSToolbox::import('includes:installer:installer:installer.class.php');
						$installer = CJTInstaller::getInstance();
					break;
					case 'upgrade':
						$upgrader = $operations['upgrader'];
						cssJSToolbox::import($upgrader['file']);
						$installer = new $upgrader['class']();
					break;
				}
				// Execute the requested operation, save state only when succesed!
				if ($installer->{$rOperation['name']}()) {
					$operation['state'] = self::OPERATION_STATE_INSTALLED;
					// Update operations cache to reflect the new state!
					update_option(self::INSTALLATION_STATE, $operations);
					// Say OK!
					$this->response = array('state' => self::OPERATION_STATE_INSTALLED);
				}
			}
		}
	}
	
	/**
	* put your comment there...
	* 
	*/
	public function isUpgrade() {
		// If the version is not the same and not equal to current version then its upgrading!
		$isUpgrade = (($this->installedDbVersion != CJTPlugin::DB_VERSION) && ($this->installedDbVersion != ''));
		return $isUpgrade;
	}
	
	/**
	* put your comment there...
	* 
	* @param mixed $input
	*/
	public function setInput(& $input) {
		$this->input = $input;
		return $this; // Chaining!
	}
	
} // End class.
