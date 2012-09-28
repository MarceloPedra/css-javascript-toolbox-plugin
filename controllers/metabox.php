<?php
/**
* @version post.php $ Id; 03-08-2012 00:51:00 Ahmed Said ;
*/

// Disallow direct access.
defined('ABSPATH') or die("Access denied");

/**
* Controll Block metabox actions.
* 
* @version 6.0
* @package CJT
* @subpackage Controllers
* @author Ahmed Said
*/
class CJTMetaboxController extends CJTAjaxController {
	
	/**
	* Initialize post controller.
	* 
	* @return void
	*/
	public function __construct($info) {
		parent::__construct($info);
		// If post Id is not supplied get it from $_GET request
		// Ajax request always comes with post Id within the $_GET request.
		// The other case when the post edit page is loaded the post Id will be provided 
		// by cssJSToolbox::dispatchController method inside identity parameter!
		$postId = $info['identity'][1] ? $info['identity'][1] : $_GET['post'];
		// Instantiate model object.
		$this->model = self::getModel('metabox', array($postId));
		// Make sure that current post type is selected by user.
		if ($this->model->doPost()) {
			// Action to be fired in the normal admin request.
			add_action('add_meta_boxes', array(&$this, 'showMetabox'));
			// Ajax actions.
			$this->registryAction('create');
			$this->registryAction('delete');
		}
	}
	
	/**
	* Create block metabox for specific post object.
	* 
	* @param integer Post id.
	* @return array New block object consist of block id and new block metabox view content.
	*/
	public function createAction() {
		// Get reserved block id from post object.
		$blockId = $this->model->getMetaboxId();
		if ($blockId) {
			// Set request paremeters for blocks-ajax controller::createBlockAction.
			$_GET['name'] = cssJSToolbox::getText(sprintf(cssJSToolbox::getText('CJT Block - Post #%d'), $this->model->getPost()->ID));
			$_GET['state'] = 'active';
			// Create post metabox.
			$this->model->create($pin)->save();
			// Create new block.
			$blocksController = CJTController::create('blocks-ajax');
			$blocksController->createBlockAction($blockId, 'metabox', $pin->flag, 'cjt-block');
			// Get metabox block view object.
			$this->view = CJTView::create('blocks/metabox');
			$this->view->setBlock(CJTModel::create('blocks')->getBlock($blockId));
			$this->view->setSecurityToken($this->createSecurityToken());
			// Send Javascript & CSS files needed for the metabox view to work.
			$this->response['references'] = self::getReferencesQueue();
			$this->response['view'] = $this->view->setOption('customizeMetabox', true)->getTemplate('metabox');
		}
	}
	
	/**
	* put your comment there...
	* 
	*/
	public function deleteAction() {
		// Get input vars.
		$metaboxBlockId = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
		// Delete metabox block.
		$blocks = CJTModel::create('blocks');
		$blocks->delete($metaboxBlockId)->save();
		// Unassociate post -- Mark post as HAS-NO-BLOCK-ASSOCIATED.
		$this->model->delete();
		// Load create-metabox view.
		$this->view = CJTView::create('blocks/create-metabox');
		// Create DUMMY block object.
		$block = (object) array();
		$block->id = $metaboxBlockId;
		$block->name = cssJSToolbox::getText('CJT Block');
		// Push vars into the view.
		$this->view->setBlock($block);
		$this->view->setSecurityToken($this->createSecurityToken());
		// Send Javascript & CSS files needed for the metabox view to work.
		$this->response['references'] = self::getReferencesQueue();
		// create-metabox view content.
		$this->response['view'] = $this->view->setOption('customizeMetabox', true)->getTemplate('create');
	}
	
	/**
	* put your comment there...
	* 
	*/
	protected static function getReferencesQueue() {
		$result = array();
		/** 
		Get all scripts needed to be loaded for block metabox to work.
		--------------------------------------------------------------
		*	1. Suppress the output we need the SRC URLs not the HTML tags.
		* 2. Fire scripts & styles action so the view would enqueue all the files.
		* 3. Merge script and styles into a single array.
		* 4. Get only files start with absoult URI (e.g start by HTTP).
		* files without HTTP may be already loaded (e.g jQuery, etc..)
		*/
		ob_start(); do_action('admin_print_scripts'); do_action('admin_print_styles'); ob_end_clean();
		$references = array_merge($GLOBALS['wp_scripts']->registered, $GLOBALS['wp_styles']->registered);
		foreach ($references as $script) {
			// Scripts with absoult URL is only for the metabox block
			// but not for Wordpress default scripts (e.g jquery, thickbox, etc...).
			if (strpos($script->src, 'http') === 0)	 {
				// For JS to work properly Always have cjt object set.
				if (!isset($script->cjt)) {
					$script->cjt = (object) array();
				}
				// Refine $script object and get only src, cjt and extra->data/localization properties!
				$script = (object) array_intersect_key(((array) $script), array_flip(array('src', 'cjt' , 'extra')));
				// Organize references into JS and CSS files lists.
				if (preg_match('/\.js$/', $script->src)) {
					$result['scripts'][] = $script;
				}
				else {
					$result['styles'][] = $script;
				}
			}
		}
		// Return references.
		return $result;
	}
	
	/**
	* Select which metabox to load.
	* 
	* create-metabox view will be loaded if user doesnt 
	* created a block for current post yet.
	* 
	* metabox view will be loaded if there is a block
	* already created for current post.
	* 
	* Callback for add_meta_boxes action.
	*/
	public function showMetabox() {
		// Import blocks view.
		CJTView::import('blocks/manager');
		/// Get block id.
		$metaboxId = $this->model->reservedMetaboxBlockId();
		// User didn't create block for this post yet.
		// Show create-metabox view.			
		if (!$this->model->hasBlock()) {
			// Set view template name.
			$viewName = 'create-metabox';
			// Create DUMMY block object.
			$block = (object) array();
			$block->id = $metaboxId;
			$block->name = cssJSToolbox::getText('CJT Block');
		}
		/*
		* Block post is already created.
		* This condition is only when the page first loaded
		* and has nothing to do with "create" action!
		*/
		else {
			// Set view template name.
			$viewName = 'metabox';
			// Get real block data.
			$block = CJTModel::create('blocks')->getBlock($metaboxId);
		}
		// Get block meta box view object instance.
		$this->view = CJTView::create("blocks/{$viewName}");
		// Push view vars.
		$this->view->setBlock($block);
		$this->view->setSecurityToken($this->createSecurityToken());
		// Add metabox.
		add_meta_box($this->view->getMetaboxId(), $this->view->getMetaboxName(), array(&$this->view, 'display'), $this->model->getPost()->post_type, 'normal');
	}
	
} // End class.