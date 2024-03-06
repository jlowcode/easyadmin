<?php
/**
 * Fabrik List Plugin
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
use Joomla\CMS\Factory;
use Joomla\CMS\Layout\FileLayout;
use Joomla\Registry\Registry;

// Requires 
// Change to namespaces on F5
require_once COM_FABRIK_FRONTEND . '/models/plugin-list.php';
require_once JPATH_PLUGINS . '/fabrik_element/field/field.php';
require_once JPATH_PLUGINS . '/fabrik_element/dropdown/dropdown.php';
require_once JPATH_PLUGINS . '/fabrik_element/databasejoin/databasejoin.php';
require_once JPATH_BASE . '/components/com_fabrik/models/element.php';
require_once JPATH_BASE . '/components/com_fabrik/models/list.php';
require_once JPATH_ADMINISTRATOR . '/components/com_fabrik/models/element.php';

/**
 *  Buttons to edit list and create elements on site Front-End
 *
 * @package     Joomla.Plugin
 * @subpackage  Fabrik.list.js
 */
class PlgFabrik_ListEasyAdmin extends PlgFabrik_List {
	private $images;
	private $elements;
	private $subject;

	/**
	 * Constructor
	 *
	 * @param   object &$subject The object to observe
	 * @param   array  $config   An array that holds the plugin configuration
	 *
	 */
	function __construct(&$subject, $config) {
		parent::__construct($subject, $config);

		$this->setSubject($subject);
		$this->setElements();
		$this->customizedStyle();
	}

