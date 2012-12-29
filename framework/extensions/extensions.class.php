<?php
/**
* 
*/

// Disallow direct access.
defined('ABSPATH') or die("Access denied");

/**
* 
*/
class CJTExtensions extends CJTHookableClass {
	
	/**
	* 
	*/
	const CACHE_OPTION_NAME = 'cjt_extensions';
	
	/**
	* 
	*/
	const LOAD_METHOD = 'getInvolved';
	
	/**
	* 
	*/
	const PREFIX = 'cjte-';
	
	/**
	* put your comment there...
	* 
	* @var mixed
	*/
	protected $extensions;
	
	/**
	* put your comment there...
	* 
	* @var mixed
	*/
	protected $loadMethod;
	
	/**
	* put your comment there...
	* 
	* @var mixed
	*/
	protected $onautoload = array('parameters' => array('file', 'class'));
	
	/**
	* put your comment there...
	* 
	* @var mixed
	*/
	protected $onbindevent = array('parameters' => array('event', 'callback'));
	
	/**
	* put your comment there...
	* 
	* @var mixed
	*/
	protected $ondetectextension  = array('parameters' => array('extension'));
	
	/**
	* put your comment there...
	* 
	* @var mixed
	*/
	protected $ongetactiveplugins = array('parameters' => array('plugins'));
	
	/**
	* put your comment there...
	* 
	* @var mixed
	*/
	protected $onload = array('parameters' => array('params'));
	
	/**
	* put your comment there...
	* 
	* @var mixed
	*/
	protected $onloadcallback = array('parameters' => array('callback'));
	
	/**
	* put your comment there...
	* 
	* @var mixed
	*/
	protected $onloaddefinition = array('parameters' => array('definition'));
	
	/***
	* put your comment there...
	* 
	* @var mixed
	*/
	protected $ontregisterautoload = array('parameters' => array('callback'));
	
	/**
	* put your comment there...
	* 
	* @var mixed
	*/
	protected $onreloadcacheparameters = array('parameters' => array('params'));
	
	/**
	* put your comment there...
	* 
	* @var mixed
	*/
	protected $onloaded = array(
		'hookType' => CJTWordpressEvents::HOOK_ACTION,
		'parameters' => array('class', 'extension', 'definition', 'result')
	);
	
	/**
	* put your comment there...
	* 
	* @var mixed
	*/
	protected $prefix;
	
	/**
	* put your comment there...
	* 
	* @param mixed $className
	*/
	 public function __autoload($className) {
		// Load only classed defined on the list!
		if (isset($this->extensions[$className])) {
			$classFile = $this->onautoload($this->extensions[$className]['runtime']['classFile'], $className);
			// Import class file!
			require_once $classFile;
		}
	}
	
	/**
	* put your comment there...
	* 
	* @param mixed $prefix
	* @param mixed $loadMethod
	* @return CJTExtensions
	*/
	public function __construct($prefix = self::PREFIX, $loadMethod = self::LOAD_METHOD) {
		// Hookable!
		parent::__construct();
		// Initializing!
		$this->prefix = $prefix;
		$this->loadMethod = $loadMethod;
	}
	
	/**
	* put your comment there...
	* 
	*/
	public function __destruct() {
		spl_autoload_unregister(array($this, '__autoload'));
	}
	
	/**
	* put your comment there...
	* 
	* @param mixed $reload
	* @return CJTExtensions
	*/
	public function getExtensions($reload = false) {
		// Get cached extensions or cache then if not yest cached!
		extract($this->onreloadcacheparameters(compact('reload')));
		if ($reload || !($extensions = get_option(self::CACHE_OPTION_NAME, array()))) {
			$extensions = array();
			$activePlugins = $this->ongetactiveplugins(wp_get_active_and_valid_plugins());
			foreach ($activePlugins as $file) {
				$pluginDir = dirname($file);
				$pluginName = basename($pluginDir);
				// Any plugin with our prefix is a CJT extension!
				if (strpos($pluginName, $this->prefix) === 0) {
					// CJT Extsnsion must has the definition XML file!
					$xmlFile = "{$pluginDir}/{$pluginName}.xml";
					if (file_exists($xmlFile)) {
						// Get Plugin primary data!
						$extension = array();
						$extension['file'] = basename($file);
						// Its useful to use ABS path only at runtime as it might changed as host might get moved.
						$extension['dir'] = str_replace((ABSPATH . PLUGINDIR . '/'), '', $pluginDir) ;
						$extension['name'] = $pluginName;
						// Cache XML file.
						$extension['definition']['raw'] = file_get_contents($xmlFile);
						// Filer!
						$extension = $this->ondetectextension($extension);
						// Read Basic XML Definition!
						$definitionXML = $this->onloaddefinition(new SimpleXMLElement($extension['definition']['raw']));
						$attrs = $definitionXML->attributes();
						$extension['definition']['primary']['loadMethod'] = (string) $attrs->loadMethod;
						// Add to list!
						$extensions[((string) $attrs->class)] = $extension;
						$definitionXML = null;
					}
				}
			}
			// Update the cache Cache!
			// ----update_option(self::CACHE_OPTION_NAME, $extensions);
		}
		$this->extensions = $this->onload($extensions);
		// Chaining
		return $this->extensions;
	}
	
	/**
	* put your comment there...
	* 
	*/
	public function load() {
		// Load all CJT extensions!
		foreach ($this->getExtensions() as $class => $extension) {
			extract($this->onload($extension, compact('class', 'extension')));
			// Initialize common vars!
			$callback = $this->onloadcallback(array($class, $this->loadMethod));
			$pluginPath = ABSPATH . PLUGINDIR . "/{$extension['name']}";
			// If auto load is speicifd then import class file and bind events.
			if ($extension['definition']['primary']['loadMethod'] == 'auto') {
				// Set runtime variables.
				$this->extensions[$class]['runtime']['classFile'] = "{$pluginPath}/{$extension['name']}.class.php";
				// Bind events!
				$definitionXML = new SimpleXMLElement($extension['definition']['raw']);
				foreach ($definitionXML->getInvolved->event as $event) {
					// filter!
					extract($this->onbindevent(compact('event', 'callback')));
					// Bind!
					CJTPlugin::on((string) $event->attributes()->type, $callback);
				}
			}
			else { // If manual load specified just 
				$this->onloaded($class, $extension, $definitionXML, call_user_func($callback));
			}
		}
		// Auto load CJT extensions files when requested.
		spl_autoload_register($this->ontregisterautoload(array($this, '__autoload')));
	}
	
} // End class.


// Hookable!
CJTExtensions::define('CJTExtensions', array('hookType' => CJTWordpressEvents::HOOK_FILTER));