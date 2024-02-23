<?php
/**
 * Fabrik List Javascript
 *
 * @package     Joomla.Plugin
 * @subpackage  Fabrik.list.js
 * @copyright   Copyright (C) 2005-2020  Media A-Team, Inc. - All rights reserved.
 * @license     GNU/GPL http://www.gnu.org/copyleft/gpl.html
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

// Require the abstract plugin class
require_once COM_FABRIK_FRONTEND . '/models/plugin-list.php';

/**
 *  Add an action button to run PHP
 *
 * @package     Joomla.Plugin
 * @subpackage  Fabrik.list.js
 * @since       3.0
 */

class PlgFabrik_ListEasyAdmin extends PlgFabrik_List {
	private $images;
	
	function __construct(&$subject, $config) {
		parent::__construct($subject, $config);
	}

	/*
	* Init function
	*/
	protected function init() {
		$this->jsScriptTranslation();
		$opts = new StdClass;

		$opts->baseUri = JURI::base();
		$opts->elements = $this->processElements($this->model->getElements(true));
		$opts->elementsNames = $this->processElementsNames($this->model->getElements(true));
		$opts->listUrl = $this->createListLink($this->getModel()->getId());
		$opts->actionMethod = $this->model->actionMethod();
		$opts->images = $this->getImages();

		// Load the JS code and pass the opts
		$this->loadJS($opts);
	}

	/*
	* Function to load the javascript code for the plugin
	*/
	protected function loadJS($opts) {
		$optsJson = json_encode($opts);
		$jsFiles = array();
		$jsFiles['Fabrik'] = 'media/com_fabrik/js/fabrik.js';
		$jsFiles['FabrikEasyAdmin'] = '/plugins/fabrik_list/easyadmin/easyadmin.js';
		$script = "var fabrikEasyAdmin = new FabrikEasyAdmin($optsJson);";
		FabrikHelperHTML::script($jsFiles, $script);
	}

	protected function processElements($elements) {
		$processedElements = new stdClass;
		foreach($elements as $key => $value) {
			$fullElementName = $this->processFullElementName($key);
			$processedElements->$fullElementName = $this->createLink($value->element->id);	
		}
		return $processedElements;
	}

	protected function processElementsNames($elements) {
		$processedElements = new stdClass;
		foreach($elements as $key => $value) {
			$fullElementName = $this->processFullElementName($key);
			$processedElements->$fullElementName = $value->element->label;	
		}
		return $processedElements;
	}

	protected function processFullElementName($key) {
		// Select point on str
		$pos = strpos($key, '.');
		
		// Get first part
		$firstName = substr ($key , 1, $pos-2);
		// Get second part
		$lastName = substr ($key , $pos+2);
		// Remove last char from secont part
		$lastName = substr ($lastName , 0, strlen($lastName) - 1);
		// Join them to the full name
		$processedKey = $firstName . "___" . $lastName;
		return $processedKey;
	}

	protected function createLink($elementId) {
		$baseUri = JURI::base();
		return $baseUri . "administrator/index.php?option=com_fabrik&view=element&layout=edit&id=". $elementId . "&modalView=1";
	}

	protected function createListLink($listId) {
		$baseUri = JURI::base();
		return $baseUri ."administrator/index.php?option=com_fabrik&view=list&layout=edit&id=". $listId . "&modalView=1";
	}

	/*
	* Function run on when list is being loaded
	* Used to trigger the init function
	*/
	public function onLoadData(&$args) {
		$this->setImages();
		$this->init();
	}

	public function setImages() {
		$this->images['admin'] = FabrikHelperHTML::image('admin.png', 'list');
		$this->images['edit'] = FabrikHelperHTML::image('edit.png', 'list');
	}

	public function getImages() {
		return $this->images;
	}

	/**
     * Function sends message texts to javascript file
     *
     * @since version
     */
    function jsScriptTranslation()
    {
        Text::script('PLG_EASY_ADMIN_ACTION_METHOD_ERROR');
    }
}