	/**
	 * Init function
	 *
	 * @return  null
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

		echo $this->setUpModalAddElements();

		// Load the JS code and pass the opts
		$this->loadJS($opts);
	}

	/**
	 * Function to load the javascript code for the plugin
	 *
	 * @param   array  $opts 	Configuration array for javascript.
	 *
	 * @return  null
	 */
	protected function loadJS($opts) {
		$ext    = FabrikHelperHTML::isDebug() ? '.js' : '-min.js';

		$optsJson = json_encode($opts);
		$jsFiles = array();
		
		$jsFiles['Fabrik'] = 'media/com_fabrik/js/fabrik.js';
		$jsFiles['FabrikEasyAdmin'] = '/plugins/fabrik_list/easyadmin/easyadmin' . $ext;
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
		$pos = strpos($key, '.');
		$firstName = substr ($key , 1, $pos-2);
		$lastName = substr ($key , $pos+2);
		$lastName = substr ($lastName , 0, strlen($lastName) - 1);
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

	/**
	 * Function run on when list is being loaded. Used to trigger the init function
	 *
	 * @param   array &$args Arguments
	 * 
	 * @return null
	 */
	public function onPreLoadData(&$args) {
		$this->setImages();
		$this->init();
	}

	/**
	 * Setting the databasejoin object to list element
	 *
	 * @param   array 	$elements		Reference to databasejoin object
	 *
	 * @return  null
	 * 
	 * @since 	version 4.0
	 */
	public function onLoadData(&$args) {
		$objDatabasejoin = $this->elements['list']['objField'];
		$json = json_encode($objDatabasejoin->elementJavascript(0));

		FabrikHelperHTML::script(['ElementDatabasejoin' => 'plugins/fabrik_element/databasejoin/databasejoin.js'], $json);
	}

	/**
     * Function sends message texts to javascript file
     *
     * @since version 4.0
     */
    function jsScriptTranslation()
    {
        Text::script('PLG_EASY_ADMIN_ACTION_METHOD_ERROR');
        Text::script('PLG_FABRIK_LIST_EASY_ADMIN_ADD_ELEMENT');
        Text::script('PLG_FABRIK_LIST_EASY_ADMIN_EDIT_LIST');
    }

	/**
	 * Function that set up the modal to add elements
	 *
	 * @return  string  The modal
	 * 
	 * @since version 4.0
	 */
	protected function setUpModalAddElements() {
		$config['title'] = Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ADD_ELEMENT_TITLE');

		$body = $this->setUpBody('add-elements');
		$modal = $this->setUpModal($body, $config);
		return $modal;
	}

	/**
	 * Function that set up the modal
	 *
	 * @param   string $body 	Body string
	 * @param   array  $config 	Configuration array for modal.
	 *
	 * @return  string  The modal
	 * 
	 * @since version 4.0
	 */
	protected function setUpModal($body, $config) {
		$footer = $this->setUpFooter();

		$modal = HTMLHelper::_(
			'bootstrap.renderModal',
			'modal-add-elements',
			[
				'title'       	=> $config['title'],
				'backdrop'    	=> true,
				'keyboard'    	=> true,
				'focus'			=> true,
				'closeButton' 	=> true,
				'height'      	=> '400px',
				'width'       	=> '600px',
				'bodyHeight'  	=> 75,
				'modalWidth'  	=> 60,
				'footer'      	=> $footer
			],
			$body
		);

		return $modal;
	}

	/**
	 * Function that set up the footer to modal
	 *
	 * @return  string  The footer
	 * 
	 * @since version 4.0
	 */
	protected function setUpFooter() {
		$footer = '<div class="d-flex">';
		$footer .= 	'<button class="btn btn-easyadmin-modal" id="easy_modal___submit" data-dismiss="modal" aria-hidden="true" style="margin-right: 10px">' . Text::_("JAPPLY") . '</button>';
		$footer .= '</div>';

		return $footer;
	}

	/**
	 * Function that redirect to set up the body modal
	 *
	 * @param   string $type	Type of modal
	 *
	 * @return  string  The body string
	 * 
	 * @since version 4.0
	 */
	protected function setUpBody($type) {
		switch ($type) {
			case 'add-elements':
				$body = $this->setUpBodyAddElements();
				break;
		}

		return $body;
	}

	/**
	 * Function that set up the body modal to add elements
	 *
	 * @return  string  The body string
	 * 
	 * @since version 4.0
	 */
	protected function setUpBodyAddElements() {
		$layoutBody = $this->getLayout('modal-body');
		$elements = $this->getElements();
		$model = $this->getModel();
		$paramsForm = $model->getFormModel()->getParams();

		$labelPosition = $paramsForm->get('labels_above');
		$body = '';
		$data = new stdClass();

		$data->labelPosition = $labelPosition;
		foreach ($elements as $nameElement => $element) {
			$dEl = new stdClass();
			$data->label = $element['objLabel']->render((object) $element['dataLabel']);
			$data->element = $element['objField']->render($element['dataField']);
			$body .= $layoutBody->render($data);
		}

		return $body;
	}

	/**
	 * Setter method to elements variable
	 *
	 * @return  null
	 * 
	 * @since version 4.0
	 */
	public function setElements() {
		$subject = $this->getSubject();
		$elements = Array();

		$this->setElementName($elements, 'name');
		$this->setElementType($elements, 'type');
		$this->setElementDefaultValue($elements, 'defaultValue');
		$this->setElementUseFilter($elements, 'useFilter');
		$this->setElementAjaxUpload($elements, 'ajaxUpload');
		$this->setElementMakeThumbs($elements, 'makeThumbs');
		$this->setElementOptsDropdown($elements, 'optsDropdown');
		$this->setElementMultiSelect($elements, 'multiSelect');
		$this->setElementList($elements, 'list');
		$this->setElementLabel($elements, 'label');
		$this->setElementFather($elements, 'father');
		$this->setElementMultiRelations($elements, 'multiRelations');
		$this->setElementRequiered($elements, 'required');

		$this->elements = $elements;
	}

	/**
	 * Setter method to name element
	 *
	 * @param   array 	$elements		Reference to all elements
	 * @param	string	$nameElement	Identity of the element
	 *
	 * @return  null
	 * 
	 * @since 	version 4.0
	 */
	private function setElementName(&$elements, $nameElement) {
		$subject = $this->getSubject();
		$id = 'easyadmin_modal___name';
		$dEl = new stdClass;

		// Options to set up the element
		$dEl->attributes = Array(
			'type' => 'text',
			'id' => $id,
			'name' => $id,
			'size' => 0,
			'maxlength' => '255',
			'class' => 'form-control fabrikinput inputbox text',
			'value' => $value
		);

		$classField = new PlgFabrik_ElementField($subject);
		$elements[$nameElement]['objField'] = $classField->getLayout('form');
		$elements[$nameElement]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);
		$elements[$nameElement]['dataLabel'] = Array(
			'canView' => true, 
			'id' => $id,
			'canUse' => true, 
			'label' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_NAME_LABEL'),
			'hasLabel' => true,
			'view' => 'form', 
			'tipText' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_NAME_DESC'),
			'tipOpts' => ['formTip' => true, 'position' => 'top-left', 'trigger' => 'hover', 'notice' => true],
			'labelClass' => 'form-label fabrikLabel',
		);
		$elements[$nameElement]['dataField'] = $dEl;
	}

