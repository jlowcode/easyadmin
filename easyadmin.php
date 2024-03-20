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
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;

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

	private $plugins = ['databasejoin', 'date', 'field', 'textarea', 'fileupload', 'dropdown', 'rating'];

	private $idModal = 'modal-elements';

	/**
	 * Constructor
	 *
	 * @param   object &$subject The object to observe
	 * @param   array  $config   An array that holds the plugin configuration
	 *
	 */
	function __construct(&$subject, $config) {
		$app = Factory::getApplication();
		$input = $app->input;
		
		//We don't have run if the task is filter
		if(strpos($input->get('task'), 'filter') > 0 || strpos($input->get('task'), 'order') > 0) {
			return;
		}

		parent::__construct($subject, $config);
		
		if($input->get('listid') && !$input->get('formid') && $input->get('view') == 'list') {
			$this->setSubject($subject);
			$this->setElements();
			$this->customizedStyle();
		}
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
		$opts->idModal = $this->idModal;

		echo $this->setUpModalElements();
		echo $this->setUpModalList();

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

	/**
	 * Function that process the data of elements to edit them
	 *
	 * @param   object		$elements 		Object of each element of the list
	 * 
	 * @return 	object
	 */
	protected function processElements($elements) {
		$processedElements = new stdClass;

		foreach($elements as $key => $element) {
			$dataEl = new stdClass();

			$fullElementName = $this->processFullElementName($key);
			$link = $this->createLink($element->element->id);
			$idElement = $element->getId();
			$enable = $this->isEnabledEdit($element->getElement()->plugin);

			$dataEl->fullname = $fullElementName;
			$dataEl->enabled = $enable;

			$this->setDataElementToEditModal($dataEl, $element, $enable);
			$processedElements->$idElement = $dataEl;
		}

		return $processedElements;
	}

	/**
	 * Function that return if the type of plugin is trated by us
	 *
	 * @param   object		$elements 		Object of each element of the list
	 * 
	 * @return 	object
	 */
	private function isEnabledEdit($type) {
		return in_array($type, $this->plugins);
	}
	
	/**
	 * Function that set the element data to each element of the list
	 *
	 * @param   object		$dataEl 		Element data object
	 * @param   object		$element 		Element object
	 * @param   boolean		$enable 		The element is trated by us or not
	 * 
	 * @return 	null
	 */
	private function setDataElementToEditModal($dataEl, $element, &$enable) {
		$dataElement = $element->getElement();
		$params = json_decode($dataElement->params, true);
		$plugin = $dataElement->plugin;

		if(!$enable) {
			return;
		}

		$dataEl->use_filter = $dataElement->filter_type ? true : false;
		$dataEl->required = !empty($element->getValidations()) ? true : false;
		$dataEl->name = $dataElement->label;

		switch ($plugin) {
			case 'field':
			case 'textarea':
				$dataEl->default_value = $dataElement->default;
				$dataEl->type = $plugin == 'field' ? 'text' : 'longtext';
			break;

			case 'fileupload':
				$dataEl->ajax_upload = $params['ajax_upload'] == '1' ? true : false;
				$dataEl->make_thumbs = $params['make_thumbnail'] == '1' ? true : false;
				$dataEl->type = 'file';
				break;

			case 'dropdown':
				$dataEl->multi_select = $params['multiple'] == '1' ? true : false;
				$dataEl->type = 'dropdown';
				$dataEl->options_dropdown = implode(', ', $params['sub_options']['sub_labels']);
				break;

			case 'date':
				$dataEl->format = $params['date_table_format'];
				$dataEl->type = 'date';
				break;

			case 'rating':
				$dataEl->access_rating = $params['rating_access'];
				$dataEl->type = 'rating';
				break;

			case 'databasejoin':
				$dataEl->list = $params['join_db_name'];

				if(!in_array($params['database_join_display_type'], ['checkbox', 'auto-complete'])) {
					$enable = false;
					return;
				}

				$dataEl->multi_relation = $params['database_join_display_type'] == 'auto-complete' ? false : true;
				$dataEl->type = $params['database_join_display_style'] == 'only-autocomplete' ? 'autocomplete' : 'treeview';
				$dataEl->label =  $params['join_val_column'];
				$dataEl->father = $params['tree_parent_id'];
				break;
		}
	}


	/**
	 * Function that process the name of elements to edit them
	 *
	 * @param   object		$elements 		Object of each element of the list
	 * 
	 * @return 	object
	 */
	protected function processElementsNames($elements) {
		$processedElements = new stdClass;

		foreach($elements as $key => $element) {
			$idElement = $element->getId();
			$processedElements->$idElement = $element->element->label;	
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
		$app = Factory::getApplication();
		$input = $app->input;
		
		//We don't have run if the task is filter
		if(strpos($input->get('task'), 'filter') > 0 || strpos($input->get('task'), 'order') > 0) {
			return;
		}

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
		$app = Factory::getApplication();
		$input = $app->input;
		
		//We don't have run if the task is filter
		if(strpos($input->get('task'), 'filter') > 0 || strpos($input->get('task'), 'order') > 0) {
			return;
		}

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
        Text::script('PLG_FABRIK_LIST_EASY_ADMIN_ADMIN');
        Text::script('PLG_EASY_ADMIN_ACTION_METHOD_ERROR');
        Text::script('PLG_FABRIK_LIST_EASY_ADMIN_ADD_ELEMENT');
        Text::script('PLG_FABRIK_LIST_EASY_ADMIN_EDIT_LIST');
        Text::script('PLG_EASY_ADMIN_ELEMENT_SUCCESS');
        Text::script('PLG_EASY_ADMIN_ELEMENT_ERROR');
        Text::script('PLG_EASY_ADMIN_ERROR_VALIDATE');
    }

	/**
	 * Function that set up the modal to elements
	 *
	 * @return  string  The modal
	 * 
	 * @since version 4.0
	 */
	private function setUpModalElements() {
		$config['title'] = Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TITLE');

		$body = $this->setUpBody('elements');
		$modal = $this->setUpModal($body, $config);
		return $modal;
	}

	/**
	 * Function that set up the modal to list
	 *
	 * @return  string  The modal
	 * 
	 * @since version 4.0
	 */
	private function setUpModalList() {
		$config['title'] = Text::_('PLG_FABRIK_LIST_EASY_ADMIN_LIST_TITLE');

		$body = $this->setUpBody('list');
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
	private function setUpModal($body, $config) {
		$footer = $this->setUpFooter();

		$modal = HTMLHelper::_(
			'bootstrap.renderModal',
			$this->idModal,
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
	private function setUpFooter() {
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
	private function setUpBody($type) {
		switch ($type) {
			case 'elements':
				$body = $this->setUpBodyElements();
				break;
			case 'list':
				$body = $this->setUpBodyList();
				break;
		}

		return $body;
	}

	/**
	 * Function that set up the body modal to elements
	 *
	 * @return  string  The body string
	 * 
	 * @since version 4.0
	 */
	private function setUpBodyElements() {
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
		$this->setElementAjaxUpload($elements, 'ajaxUpload');
		$this->setElementMakeThumbs($elements, 'makeThumbs');
		$this->setElementFormat($elements, 'format');
		$this->setElementOptsDropdown($elements, 'optsDropdown');
		$this->setElementMultiSelect($elements, 'multiSelect');
		$this->setElementList($elements, 'list');
		$this->setElementLabel($elements, 'label');
		$this->setElementFather($elements, 'father');
		$this->setElementMultiRelations($elements, 'multiRelations');
		$this->setElementAccessRating($elements, 'accessRating');
		$this->setElementUseFilter($elements, 'useFilter');
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

		$elements[$nameElement]['dataLabel'] = $this->getDataLabel($id, 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_NAME_LABEL'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_NAME_DESC'), 
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

		$elements[$nameElement]['dataLabel'] = $this->getDataLabel($id, 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TYPE_LABEL'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TYPE_DESC'), 
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

		$elements[$nameElement]['dataLabel'] = $this->getDataLabel($id, 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_REQUIRED_LABEL'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_REQUIRED_DESC'), 
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
			'class' => 'form-control fabrikinput inputbox text',
			'value' => $value
		);

		$classField = new PlgFabrik_ElementField($subject);
		$elements[$nameElement]['objField'] = $classField->getLayout('form');
		$elements[$nameElement]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$nameElement]['dataLabel'] = $this->getDataLabel($id, 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_DEFAULT_VALUE_LABEL'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_DEFAULT_VALUE_DESC'), 
			$showOnTypes, 
			false
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
		$showOnTypes = ['text', 'longtext', 'date', 'dropdown', 'autocomplete', 'treeview', 'date', 'rating'];

		// Options to set up the element
		$opts = Array(
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_NO'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_YES')
		);
		$elements[$nameElement]['objField'] = new FileLayout('joomla.form.field.radio.switcher');
		$elements[$nameElement]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$nameElement]['dataLabel'] = $this->getDataLabel($id, 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_USE_FILTER_LABEL'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_USE_FILTER_DESC'), 
			$showOnTypes, 
			false
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

		$elements[$nameElement]['dataLabel'] = $this->getDataLabel($id, 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_AJAX_ELEMENT_LABEL'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_AJAX_ELEMENT_DESC'), 
			$showOnTypes, 
			false
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

		$elements[$nameElement]['dataLabel'] = $this->getDataLabel($id, 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_MAKE_THUMBS_LABEL'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_MAKE_THUMBS_DESC'), 
			$showOnTypes, 
			false
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
	 * Setter method to format element
	 *
	 * @param   array 	$elements		Reference to all elements
	 * @param	string	$nameElement	Identity of the element
	 *
	 * @return  null
	 * 
	 * @since version 4.0
	 */
	private function setElementFormat(&$elements, $nameElement) {
		$subject = $this->getSubject();
		$id = 'easyadmin_modal___format';
		$dEl = new stdClass();
		$showOnTypes = ['date'];

		// Options to set up the element
		$opts = Array(
			'd/m/Y' => 'DD/MM/AAAA',
			'm/d/Y' => 'MM/DD/AAAA',
			'Y/m/d' => 'AAAA/MM/DD',
			'd/m/Y h:i:s' => 'DD/MM/AAAA hh:mm:ss',
			'm/d/Y h:i:s' => 'MM/DD/AAAA hh:mm:ss',
			'Y/m/d h:i:s' => 'AAAA/MM/DD hh:mm:ss',
			'd-m-Y' => 'DD-MM-AAAA',
			'm-d-Y' => 'MM-DD-AAAA',
			'Y-m-d' => 'AAAA-MM-DD',
			'd-m-Y h:i:s' => 'DD-MM-AAAA hh:mm:ss',
			'm-d-Y h:i:s' => 'MM-DD-AAAA hh:mm:ss',
			'Y-m-d h:i:s' => 'AAAA-MM-DD hh:mm:ss',
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

		$elements[$nameElement]['dataLabel'] = $this->getDataLabel($id,
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_FORMAT_LABEL'),
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_FORMAT_DESC'),
			$showOnTypes,
			false
		);
		$elements[$nameElement]['dataField'] = $dEl;
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

		$elements[$nameElement]['dataLabel'] = $this->getDataLabel($id, 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_OPTIONS_DROPDOWN_LABEL'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_OPTIONS_DROPDOWN_DESC'), 
			$showOnTypes, 
			false
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
		
		$elements[$nameElement]['dataLabel'] = $this->getDataLabel($id, 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_MULTI_SELECT_LABEL'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_MULTI_SELECT_DESC'), 
			$showOnTypes, 
			false
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
			'join_key_column' => 'db_table_name',
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

		$elements[$nameElement]['dataLabel'] = $this->getDataLabel($id, 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LIST_LABEL'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LIST_DESC'), 
			$showOnTypes,
			false
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

		$elements[$nameElement]['dataLabel'] = $this->getDataLabel($id,
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LABEL_LABEL'),
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LABEL_DESC'),
			$showOnTypes,
			false
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
		
		$elements[$nameElement]['dataLabel'] = $this->getDataLabel($id, 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_FATHER_LABEL'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_FATHER_DESC'), 
			$showOnTypes, 
			false
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

		$elements[$nameElement]['dataLabel'] = $this->getDataLabel($id, 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_MULTI_RELATIONS_LABEL'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_MULTI_RELATIONS_DESC'), 
			$showOnTypes, 
			false
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
	 * Setter method to access rating element
	 *
	 * @param   array 	$elements		Reference to all elements
	 * @param	string	$nameElement	Identity of the element
	 *
	 * @return  null
	 * 
	 * @since version 4.0
	 */
	private function setElementAccessRating(&$elements, $nameElement) {
		$subject = $this->getSubject();
		$id = 'easyadmin_modal___access_rating';
		$dEl = new stdClass();
		$showOnTypes = ['rating'];

		// Options to set up the element
		$opts = $this->getViewLevels();
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

		$elements[$nameElement]['dataLabel'] = $this->getDataLabel($id,
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_ACCESS_RATING_LABEL'),
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_ACCESS_RATING_DESC'),
			$showOnTypes,
			false
		);
		$elements[$nameElement]['dataField'] = $dEl;
	}

	/**
     * Get the list of all view levels
     *
     * @return  \stdClass[]|boolean  An array of all view levels (id, title).
     *
     * @since   3.4
     */
    public function getViewLevels()
    {
        $db    = JFactory::getDbo();
        $query = $db->getQuery(true);

        // Get all the available view levels
        $query->select($db->quoteName('id'))
            ->select($db->quoteName('title'))
            ->from($db->quoteName('#__viewlevels'))
            ->order($db->quoteName('id'));

        $db->setQuery($query);
        $result = $db->loadObjectList();
		
		$levels = Array();
		foreach ($result as $val) {
			$levels[$val->id] = $val->title;
		}

        return $levels;
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

		$listModel = JModelLegacy::getInstance('List', 'FabrikFEModel');
		$model = JModelLegacy::getInstance('Element', 'FabrikAdminModel');
		
		$listId = $_POST['easyadmin_modal___listid'];
		$listModel->setId($listId);
		$app = Factory::getApplication();

		$input = $app->input;
		$data = $listModel->removeTableNameFromSaveData($_POST);
		$group_id = $listModel->getFormModel()->getGroups()[$listId]->getGroup()->getId();

		$validate = $this->validate($data);
		if($validate->error) {
			echo json_encode($validate);
			return;
		}

		$opts = Array();
		$params = Array();
		$validation = Array();

		$opts['easyadmin'] = true;
		$opts['asset_id'] = '';
		$opts['id'] = $data['valIdEl'];
		$opts['label'] = $data['name'];
		$opts['name'] = $opts['id'] == '0' ? strtolower($data['name']) : '';
		$opts['group_id'] = $group_id;
		$opts['published'] = '1';
		$opts['access'] = '1';
		$opts['modelElement'] = $modelElement;
		
		$type = $data["type"];
		switch ($type) {
			case 'text':
			case 'longtext':
				$params['maxlength'] = 255;

				$opts['hidden'] = '0';
				$opts['default'] = $data['default_value'];
				$opts['plugin'] = 'field';

				if($type == 'longtext') {
					$opts['plugin'] = 'textarea';
					$params['bootstrap_class'] = 'col-sm-12';
				}

				if($data['use_filter']) {
					$opts['filter_type'] = 'auto-complete';
				}

				break;

			case 'file':
				$opts['plugin'] = 'fileupload';
				$params['ajax_upload'] = $data['ajax_upload'] ? '1' : '0';
				$params['ul_max_file_size'] = '1048576';
				$params['ul_directory'] = "images/stories/";
				$params['image_library'] = 'gd2';
				$params['fileupload_crop_dir'] = "images/stories/crop";
				$params['ul_max_file_size'] = '1048576';
				$params['ul_max_file_size'] = '1048576';
				$params['ul_file_increment'] = '1';

				if($data['make_thumbs']) {
					$params['make_thumbnail'] = '1';
					$params['fu_make_pdf_thumb'] = '1';
					$params['thumb_dir'] = "images/stories/thumbs";
				}

				if($data['use_filter']) {
					$opts['filter_type'] = 'auto-complete';
				}

				break;

			case 'dropdown':
				$opts['plugin'] = 'dropdown';
				$params['multiple'] = $data['multi_select'] ? '1' : '0';

				$sub_options = explode(',', $data['options_dropdown']);
				$params['sub_options'] = Array(
					'sub_values' => $sub_options,
					'sub_labels' => array_map(function($opt) {return preg_replace('/[^A-Za-z0-9]/', '_', $opt);}, $sub_options),
					'sub_initial_selection' => Array($sub_options[0])
				);

				if($data['use_filter']) {
					$opts['filter_type'] = 'dropdown';
				}

				break;

			case 'date':
				$opts['plugin'] = 'date';
				$params['date_table_format'] = $data['format'];
				$params['date_form_format'] = $data['format'];

				if($data['use_filter']) {
					$opts['filter_type'] = 'range';
				}

				break;

			case 'rating':
				$opts['plugin'] = 'rating';
				$params['rating_access'] = $data['access_rating'];
				$params['rating-mode'] = 'creator-rating';
				$params['rating-nonefirst'] = '1';
				$params['rating-rate-in-form'] = '1';
				$params['rating_float'] = '0';

				if($data['use_filter']) {
					$opts['filter_type'] = 'stars';
				}

				break;

			case 'autocomplete':
			case 'treeview':
				$opts['plugin'] = 'databasejoin';
				$params['join_conn_id'] = '1';
				$params['join_db_name'] = $data['list'];
				$params['join_key_column'] = 'id';
				$params['join_val_column'] =  $data['label'];
				$params['database_join_show_please_select'] =  '1';
				$params['dbjoin_autocomplete_rows'] =  10;

				$params['database_join_display_type'] = $data['multi_relation'] ? 'checkbox' : 'auto-complete';

				if($data['use_filter']) {
					$opts['filter_type'] = 'auto-complete';
				}

				if($type == 'autocomplete') {
					$params['database_join_display_style'] =  'only-autocomplete';
				} else {
					$params['database_join_display_style'] =  'only-treeview';
					$params['tree_parent_id'] =  $data['father'];
				}

				break;
		}

		if($data['use_filter']) {
			$opts['filter_exact_match'] = '0';
			$params['filter_access'] = '1';
			$params['filter_length'] = '20';
			$params['filter_required'] = '0';
			$params['filter_build_method'] = '0';
			$params['filter_groupby'] = 'text';
			$params['filter_class'] = 'input-xxlarge';
			$params['filter_responsive_class'] = '';
		} else {
			$opts['filter_type'] = '';
		}

		if($data['required']) {
			$validation['plugin'] = ['notempty'];
			$validation['plugin_published'] = ['1'];
			$validation['validate_in'] = ['both'];
			$validation['validate_on'] = ['both'];
			$validation['validate_hidden'] = ['0'];
			$validation['must_validate'] = ['1'];
			$validation['show_icon'] = ['1'];
			$opts['validationrule'] = $validation;
		}

		$opts['params'] = $params;

		if($opts['id'] != '0') {
			$this->syncParams($opts, $listModel);
		}

		if(!$validate->error) {
			$modelElement->save($opts);
		}

		echo json_encode($validate);
	}

	/**
	 * We need update the params that already exists
	 *
	 * @param   array 	$opts			Options and params
	 * @param   object 	$listModel		Object of list
	 *
	 * @return  null
	 * 
	 * @since 	version 4.0
	 */
	private function syncParams(&$opts, $listModel) {
		$idEl = $opts['id'];
		$element = $listModel->getElements('id')[$idEl];
		$dataEl = $element->element->getProperties();

		foreach ($dataEl as $key => $val) {
			if(!array_key_exists($key, $opts)) {
				$opts[$key] = $dataEl[$key];
			} else {
				$sub = $opts[$key];
				
				switch ($key) {
					case 'params':
						$oldParams = json_decode($dataEl[$key], true);
						$newParams = $opts[$key];
						$sub = array_merge($oldParams, $newParams);
						break;
					
					case 'name':
						$opts[$key] == '' ? $sub = $dataEl[$key] : null;
						break;
				}

				$opts[$key] = $sub;
			}
		}

		/** FALTA fazer com que as validações sejam adicionadas e não sobrescritas */
		foreach ($element->getValidations() as $key => $validation) {
		 	$a = $opts['validationrule'];
		}
	}

	/**
	 * We need update the id saved from input to create the elements correctly
	 *
	 * @param   array 	$data		Source of options
	 *
	 * @return  object
	 * 
	 * @since 	version 4.0
	 */
	private function validate($data) {
		$validate = new stdClass();
		$validate->error = false;
		$validate->message = "";

		// If the element is auto-complete, label must be exists
		if($data['type'] == 'autocomplete' && empty($data['label'])) {
			$validate->error = true;
			empty($data['label']) ? $validate->message = Text::sprintf('PLG_FABRIK_LIST_EASY_ADMIN_ERROR_ELEMENT_EMPTY', Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LABEL_LABEL')) : '';
			empty($data['list']) ? $validate->message = Text::sprintf('PLG_FABRIK_LIST_EASY_ADMIN_ERROR_ELEMENT_EMPTY', Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LIST_LABEL')) : '';
		}

		// If the element is treeview, label and father must be exists
		if($data['type'] == 'treeview' && (empty($data['label']) || empty($data['father']))) {
			$validate->error = true;
			empty($data['father']) ? $validate->message = Text::sprintf('PLG_FABRIK_LIST_EASY_ADMIN_ERROR_ELEMENT_EMPTY', Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_FATHER_LABEL')) : '';
			empty($data['label']) ? $validate->message = Text::sprintf('PLG_FABRIK_LIST_EASY_ADMIN_ERROR_ELEMENT_EMPTY', Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LABEL_LABEL')) : '';
			empty($data['list']) ? $validate->message = Text::sprintf('PLG_FABRIK_LIST_EASY_ADMIN_ERROR_ELEMENT_EMPTY', Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LIST_LABEL')) : '';
		}

		// If the element is drodown, options must be exists
		if($data['type'] == 'dropdown' && empty($data['options_dropdown'])) {
			$validate->error = true;
			$validate->message = Text::sprintf('PLG_FABRIK_LIST_EASY_ADMIN_ERROR_ELEMENT_EMPTY', Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_OPTIONS_DROPDOWN_LABEL'));
		}

		return $validate;
	}

	/**
	 * We need update the id saved from input to create the elements correctly
	 *
	 * @return  null
	 * 
	 * @since 	version 4.0
	 */
	public function onContentAfterSave($context, $item, $isNew, $data = []) {
		$app = Factory::getApplication();
		$input = $app->input;
		$id = $item->id;
		
		//We don't have run if the task is filter
		if(strpos($input->get('task'), 'filter') > 0 || strpos($input->get('task'), 'order') > 0) {
			return;
		}

		if (!is_array($data) || empty($item->id) || !$data['easyadmin'] || $context != 'com_fabrik.element') {
            return;
        }

		JFactory::getApplication()->getInput()->set("id", $id);
		$data['modelElement']->setState("element.id", $id);
		$data['modelElement']->getState("element.id");
	}

	/**
	 * Getting the array of data to construct the elements label
	 *
	 * @param	String	$id					Identity of the element
	 * @param	String	$label				Label of the element
	 * @param	String	$tip				Tip of the element
	 * @param   Array 	$showOnTypes		When each element must show on each type of elements (Used in js)
	 * @param	Boolean	$fixed				If the element is fixed always or must show and hide depending of the types above
	 *
	 * @return  Array
	 * 
	 * @since version 4.0
	 */
	private function getDataLabel($id, $label, $tip, $showOnTypes='', $fixed=true) {
		$class = $fixed ?  '' : "modal-element type-" . implode(' type-', $showOnTypes);

		$data = Array(
			'canView' => true,
			'id' => $id,
			'canUse' => true,
			'label' => $label,
			'hasLabel' => true,
			'view' => 'form',
			'tipText' => $tip,
			'tipOpts' => ['formTip' => true, 'position' => 'top-left', 'trigger' => 'hover', 'notice' => true],
			'labelClass' =>  "form-label fabrikLabel {$class}",
		);

		return $data;
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