	/**
	 * Setter method to type element
	 *
	 * @param   array 	$elements		Reference to all elements
	 * @param	string	$nameElement	Identity of the element
	 *
	 * @return  null
	 * 
	 * @since version 4.0
	 */
	private function setElementType(&$elements, $nameElement) {
		$subject = $this->getSubject();
		$id = 'easyadmin_modal___type';
		$dEl = new stdClass();

		// Options to set up the element
		$opts = Array(
			'text' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TYPE_TEXT'),
			'longtext' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TYPE_LONGTEXT'),
			'file' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TYPE_FILE'),
			'date' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TYPE_DATE'),
			'dropdown' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TYPE_DROPDOWN'),
			'autocomplete' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TYPE_AUTOCOMPLETE'),
			'treeview' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TYPE_TREEVIEW'),
			'rating' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TYPE_RATING')
		);
		$dEl->options = $this->optionsElements($opts);
		$dEl->name = $id;
		$dEl->id = $id;
		$dEl->selected = Array();
		$dEl->multiple = '0';
		$dEl->attribs = 'class="fabrikinput form-select input-medium"';
		$dEl->multisize = '';

		$classDropdown = new PlgFabrik_ElementDropdown($subject);
		$elements[$nameElement]['objField'] = $classDropdown->getLayout('form');
		$elements[$nameElement]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);
		$elements[$nameElement]['dataLabel'] = Array(
			'canView' => true,
			'id' => $id,
			'canUse' => true,
			'label' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TYPE_LABEL'),
			'hasLabel' => true,
			'view' => 'form',
			'tipText' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TYPE_DESC'),
			'tipOpts' => ['formTip' => true, 'position' => 'top-left', 'trigger' => 'hover', 'notice' => true],
			'labelClass' => 'form-label fabrikLabel',
		);
		$elements[$nameElement]['dataField'] = $dEl;
	}

	/**
	 * Function that set up the options(labels and values) to elements
	 *
	 * @param	array	$opts	Options with value and label
	 * 
	 * @return  array
	 * 
	 * @since 	version 4.0
	 */
	private function optionsElements($opts) {
		$qtnTypes = count($opts);
		$x = 0;
		
		foreach ($opts as $value => $text) {
			$options[$x] = new stdClass();
			$options[$x]->value = $value;
			$options[$x]->text = $text;
			$options[$x]->disabled = false;
			$x++;
		}

		return $options;
	}

	/**
	 * Setter method to requiered element
	 *
	 * @param   array 	$elements		Reference to all elements
	 * @param	string	$nameElement	Identity of the element
	 *
	 * @return  null
	 * 
	 * @since version 4.0
	 */
	private function setElementRequiered(&$elements, $nameElement) {
		$subject = $this->getSubject();
		$id = 'easyadmin_modal___required';
		$dEl = new stdClass();

		// Options to set up the element
		$opts = Array(
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_NO'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_YES')
		);
		$elements[$nameElement]['objField'] = new FileLayout('joomla.form.field.radio.switcher');
		$elements[$nameElement]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);
		$elements[$nameElement]['dataLabel'] = Array(
			'canView' => true,
			'id' => $id,
			'canUse' => true,
			'label' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_REQUIRED_LABEL'),
			'hasLabel' => true,
			'view' => 'form',
			'tipText' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_REQUIRED_DESC'),
			'tipOpts' => ['formTip' => true, 'position' => 'top-left', 'trigger' => 'hover', 'notice' => true],
			'labelClass' => 'form-label fabrikLabel',
		);
		$elements[$nameElement]['dataField'] = Array(
			'value' => 0,
			'options' => $this->optionsElements($opts),
			'name' => $id,
			'id' => $id,
			'class' => 'fbtn-default fabrikinput',
			'dataAttribute' => 'style="margin-bottom: 0px; padding: 0px"',
		);
	}
	
	/**
	 * Setter method to default value element
	 *
	 * @param   array 	$elements		Reference to all elements
	 * @param	string	$nameElement	Identity of the element
	 *
	 * @return  null
	 * 
	 * @since version 4.0
	 */
	private function setElementDefaultValue(&$elements, $nameElement) {
		$subject = $this->getSubject();
		$id = 'easyadmin_modal___default_value';
		$dEl = new stdClass;
		$showOnTypes = ['text', 'longtext'];

		// Options to set up the element
		$dEl->attributes = Array(
			'type' => 'text',
			'id' => $id,
			'name' => $id,
			'size' => 0,
			'maxlength' => '255',
			'class' => 'form-control fabrikinput inputbox text modal-element type-' . implode(' type-', $showOnTypes),
			'value' => $value
		);

		$classField = new PlgFabrik_ElementField($subject);
		$elements[$nameElement]['objField'] = $classField->getLayout('form');
		$elements[$nameElement]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);
		$elements[$nameElement]['dataLabel'] = Array(
			'canView' => true,
			'id' => $id,
			'canUse' => true,
			'label' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_DEFAULT_VALUE_LABEL'),
			'hasLabel' => true,
			'view' => 'form',
			'tipText' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_DEFAULT_VALUE_DESC'),
			'tipOpts' => ['formTip' => true, 'position' => 'top-left', 'trigger' => 'hover', 'notice' => true],
			'labelClass' => 'form-label fabrikLabel',
		);
		$elements[$nameElement]['dataField'] = $dEl;
	}

	/**
	 * Setter method to use filter element
	 *
	 * @param   array 	$elements		Reference to all elements
	 * @param	string	$nameElement	Identity of the element
	 *
	 * @return  null
	 * 
	 * @since version 4.0
	 */
	private function setElementUseFilter(&$elements, $nameElement) {
		$subject = $this->getSubject();
		$id = 'easyadmin_modal___use_filter';
		$dEl = new stdClass();
		$showOnTypes = ['text', 'longtext', 'date', 'dropdown', 'autocomplete', 'treeview'];

		// Options to set up the element
		$opts = Array(
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_NO'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_YES')
		);
		$elements[$nameElement]['objField'] = new FileLayout('joomla.form.field.radio.switcher');
		$elements[$nameElement]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);
		$elements[$nameElement]['dataLabel'] = Array(
			'canView' => true,
			'id' => $id,
			'canUse' => true,
			'label' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_USE_FILTER_LABEL'),
			'hasLabel' => true,
			'view' => 'form',
			'tipText' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_USE_FILTER_DESC'),
			'tipOpts' => ['formTip' => true, 'position' => 'top-left', 'trigger' => 'hover', 'notice' => true],
			'labelClass' => 'form-label fabrikLabel modal-element type-' . implode(' type-', $showOnTypes),
		);
		$elements[$nameElement]['dataField'] = Array(
			'value' => 0,
			'options' => $this->optionsElements($opts),
			'name' => $id,
			'id' => $id,
			'class' => 'fbtn-default fabrikinput',
			'dataAttribute' => 'style="margin-bottom: 10px; padding: 0px"',
		);
	}

	/**
	 * Setter method to ajax upload element
	 *
	 * @param   array 	$elements		Reference to all elements
	 * @param	string	$nameElement	Identity of the element
	 *
	 * @return  null
	 * 
	 * @since version 4.0
	 */
	private function setElementAjaxUpload(&$elements, $nameElement) {
		$subject = $this->getSubject();
		$id = 'easyadmin_modal___ajax_upload';
		$dEl = new stdClass();
		$showOnTypes = ['file'];

		// Options to set up the element
		$opts = Array(
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_NO'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_YES')
		);
		$elements[$nameElement]['objField'] = new FileLayout('joomla.form.field.radio.switcher');
		$elements[$nameElement]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);
		$elements[$nameElement]['dataLabel'] = Array(
			'canView' => true,
			'id' => $id,
			'canUse' => true,
			'label' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_AJAX_ELEMENT_LABEL'),
			'hasLabel' => true,
			'view' => 'form',
			'tipText' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_AJAX_ELEMENT_DESC'),
			'tipOpts' => ['formTip' => true, 'position' => 'top-left', 'trigger' => 'hover', 'notice' => true],
			'labelClass' => 'form-label fabrikLabel modal-element type-' . implode(' type-', $showOnTypes),
		);
		$elements[$nameElement]['dataField'] = Array(
			'value' => 0,
			'options' => $this->optionsElements($opts),
			'name' => $id,
			'id' => $id,
			'class' => 'fbtn-default fabrikinput',
			'dataAttribute' => 'style="margin-bottom: 10px; padding: 0px"',
		);
	}

	/**
	 * Setter method to make thumbs element
	 *
	 * @param   array 	$elements		Reference to all elements
	 * @param	string	$nameElement	Identity of the element
	 *
	 * @return  null
	 * 
	 * @since version 4.0
	 */
	private function setElementMakeThumbs(&$elements, $nameElement) {
		$subject = $this->getSubject();
		$id = 'easyadmin_modal___make_thumbs';
		$dEl = new stdClass();
		$showOnTypes = ['file'];

		// Options to set up the element
		$opts = Array(
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_NO'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_YES')
		);
		$elements[$nameElement]['objField'] = new FileLayout('joomla.form.field.radio.switcher');
		$elements[$nameElement]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);
		$elements[$nameElement]['dataLabel'] = Array(
			'canView' => true,
			'id' => $id,
			'canUse' => true,
			'label' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_MAKE_THUMBS_LABEL'),
			'hasLabel' => true,
			'view' => 'form',
			'tipText' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_MAKE_THUMBS_DESC'),
			'tipOpts' => ['formTip' => true, 'position' => 'top-left', 'trigger' => 'hover', 'notice' => true],
			'labelClass' => 'form-label fabrikLabel modal-element type-' . implode(' type-', $showOnTypes),
		);
		$elements[$nameElement]['dataField'] = Array(
			'value' => 0,
			'options' => $this->optionsElements($opts),
			'name' => $id,
			'id' => $id,
			'class' => 'fbtn-default fabrikinput',
			'dataAttribute' => 'style="margin-bottom: 10px; padding: 0px"',
		);
	}

	/**
	 * Setter method to options drodown element
	 *
	 * @param   array 	$elements		Reference to all elements
	 * @param	string	$nameElement	Identity of the element
	 *
	 * @return  null
	 * 
	 * @since 	version 4.0
	 */
	private function setElementOptsDropdown(&$elements, $nameElement) {
		$subject = $this->getSubject();
		$id = 'easyadmin_modal___options_dropdown';
		$dEl = new stdClass;
		$showOnTypes = ['dropdown'];

		// Options to set up the element
		$dEl->attributes = Array(
			'type' => 'text',
			'id' => $id,
			'name' => $id,
			'size' => 0,
			'maxlength' => '255',
			'class' => 'form-control fabrikinput inputbox text',
			'value' => $value
		);

		$classField = new PlgFabrik_ElementField($subject);
		$elements[$nameElement]['objField'] = $classField->getLayout('form');
		$elements[$nameElement]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);
		$elements[$nameElement]['dataLabel'] = Array(
			'canView' => true, 
			'id' => $id,
			'canUse' => true, 
			'label' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_OPTIONS_DROPDOWN_LABEL'),
			'hasLabel' => true,
			'view' => 'form', 
			'tipText' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_OPTIONS_DROPDOWN_DESC'),
			'tipOpts' => ['formTip' => true, 'position' => 'top-left', 'trigger' => 'hover', 'notice' => true],
			'labelClass' => 'form-label fabrikLabel modal-element type-' . implode(' type-', $showOnTypes),
		);
		$elements[$nameElement]['dataField'] = $dEl;
	}

	/**
	 * Setter method to multi select element
	 *
	 * @param   array 	$elements		Reference to all elements
	 * @param	string	$nameElement	Identity of the element
	 *
	 * @return  null
	 * 
	 * @since version 4.0
	 */
	private function setElementMultiSelect(&$elements, $nameElement) {
		$subject = $this->getSubject();
		$id = 'easyadmin_modal___multi_select';
		$dEl = new stdClass();
		$showOnTypes = ['dropdown'];

		// Options to set up the element
		$opts = Array(
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_NO'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_YES')
		);
		$elements[$nameElement]['objField'] = new FileLayout('joomla.form.field.radio.switcher');
		$elements[$nameElement]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);
		$elements[$nameElement]['dataLabel'] = Array(
			'canView' => true,
			'id' => $id,
			'canUse' => true,
			'label' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_MULTI_SELECT_LABEL'),
			'hasLabel' => true,
			'view' => 'form',
			'tipText' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_MULTI_SELECT_DESC'),
			'tipOpts' => ['formTip' => true, 'position' => 'top-left', 'trigger' => 'hover', 'notice' => true],
			'labelClass' => 'form-label fabrikLabel modal-element type-' . implode(' type-', $showOnTypes),
		);
		$elements[$nameElement]['dataField'] = Array(
			'value' => 0,
			'options' => $this->optionsElements($opts),
			'name' => $id,
			'id' => $id,
			'class' => 'fbtn-default fabrikinput',
			'dataAttribute' => 'style="margin-bottom: 10px; padding: 0px"',
		);
	}

	/**
	 * Setter method to list element
	 *
	 * @param   array 	$elements		Reference to all elements
	 * @param	string	$nameElement	Identity of the element
	 *
	 * @return  null
	 * 
	 * @since 	version 4.0
	 */
	private function setElementList(&$elements, $nameElement) {
		$subject = $this->getSubject();
		$objDatabasejoin = new PlgFabrik_ElementDatabasejoin($subject);
		$showOnTypes = ['autocomplete', 'treeview'];

		$elContextModelElement = Array('name' => 'list');
		$elContextTableElement = Array('label' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LIST_LABEL'));
		$elContextTableJoin = Array('table_join' => '#__fabrik_lists', 'table_key' => 'id');
		$params = new Registry(json_encode(Array(
			'database_join_display_type' => 'checkbox', 
			'database_join_display_style' => 'only-autocomplete',
			'join_db_name' => '#__fabrik_lists',
			'join_val_column' => 'db_table_name',
			'join_key_column' => 'id',
			'database_join_show_please_select' => '1',
			'dbjoin_autocomplete_rows' => 10
		)));

		$objDatabasejoin->setParams($params, 0);
		$objDatabasejoin->setEditable(true);
		$objDatabasejoin->getListModel()->getTable()->bind(Array('db_table_name' => 'easyadmin_modal'));
		$objDatabasejoin->getFormModel()->getTable()->bind(Array('record_in_database' => '1'));
		$objDatabasejoin->getFormModel()->getData();
		$objDatabasejoin->getJoinModel()->getJoin()->bind($elContextTableJoin);
		$objDatabasejoin->getElement()->bind($elContextTableElement);
		$objDatabasejoin->bindToElement($elContextModelElement);		
		$objDatabasejoin->jsJLayout();
		$json = json_encode($objDatabasejoin->elementJavascript(0));

		$elements[$nameElement]['objField'] = $objDatabasejoin;
		$elements[$nameElement]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);
		$elements[$nameElement]['dataLabel'] = Array(
			'canView' => true, 
			'id' => $id,
			'canUse' => true, 
			'label' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LIST_LABEL'),
			'hasLabel' => true,
			'view' => 'form', 
			'tipText' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LIST_DESC'),
			'tipOpts' => ['formTip' => true, 'position' => 'top-left', 'trigger' => 'hover', 'notice' => true],
			'labelClass' => 'form-label fabrikLabel fabrikinput modal-element type-' . implode(' type-', $showOnTypes),
		);
		$elements[$nameElement]['dataField'] = Array();
	}

	/**
	 * Setter method to label element
	 *
	 * @param   array 	$elements		Reference to all elements
	 * @param	string	$nameElement	Identity of the element
	 *
	 * @return  null
	 * 
	 * @since version 4.0
	 */
	private function setElementLabel(&$elements, $nameElement) {
		$subject = $this->getSubject();
		$id = 'easyadmin_modal___label';
		$dEl = new stdClass();
		$showOnTypes = ['autocomplete', 'treeview'];

		// Options to set up the element
		$dEl->options = $this->optionsElements(Array());
		$dEl->name = $id;
		$dEl->id = $id;
		$dEl->selected = Array();
		$dEl->multiple = '0';
		$dEl->attribs = 'class="fabrikinput form-select input-medium child-element-list"';
		$dEl->multisize = '';

		$classDropdown = new PlgFabrik_ElementDropdown($subject);
		$elements[$nameElement]['objField'] = $classDropdown->getLayout('form');
		$elements[$nameElement]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);
		$elements[$nameElement]['dataLabel'] = Array(
			'canView' => true,
			'id' => $id,
			'canUse' => true,
			'label' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LABEL_LABEL'),
			'hasLabel' => true,
			'view' => 'form',
			'tipText' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LABEL_DESC'),
			'tipOpts' => ['formTip' => true, 'position' => 'top-left', 'trigger' => 'hover', 'notice' => true],
			'labelClass' => 'form-label fabrikLabel modal-element type-' . implode(' type-', $showOnTypes),
		);
		$elements[$nameElement]['dataField'] = $dEl;
	}

	/**
	 * Setter method to father element
	 *
	 * @param   array 	$elements		Reference to all elements
	 * @param	string	$nameElement	Identity of the element
	 *
	 * @return  null
	 * 
	 * @since version 4.0
	 */
	private function setElementFather(&$elements, $nameElement) {
		$subject = $this->getSubject();
		$id = 'easyadmin_modal___father';
		$showOnTypes = ['treeview'];
		$dEl = new stdClass();

		// Options to set up the element
		$dEl->options = $this->optionsElements(Array());
		$dEl->name = $id;
		$dEl->id = $id;
		$dEl->selected = Array();
		$dEl->multiple = '0';
		$dEl->attribs = 'class="fabrikinput form-select input-medium child-element-list"';
		$dEl->multisize = '';

		$classDropdown = new PlgFabrik_ElementDropdown($subject);
		$elements[$nameElement]['objField'] = $classDropdown->getLayout('form');
		$elements[$nameElement]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);
		$elements[$nameElement]['dataLabel'] = Array(
			'canView' => true,
			'id' => $id,
			'canUse' => true,
			'label' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_FATHER_LABEL'),
			'hasLabel' => true,
			'view' => 'form',
			'tipText' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_FATHER_DESC'),
			'tipOpts' => ['formTip' => true, 'position' => 'top-left', 'trigger' => 'hover', 'notice' => true],
			'labelClass' => 'form-label fabrikLabel modal-element type-' . implode(' type-', $showOnTypes),
		);
		$elements[$nameElement]['dataField'] = $dEl;
	}

	/**
	 * Setter method to multi relation element
	 *
	 * @param   array 	$elements		Reference to all elements
	 * @param	string	$nameElement	Identity of the element
	 *
	 * @return  null
	 * 
	 * @since version 4.0
	 */
	private function setElementMultiRelations(&$elements, $nameElement) {
		$subject = $this->getSubject();
		$id = 'easyadmin_modal___multi_relation';
		$dEl = new stdClass();
		$showOnTypes = ['autocomplete', 'treeview'];

		// Options to set up the element
		$opts = Array(
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_NO'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_YES')
		);
		$elements[$nameElement]['objField'] = new FileLayout('joomla.form.field.radio.switcher');
		$elements[$nameElement]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);
		$elements[$nameElement]['dataLabel'] = Array(
			'canView' => true,
			'id' => $id,
			'canUse' => true,
			'label' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_MULTI_RELATIONS_LABEL'),
			'hasLabel' => true,
			'view' => 'form',
			'tipText' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_MULTI_RELATIONS_DESC'),
			'tipOpts' => ['formTip' => true, 'position' => 'top-left', 'trigger' => 'hover', 'notice' => true],
			'labelClass' => 'form-label fabrikLabel modal-element type-' . implode(' type-', $showOnTypes),
		);
		$elements[$nameElement]['dataField'] = Array(
			'value' => 0,
			'options' => $this->optionsElements($opts),
			'name' => $id,
			'id' => $id,
			'class' => 'fbtn-default fabrikinput',
			'dataAttribute' => 'style="margin-bottom: 10px; padding: 0px"',
		);
	}

	/**
	 * Function that save the modal data from ajax request
	 * 
	 * @return  string
	 * 
	 * @since 	version 4.0
	 */
	public function onSaveModal() {
		$listModel = new FabrikFEModelList();
		$modelElement = new FabrikAdminModelElement();

		$model = JModelLegacy::getInstance('Element', 'FabrikAdminModel');
		$app = Factory::getApplication();
		$input = $app->input;
		$data = $listModel->removeTableNameFromSaveData($_POST);

		$opts = Array();
		$params = Array();

		$type = $data["type"];
		switch ($type) {
			case 'text':
				$opts['id'] = '';
				break;

			case 'longtext':
				break;
		}

		$modelElement->validate($model->getForm($params, false));

		echo json_encode(true);
	}

	/**
	 * Adding css style
	 *
	 * @return  null
	 * 
	 * @since 	version 4.0
	 */
	private function customizedStyle() {
		$document = Factory::getDocument();
		$css = '.select2-container--open {z-index: 9999 !important;}';
		$css .= '.btn-easyadmin-modal {min-height: 30px; width: 100%; border-radius: 12px; color: rgb(255, 255, 255); background-color: rgb(0, 62, 161);}';
		$document->addStyleDeclaration($css);
	}

	/**
	 * Getter method to elements variable
	 *
	 * @return  null
	 * 
	 * @since version 4.0
	 */
	public function getElements() {
		return $this->elements;
	}

	/**
	 * Setter method to images variable
	 *
	 * @return  null
	 * 
	 * @since version 4.0
	 */
	public function setImages() {
		$this->images['admin'] = FabrikHelperHTML::image('admin.png', 'list');
		$this->images['edit'] = FabrikHelperHTML::image('edit.png', 'list');
	}

	/**
	 * Getter method to images variable
	 *
	 * @return  null
	 * 
	 * @since version 4.0
	 */
	public function getImages() {
		return $this->images;
	}

	/**
	 * Setter method to subject variable
	 *
	 * @return  null
	 * 
	 * @since version 4.0
	 */
	public function setSubject($subject) {
		$this->subject = $subject;
	}

	/**
	 * Getter method to subject variable
	 *
	 * @return  null
	 * 
	 * @since version 4.0
	 */
	public function getSubject() {
		return $this->subject;
	}
}
