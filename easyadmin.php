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
use Joomla\CMS\Form\Field\FolderlistField;
use Joomla\CMS\Filesystem\Path;
use Joomla\CMS\Filesystem\Folder;

// Requires 
// Change to namespaces on F5
require_once COM_FABRIK_FRONTEND . '/models/plugin-list.php';
require_once JPATH_PLUGINS . '/fabrik_element/field/field.php';
require_once JPATH_PLUGINS . '/fabrik_element/dropdown/dropdown.php';
require_once JPATH_PLUGINS . '/fabrik_element/databasejoin/databasejoin.php';
require_once JPATH_BASE . '/components/com_fabrik/models/element.php';
require_once JPATH_BASE . '/components/com_fabrik/models/list.php';
require_once JPATH_BASE . '/components/com_fabrik/models/form.php';
require_once JPATH_ADMINISTRATOR . '/components/com_fabrik/models/element.php';
require_once JPATH_ADMINISTRATOR . '/components/com_fabrik/models/list.php';
require_once JPATH_ADMINISTRATOR . '/components/com_fabrik/models/form.php';

/**
 *  Buttons to edit list and create elements on site Front-End
 *
 * @package     Joomla.Plugin
 * @subpackage  Fabrik.list.js
 */
class PlgFabrik_ListEasyAdmin extends PlgFabrik_List {
	private $images;
	
	private $listId;

	private $listModel;
	
	private $elements;

	private $elementsList;
	
	private $subject;

	private $plugins = ['databasejoin', 'date', 'field', 'textarea', 'fileupload', 'dropdown', 'rating', 'thumbs'];

	private $idModal = 'modal-elements';

	private $idModalList = 'modal-list';

	/**
	 * Constructor
	 *
	 * @param   object &$subject The object to observe
	 * @param   array  $config   An array that holds the plugin configuration
	 *
	 */
	public function __construct(&$subject, $config) {
		$app = Factory::getApplication();
		$input = $app->input;
		
		$this->setListId($input->get('listid'));
		
		//We don't have run if the task is filter
		if(strpos($input->get('task'), 'filter') > 0 || strpos($input->get('task'), 'order') > 0 || $input->get('format') == 'csv') {
			return;
		}

		parent::__construct($subject, $config);
		
		if($this->getListId() && !$input->get('formid') && $input->get('view') == 'list') {
			$listModel = new FabrikFEModelList();
			$listModel = JModelLegacy::getInstance('List', 'FabrikFEModel');
			$listModel->setId($this->listId);
			
			$this->db_table_name = $listModel->getTable()->db_table_name;

			$this->setListModel($listModel);
			$this->setSubject($subject);
			$this->setElements();
			$this->setElementsList();
			$this->customizedStyle();
		}
	}

	/**
	 * Init function
	 *
	 * @return  null
	 */
	protected function init() {
		if(!$this->authorized()) {
			return;
		}

		$this->jsScriptTranslation();

		$opts = new StdClass;
		$opts->baseUri = JURI::base();
		$opts->allElements = $this->processElements($this->model->getElements(true, true, false));
		$opts->elements = $this->processElements($this->model->getElements(true, true, false), true);
		$opts->elementsNames = $this->processElementsNames($this->model->getElements(true, true, false));
		$opts->listUrl = $this->createListLink($this->getModel()->getId());
		$opts->actionMethod = $this->model->actionMethod();
		$opts->images = $this->getImages();
		$opts->idModal = $this->idModal;
		$opts->idModalList = $this->idModalList;

		echo $this->setUpModalElements();
		echo $this->setUpModalList();

		// Load the JS code and pass the opts
		$this->loadJS($opts);
	}

	/**
	 * Function to check if the user is authorized
	 *
	 * @return  Boolean
	 * 
	 * @since version 4.0.2
	 */
	private function authorized() {
		$user = Factory::getUser();
		$db = Factory::getDbo();
		$listModel = $this->getListModel();

		$groupsLevels = $user->groups;
		$levelEditList = (int) $listModel->getParams()->get("allow_edit_details");
		$query = $db->getQuery(true);

		$query->select($db->qn("rules"))
			->from($db->qn("#__viewlevels"))
			->where($db->qn("id") . " = " . $db->q($levelEditList));
		$db->setQuery($query);
		$groups = json_decode($db->loadResult());

		return count(array_intersect($groupsLevels, $groups)) > 0 ? true : false;
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
		$this->opts = $optsJson;
		FabrikHelperHTML::script($jsFiles, $script);
	}

	/**
	 * Function that process the data of elements to edit them
	 *
	 * @param   object		$elements 		Object of each element of the list
	 * 
	 * @return 	object
	 */
	protected function processElements($elements, $div=false) {
		$processedElements = new stdClass;
		$processedElements->published = new stdClass;
		$processedElements->trash = new stdClass;

		foreach($elements as $key => $element) {
			$dataEl = new stdClass();

			$fullElementName = $this->processFullElementName($key);
			$link = $this->createLink($element->element->id);
			$idElement = $element->getId();
			$enable = $this->isEnabledEdit($element->getElement()->plugin);

			$dataEl->fullname = $fullElementName;
			$dataEl->enabled = $enable;

			$this->setDataElementToEditModal($dataEl, $element, $enable);

			if($div) {
				$dataEl->trash ? $processedElements->trash->$idElement = $dataEl : $processedElements->published->$idElement = $dataEl;
			} else {
				$processedElements->$idElement = $dataEl;
			}
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

		preg_match('/(\d+)/', $params["tablecss_cell"], $matches);

		$dataEl->use_filter = $dataElement->filter_type ? true : false;
		$dataEl->required = !empty($element->getValidations()) ? true : false;
		$dataEl->show_in_list = $dataElement->show_in_list_summary ? true : false;
		$dataEl->width_field = $matches[0];
		$dataEl->name = $dataElement->label;
		$dataEl->ordering_elements = $dataElement->show_in_list_summary ? $element->getId() : '-2';
		$dataEl->trash = $dataElement->published == 1 ? false : true;

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

			case 'thumbs':
				$dataEl->type = 'thumbs';
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
		if(strpos($input->get('task'), 'filter') > 0 || strpos($input->get('task'), 'order') > 0 || $input->get('format') == 'csv') {
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
		if((strpos($input->get('task'), 'filter') > 0 || strpos($input->get('task'), 'order') > 0) || $this->elements['list']['objField'] === null || $input->get('format') == 'csv') {
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
        Text::script('PLG_FABRIK_LIST_EASY_ADMIN_ACTION_METHOD_ERROR');
        Text::script('PLG_FABRIK_LIST_EASY_ADMIN_ADD_ELEMENT');
        Text::script('PLG_FABRIK_LIST_EASY_ADMIN_EDIT_LIST');
        Text::script('PLG_FABRIK_LIST_EASY_ADMIN_SUCCESS');
        Text::script('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_ERROR');
        Text::script('PLG_FABRIK_LIST_EASY_ADMIN_ERROR_VALIDATE');
        Text::script('PLG_FABRIK_LIST_EASY_ADMIN_TRASH');
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
		$modal = $this->setUpModal($body, $config, 'elements');

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
		$modal = $this->setUpModal($body, $config, 'list');

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
	private function setUpModal($body, $config, $type) {
		$footer = $this->setUpFooter($type);

		switch ($type) {
			case 'elements':
				$id = $this->idModal;
				break;
			
			case 'list':
				$id = $this->idModalList;
				break;
		}

		$modal = HTMLHelper::_(
			'bootstrap.renderModal',
			$id,
			[
				'title'       	=> $config['title'],
				'backdrop'    	=> true,
				'keyboard'    	=> true,	
				'focus'			=> true,
				'closeButton' 	=> true,
				'height'      	=> '400px',
				'width'       	=> '600px',
				'bodyHeight'  	=> 70,
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
	 * @param	string	Mode
	 * 
	 * @return  string  The footer
	 * 
	 * @since version 4.0
	 */
	private function setUpFooter($type) {
		$footer = '<div class="d-flex">';
		$footer .= 	'<button class="btn btn-easyadmin-modal" id="easyadmin_modal___submit_' . $type . '" data-dismiss="modal" aria-hidden="true" style="margin-right: 10px">' . Text::_("JAPPLY") . '</button>';
		
		if($type == 'list') {
			$footer .=  '<input type="hidden" id="easyadmin_modal___db_table_name" name="db_table_name" value="' . $this->db_table_name . '">';
		}

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
		$elements = $this->getElements(true, true, false);
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
	 * Function that set up the body modal to elements
	 *
	 * @return  string  The body string
	 * 
	 * @since version 4.0
	 */
	private function setUpBodyList() {
		$layoutBody = $this->getLayout('modal-body');
		$elements = $this->getElementsList();
		$model = $this->getModel();
		$paramsForm = $model->getFormModel()->getParams();

		$labelPosition = $paramsForm->get('labels_above');
		$body = '';
		$data = new stdClass();

		$data->labelPosition = $labelPosition;
		foreach ($elements as $nameElement => $element) {
			$dEl = new stdClass();
			!empty($element['dataLabel']) ? $data->label = $element['objLabel']->render((object) $element['dataLabel']) : $data->label = '';

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
		$this->setElementShowInList($elements, 'showInList');
		$this->setElementOrderingElements($elements, 'OrderingElements');
		$this->setElementWidthField($elements, 'widthField');
		$this->setElementRequired($elements, 'required');
		$this->setElementTrash($elements, 'trash');

		$this->elements = $elements;
	}

	/**
	 * Setter method to elements list variable
	 *
	 * @return  null
	 * 
	 * @since version 4.0
	 */
	public function setElementsList() {
		$subject = $this->getSubject();
		$elementsList = Array();

		$this->setElementNameList($elementsList, 'nameList');
		$this->setElementDescriptionList($elementsList, 'descriptionList');
		//$this->setElementThumbList($elementsList, 'thumbList');	// For new version
		$this->setElementOrderingList($elementsList, 'orderingList');
		$this->setElementOrderingTypeList($elementsList, 'orderingTypeList');
		$this->setElementCollab($elementsList, 'collabList');
		//$this->setElementDefaultLayout($elementsList, 'defaultLayout');

		$this->elementsList = $elementsList;
	}

	/**
	 * Setter method to list name element
	 *
	 * @param   array 	$elements		Reference to all elements
	 * @param	string	$nameElement	Identity of the element
	 *
	 * @return  null
	 * 
	 * @since 	version 4.0
	 */
	private function setElementNameList(&$elements, $nameElement) {
		$listModel = $this->getListModel();
		$subject = $this->getSubject();

		$tableList = $listModel->getTable();
		$val = $tableList->get('label');

		$id = 'easyadmin_modal___name_list';
		$dEl = new stdClass;

		// Options to set up the element
		$dEl->attributes = Array(
			'type' => 'text',
			'id' => $id,
			'name' => $id,
			'size' => 0,
			'maxlength' => '255',
			'class' => 'form-control fabrikinput inputbox text',
			'value' => $val
		);

		$classField = new PlgFabrik_ElementField($subject);
		$elements[$nameElement]['objField'] = $classField->getLayout('form');
		$elements[$nameElement]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$nameElement]['dataLabel'] = $this->getDataLabel(
			$id, 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LIST_NAME_LABEL'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LIST_NAME_DESC'), 
		);
		$elements[$nameElement]['dataField'] = $dEl;
	}

	/**
	 * Setter method to list description element
	 *
	 * @param   array 	$elements		Reference to all elements
	 * @param	string	$nameElement	Identity of the element
	 *
	 * @return  null
	 * 
	 * @since 	version 4.0
	 */
	private function setElementDescriptionList(&$elements, $nameElement) {
		$listModel = $this->getListModel();
		$subject = $this->getSubject();

		$tableList = $listModel->getTable();
		$val = $tableList->get('introduction');

		$id = 'easyadmin_modal___description_list';
		$dEl = new stdClass;

		// Options to set up the element
		$dEl->attributes = Array(
			'type' => 'text',
			'id' => $id,
			'name' => $id,
			'size' => 0,
			'maxlength' => '255',
			'class' => 'form-control fabrikinput inputbox text',
			'value' => $val
		);

		$classField = new PlgFabrik_ElementField($subject);
		$elements[$nameElement]['objField'] = $classField->getLayout('form');
		$elements[$nameElement]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$nameElement]['dataLabel'] = $this->getDataLabel(
			$id,
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LIST_DESCRIPTION_LABEL'),
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LIST_DESCRIPTION_DESC'),
		);
		$elements[$nameElement]['dataField'] = $dEl;
	}

	/**
	 * Setter method to ordering element
	 *
	 * @param   array 	$elements		Reference to all elements
	 * @param	string	$nameElement	Identity of the element
	 *
	 * @return  null
	 * 
	 * @since version 4.0
	 */
	private function setElementOrderingList(&$elements, $nameElement) {
		$listModel = $this->getListModel();
		$formModel = $listModel->getFormModel();
		$subject = $this->getSubject();

		$id = 'easyadmin_modal___ordering_list';
		$dEl = new stdClass();

		// Options to set up the element
		$selected = $listModel->getOrderBys();
		$dEl->options = $formModel->getElementOptions(false, 'id', true, false);
		$dEl->name = $id;
		$dEl->id = $id;
		$dEl->selected = $selected;
		$dEl->multiple = '0';
		$dEl->attribs = 'class="fabrikinput form-select input-medium child-element-list"';
		$dEl->multisize = '';

		$classDropdown = new PlgFabrik_ElementDropdown($subject);
		$elements[$nameElement]['objField'] = $classDropdown->getLayout('form');
		$elements[$nameElement]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$nameElement]['dataLabel'] = $this->getDataLabel(
			$id,
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_ORDERING_LABEL'),
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_ORDERING_DESC'),
		);
		$elements[$nameElement]['dataField'] = $dEl;
	}

	/**
	 * Setter method to type ordering element
	 *
	 * @param   array 	$elements		Reference to all elements
	 * @param	string	$nameElement	Identity of the element
	 *
	 * @return  null
	 * 
	 * @since version 4.0
	 */
	private function setElementOrderingTypeList(&$elements, $nameElement) {
		$listModel = $this->getListModel();
		$formModel = $listModel->getFormModel();
		$subject = $this->getSubject();

		$tableList = $listModel->getTable();
		$val = json_decode($tableList->get('order_dir'), true);

		$id = 'easyadmin_modal___ordering_type_list';
		$dEl = new stdClass();

		// Options to set up the element
		$dEl->options = $this->optionsElements(Array('ASC' => 'Crescente', 'DESC' => 'Decrescente'));
		$dEl->name = $id;
		$dEl->id = $id;
		$dEl->selected = $val;
		$dEl->multiple = '0';
		$dEl->attribs = 'class="fabrikinput form-select input-medium child-element-list"';
		$dEl->multisize = '';

		$classDropdown = new PlgFabrik_ElementDropdown($subject);
		$elements[$nameElement]['objField'] = $classDropdown->getLayout('form');
		$elements[$nameElement]['dataField'] = $dEl;
	}

	/**
	 * Setter method to collaboration element
	 *
	 * @param   array 	$elements		Reference to all elements
	 * @param	string	$nameElement	Identity of the element
	 *
	 * @return  null
	 * 
	 * @since version 4.0
	 */
	private function setElementCollab(&$elements, $nameElement) {
		$listModel = $this->getListModel();
		$formModel = $listModel->getFormModel();
		$subject = $this->getSubject();

		$tableList = $listModel->getTable();
		$val = Array($formModel->getParams()->get('approve_for_own_records'));

		$id = 'easyadmin_modal___collab_list';
		$dEl = new stdClass();

		// Options to set up the element
		$dEl->options = $this->optionsElements(Array(
			'0' => 'Restrita, todas as contribuições devem ser revisadas e precisam de aprovação',
			'1' => 'Aberta, apenas as contribuições de edição e exclusão precisam de aprovação'
		));
		$dEl->name = $id;
		$dEl->id = $id;
		$dEl->selected = $val;
		$dEl->multiple = '0';
		$dEl->attribs = 'class="fabrikinput form-select input-medium child-element-list"';
		$dEl->multisize = '';

		$classDropdown = new PlgFabrik_ElementDropdown($subject);
		$elements[$nameElement]['objField'] = $classDropdown->getLayout('form');
		$elements[$nameElement]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);
		$elements[$nameElement]['dataField'] = $dEl;
		$elements[$nameElement]['dataLabel'] = $this->getDataLabel(
			$id,
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_COLLAB_LABEL'),
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_COLLAB_DESC'),
		);
	}

	/**
	 * Setter method to default layout element
	 *
	 * @param   	array 			$elements		Reference to all elements
	 * @param		string			$nameElement	Identity of the element
	 *
	 * @return  	null
	 * 
	 * @since 		version 4.0
	 * 
	 * @deprecated  @since 4.0.3 	This method was remove because this plugin is working only for jlowcode_admin template
	 */
	private function setElementDefaultLayout(&$elements, $nameElement) {
		$listModel = $this->getListModel();
		$subject = $this->getSubject();

		$tableList = $listModel->getTable();
		$val = $tableList->get('template');

		$id = 'easyadmin_modal___default_layout';
		$dEl = new stdClass();

		// Options to set up the element
		$dEl->options = $this->getLayoutsOptions();
		$dEl->name = $id;
		$dEl->id = $id;
		$dEl->selected = [$val];
		$dEl->multiple = '0';
		$dEl->attribs = 'class="fabrikinput form-select input-medium"';
		$dEl->multisize = '';

		$classDropdown = new PlgFabrik_ElementDropdown($subject);
		$elements[$nameElement]['objField'] = $classDropdown->getLayout('form');
		$elements[$nameElement]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$nameElement]['dataLabel'] = $this->getDataLabel(
			$id,
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_DEFAULT_LAYOUT_LABEL'),
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_DEFAULT_LAYOUT_DESC'),
		);
		$elements[$nameElement]['dataField'] = $dEl;
	}

	/**
	 * Method to get the layout options.
	 * Method copied from administrator/components/com_fabrik/models/fields/fabriktemplate.php (getOptions) because couldnt instanciate the class correctly
	 * 
	 * @return  array  The layout option.
	 * 
	 * @since version 4.0
	 */
	protected function getLayoutsOptions()
	{
		$path = JPATH_ROOT . '/components/com_fabrik/views/list/tmpl/';
		$path = str_replace('\\', '/', $path);
		$path = str_replace('//', '/', $path);

        $options = array();

        $path = Path::clean($path);

        // Get a list of folders in the search path with the given filter.
        $folders = Folder::folders($path);

        // Build the options list from the list of folders.
        if (is_array($folders))
        {
            foreach ($folders as $folder)
            {
                // Remove the root part and the leading /
                $folder = trim(str_replace($path, '', $folder), '/');

                $options[] = HTMLHelper::_('select.option', $folder, $folder);
            }
        }

		foreach ($options as &$opt)
		{
			$opt->value = str_replace('\\', '/', $opt->value);
			$opt->value = str_replace('//', '/', $opt->value);
			$opt->value = str_replace($path, '', $opt->value);
			$opt->text = str_replace('\\', '/', $opt->text);
			$opt->text = str_replace('//', '/', $opt->text);
			$opt->text = str_replace($path, '', $opt->text);
		}

		return $options;
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

		$elements[$nameElement]['dataLabel'] = $this->getDataLabel(
			$id, 
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
			'rating' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TYPE_RATING'),
			'thumbs' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TYPE_THUMBS')
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

		$elements[$nameElement]['dataLabel'] = $this->getDataLabel(
			$id, 
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
	 * Setter method to show in list element
	 *
	 * @param   array 	$elements		Reference to all elements
	 * @param	string	$nameElement	Identity of the element
	 *
	 * @return  null
	 * 
	 * @since version 4.0.1
	 */
	private function setElementShowInList(&$elements, $nameElement) {
		$subject = $this->getSubject();
		$id = 'easyadmin_modal___show_in_list';
		$dEl = new stdClass();
		$showOnTypes = ['text', 'longtext', 'file', 'date', 'dropdown', 'autocomplete', 'treeview', 'rating', 'thumbs'];

		// Options to set up the element
		$opts = Array(
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_NO'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_YES')
		);
		$elements[$nameElement]['objField'] = new FileLayout('joomla.form.field.radio.switcher');
		$elements[$nameElement]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$nameElement]['dataLabel'] = $this->getDataLabel(
			$id, 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_SHOW_IN_LIST_LABEL'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_SHOW_IN_LIST_DESC'),
			$showOnTypes, 
			false
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
	 * Setter method to width field element
	 *
	 * @param   array 	$elements		Reference to all elements
	 * @param	string	$nameElement	Identity of the element
	 *
	 * @return  null
	 * 
	 * @since version 4.0.2
	 */
	private function setElementWidthField(&$elements, $nameElement) 
	{
		$subject = $this->getSubject();
		$id = 'easyadmin_modal___width_field';
		$dEl = new stdClass;
		$showOnTypes = ['element-show_in_list'];

		// Options to set up the element
		$dEl->attributes = Array(
			'type' => 'number',
			'text_format' => 'integer',
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

		$elements[$nameElement]['dataLabel'] = $this->getDataLabel(
			$id, 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_WIDTH_FIELD_LABEL'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_WIDTH_FIELD_DESC'),
			$showOnTypes, 
			false
		);
		$elements[$nameElement]['dataField'] = $dEl;
	}

	/**
	 * Setter method to ordering elements element
	 *
	 * @param   array 	$elements		Reference to all elements
	 * @param	string	$nameElement	Identity of the element
	 *
	 * @return  null
	 * 
	 * @since version 4.0.2
	 */
	private function setElementOrderingElements(&$elements, $nameElement) 
	{
		$listModel = $this->getListModel();
		$subject = $this->getSubject();

		$id = 'easyadmin_modal___ordering_elements';
		$dEl = new stdClass();
		$showOnTypes = ['element-show_in_list'];

		// Options to set up the element
		$opts = $this->getElementsToOrderingInList();

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

		$elements[$nameElement]['dataLabel'] = $this->getDataLabel(
			$id, 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_ORDERING_ELEMENTS_LABEL'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_ORDERING_ELEMENTS_DESC'),
			$showOnTypes, 
			false
		);
		$elements[$nameElement]['dataField'] = $dEl;
	}

	/**
     * Get the elements that are in list to ordering it
	 * 
     * @return  Array  An array of all elements to order
     *
     * @since   4.0.2
     */
    public function getElementsToOrderingInList()
    {
		$listModel = $this->getListModel();
		$listModel->setId($this->getListId());

		$options = Array();
		foreach ($listModel->getElements('id') as $id => $element) {
			$element->getElement()->show_in_list_summary ? $options[$id] = $element->getElement()->label : null;
		}

		return $options;
	}

	/**
	 * Setter method to required element
	 *
	 * @param   array 	$elements		Reference to all elements
	 * @param	string	$nameElement	Identity of the element
	 *
	 * @return  null
	 * 
	 * @since version 4.0
	 */
	private function setElementRequired(&$elements, $nameElement) 
	{
		$subject = $this->getSubject();
		$id = 'easyadmin_modal___required';
		$dEl = new stdClass();
		$showOnTypes = ['text', 'longtext', 'file', 'date', 'dropdown', 'autocomplete', 'treeview', 'rating'];

		// Options to set up the element
		$opts = Array(
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_NO'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_YES')
		);
		$elements[$nameElement]['objField'] = new FileLayout('joomla.form.field.radio.switcher');
		$elements[$nameElement]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$nameElement]['dataLabel'] = $this->getDataLabel(
			$id, 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_REQUIRED_LABEL'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_REQUIRED_DESC'),
			$showOnTypes, 
			false
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
	 * Setter method to trash element
	 *
	 * @param   array 	$elements		Reference to all elements
	 * @param	string	$nameElement	Identity of the element
	 *
	 * @return  null
	 * 
	 * @since version 4.0.3
	 */
	private function setElementTrash(&$elements, $nameElement) 
	{
		$subject = $this->getSubject();
		$id = 'easyadmin_modal___trash';
		$dEl = new stdClass();
		$showOnTypes = ['text', 'longtext', 'file', 'date', 'dropdown', 'autocomplete', 'treeview', 'rating', 'thumbs'];

		// Options to set up the element
		$opts = Array(
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_NO'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_YES')
		);
		$elements[$nameElement]['objField'] = new FileLayout('joomla.form.field.radio.switcher');
		$elements[$nameElement]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$nameElement]['dataLabel'] = $this->getDataLabel(
			$id,
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TRASH_LABEL'),
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TRASH_DESC'),
			$showOnTypes,
			false
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
	private function setElementDefaultValue(&$elements, $nameElement) 
	{
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

		$elements[$nameElement]['dataLabel'] = $this->getDataLabel(
			$id, 
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
	private function setElementUseFilter(&$elements, $nameElement) 
	{
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

		$elements[$nameElement]['dataLabel'] = $this->getDataLabel(
			$id, 
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
	private function setElementAjaxUpload(&$elements, $nameElement) 
	{
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

		$elements[$nameElement]['dataLabel'] = $this->getDataLabel(
			$id, 
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
	private function setElementMakeThumbs(&$elements, $nameElement) 
	{
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

		$elements[$nameElement]['dataLabel'] = $this->getDataLabel(
			$id, 
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
	private function setElementFormat(&$elements, $nameElement) 
	{
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

		$elements[$nameElement]['dataLabel'] = $this->getDataLabel(
			$id,
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
	private function setElementOptsDropdown(&$elements, $nameElement) 
	{
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

		$elements[$nameElement]['dataLabel'] = $this->getDataLabel(
			$id, 
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
	private function setElementMultiSelect(&$elements, $nameElement) 
	{
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
		
		$elements[$nameElement]['dataLabel'] = $this->getDataLabel(
			$id, 
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
	private function setElementList(&$elements, $nameElement) 
	{
		$subject = $this->getSubject();
		$objDatabasejoin = new PlgFabrik_ElementDatabasejoin($subject);
		$id = 'easyadmin_modal___list';
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
			'dbjoin_autocomplete_rows' => 10,
			'database_join_where_sql' => 'SUBSTRING(`label`, 1, 1) != "_"'
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

		$elements[$nameElement]['dataLabel'] = $this->getDataLabel(
			$id, 
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
	private function setElementLabel(&$elements, $nameElement) 
	{
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

		$elements[$nameElement]['dataLabel'] = $this->getDataLabel(
			$id,
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
	private function setElementFather(&$elements, $nameElement) 
	{
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
		
		$elements[$nameElement]['dataLabel'] = $this->getDataLabel(
			$id, 
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
	private function setElementMultiRelations(&$elements, $nameElement) 
	{
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

		$elements[$nameElement]['dataLabel'] = $this->getDataLabel(
			$id, 
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
	private function setElementAccessRating(&$elements, $nameElement) 
	{
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

		$elements[$nameElement]['dataLabel'] = $this->getDataLabel(
			$id,
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
     * @since   4.0
     */
    public function getViewLevels()
    {
        $db    = Factory::getDbo();
        $query = $db->getQuery(true);

        // Get all the available view levels
        $query->select($db->qn('id'))
            ->select($db->qn('title'))
            ->from($db->qn('#__viewlevels'))
            ->order($db->qn('id'))
			->where($db->qn("id") . " IN ('" . implode("','", [1, $this->getListModel()->getTable()->access]) . "')");

        $db->setQuery($query);
        $result = $db->loadObjectList();
		
		$levels = Array();
		foreach ($result as $val) 
		{
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
	public function onSaveModal() 
	{
		$listModel = new FabrikFEModelList();

		$listModel = JModelLegacy::getInstance('List', 'FabrikFEModel');
		$model = JModelLegacy::getInstance('Element', 'FabrikAdminModel');
		
		$listId = $_POST['easyadmin_modal___listid'];
		$listModel->setId($listId);
		$app = Factory::getApplication();

		$input = $app->input;
		$data = $listModel->removeTableNameFromSaveData($_POST);
		$mode = $data['mode'];

		switch ($mode) {
			case 'elements':
				if($data['valIdEl'] != '0') {
					$idEl = $data['valIdEl'];
					$element = $listModel->getElements('id', true, false)[$idEl];
					$group_id = $element->getGroup()->getId();
				} else {
					foreach ($listModel->getFormModel()->getGroups() as $key => $value) {
						$groups[] = $value;
					}
					$group_id = $groups[0]->getGroup()->getId();
				}

				$r = $this->saveModalElements($data, $group_id, $listModel);
				break;
			
			case 'list':
				$r = $this->saveModalList($data, $listModel);
				break;
		}

		echo $r;
	}

	/**
	 * Function that save the modal data to elements
	 * 
	 * @param	array		The data sent
	 * @param	int			Group id of the list
	 * @param	object		Object of the frontend list model
	 * 
	 * @return  string		Success or false
	 * 
	 * @since 	version 4.0
	 */
	private function saveModalElements($data, $group_id, $listModel) {
		$db = Factory::getDbo();
		$modelElement = new FabrikAdminModelElement();

		$validate = $this->validateElements($data);
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
		$opts['published'] = $data['trash'] == 'true' ? '0' : '1';
		$opts['show_in_list_summary'] = $data['show_in_list'] != '' ? '1' : '0';
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
					$params['fu_show_image'] = '2';
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
					'sub_values' => array_map(function($opt) {return preg_replace('/[^A-Za-z0-9]/', '_', trim($opt));}, $sub_options),
					'sub_labels' => $sub_options,
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
				$params['rating-mode'] = 'user-rating';
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
					$type == 'autocomplete' ? $opts['filter_type'] = 'auto-complete' : $opts['filter_type'] = 'treeview';
				}

				if($type == 'autocomplete') {
					$params['database_join_display_style'] =  'only-autocomplete';
					$params['jsSuggest'] =  '1';
					$params['moldTags'] =  '1';
				} else {
					$params['database_join_display_style'] =  'both-treeview-autocomplete';
					$params['tree_parent_id'] =  $data['father'];
					$params['fabrikdatabasejoin_frontend_add'] =  '1';
					$params['fabrikdatabasejoin_frontend_blank_page'] =  '0';
					$params['join_popupwidth'] =  '80%';

					$query = $db->getQuery(true);
					$query->select('f.id AS value, f.label AS text, l.id AS listid')->from('#__fabrik_forms AS f')
						->join('LEFT', '#__fabrik_lists As l ON f.id = l.form_id')
						->where('f.published = 1 AND l.db_table_name = ' . $db->q($data['list']));
					$db->setQuery($query);
					$options = $db->loadObjectList();

					$params['databasejoin_popupform'] =  $options[0]->value;
				}

				break;
			
			case 'thumbs':
				$opts['plugin'] = 'thumbs';
				$params['rate_in_from'] =  '0';
				break;
		}

		if($data['use_filter']) {
			$opts['filter_exact_match'] = '0';
			$params['filter_access'] = '1';
			$params['filter_length'] = '20';
			$params['filter_required'] = '0';
			$params['filter_build_method'] = '1';
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

		if($data['show_in_list']) {
			$params['tablecss_cell'] = $data['width_field'] ? 'width: ' . $data['width_field'] . '%;' : "";
		}

		$params['can_order'] = '1';
		$opts['params'] = $params;

		if($opts['id'] != '0') {
			$this->syncParams($opts, $listModel);
		}

		if(!$validate->error) {
			$modelElement->save($opts);
			$saveOrder = $this->saveOrder($modelElement, $data, $listModel);
			if(!$saveOrder) {
				$validate->error = Text::_("");
			}
		}

		return json_encode($validate);
	}

	/**
	 * Function that treated the data and save the order of the elements
	 * 
	 * @param	Object		$modelElement		Object of the admin list model
	 * @param	Array		$data				The data sent
	 * @param	Object		$listModel			Object of the frontend list model
	 * 
	 * @return  null
	 * 
	 * @since 	version 4.0.2
	 */
	private function saveOrder($modelElement, $data, $listModel) 
	{
		$pks = Array();
		$order = Array();
		$x = 0;

		$idAtual = (int) $data["valIdEl"];
		$idOrder = (int) $data["ordering_elements"];
		$elements = $listModel->getElements("id");

		//If element does not must show in list or the ordering not changed, nothing is gonna happen
		if($data['show_in_list'] == '' || $idAtual == $idOrder) {
			return;
		}

		foreach ($elements as $id => $element) {
			if($id != $idAtual) {
				$pks[$x] = $id;
				$order[$x] = $x+1;
				$x++;
			}

			if($id == $idOrder) {
				$pks[$x] = $idAtual;
				$order[$x] = $x+1;
				$x++;
			}
		}

		// Before to save the ordering we need to change the permissions and later change again
		$originalRules = $this->changeRulesPermissons("change");

		$modelElement->saveorder($pks, $order);
		
		return $this->changeRulesPermissons("recover", $originalRules);
	}

	/**
	 * Function that change or recover the rules of table #__assets
	 * 
	 * We need to do this because the elements ordering must be done originally only by admin users
	 * 
	 * @param	String		$mode		Object of the admin list model
	 * @param	String		$rule		The data sent
	 * 
	 * @return  String|Boolean		
	 * 
	 * @since 	version 4.0.2
	 */
	private function changeRulesPermissons($mode, $rule=null) 
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
		$user = Factory::getApplication()->getIdentity();
		$groups = $user->getAuthorisedViewLevels();

		switch ($mode) {
			case 'change':
				$query = $db->getQuery(true);
				$query->select($db->qn("rules"))
					->from($db->qn("#__assets"))
					->where($db->qn("name") . " = " . $db->q("root.1"));
				$db->setQuery($query);
				$originalRules = $db->loadColumn()[0];
				$rules = json_decode($originalRules, true);
				$rules["core.admin"][max($groups)] = 1;

				$query = $db->getQuery(true);
				$query->update($db->qn("#__assets"))
					->set($db->qn("rules") . " = " . $db->q(json_encode($rules)))
					->where($db->qn("name") . " = " . $db->q("root.1"));
				$db->setQuery($query);
				$db->execute();
				
				break;
			
			case 'recover':
				$query = $db->getQuery(true);
				$query->update($db->qn("#__assets"))
					->set($db->qn("rules") . " = " . $db->q($rule))
					->where($db->qn("name") . " = " . $db->q("root.1"));
				$db->setQuery($query);
				$recover = $db->execute();
				break;
		}

		return $mode == "change" ? $originalRules : $recover;
	}

	/**
	 * Function that save the modal data to list
	 * 
	 * @param	Array		$data				The data sent
	 * @param	Object		$listModel			Object of the frontend list model
	 * 
	 * @return  string	Success or false
	 * 
	 * @since 	version 4.0
	 */
	private function saveModalList($data, $listModel)
	{
		$app = Factory::getApplication();
		$input = $app->input;
		
		$modelList = new FabrikAdminModelList();
		$modelForm = new FabrikAdminModelForm();
		$formModel = new FabrikFEModelForm();

		$formModel->setId("11");
		$groupsForm = $formModel->getGroups();

		$properties = $listModel->getTable()->getProperties();
		$propertiesForm = $listModel->getFormModel()->getTable()->getProperties();

		$validate = $this->validateList($data);
		if($validate->error) {
			echo json_encode($validate);
			return;
		}

		$dataList['label'] = $data['name_list'];
		$dataList['introduction'] = $data['description_list'];
		//$dataList['order_by'] = array($data['ordering_list']);			//Updated by input data order_by (js)
		//$dataList['order_dir'] = array($data['ordering_type_list']);		//Updated by input data order_dir (js)
		$dataList['template'] = $data['default_layout'];

		foreach ($properties as $key => $val) {
			if(!array_key_exists($key, $dataList)) {
				$dataList[$key] = $properties[$key];
			}

			if($key == 'params') {
				$dataList[$key] = json_decode($dataList[$key], true);
				$dataList[$key]['admin_template'] = $data['default_layout'];
			}
		}	

		$dataForm['current_groups'] = array_keys($groupsForm);
		$pluginsForm = Array();
		foreach ($propertiesForm as $key => $val) {
			if(!array_key_exists($key, $dataForm)) {
				$dataForm[$key] = $propertiesForm[$key];
			}

			if($key == 'params') {
				$dataForm[$key] = json_decode($dataForm[$key], true);
				$dataForm[$key]['approve_for_own_records'] = $data['collab_list'];
				$pluginsForm['plugin'] = $dataForm[$key]['plugins'];
				$pluginsForm['plugin_locations'] = $dataForm[$key]['plugin_locations'];
				$pluginsForm['plugin_events'] = $dataForm[$key]['plugin_events'];
				$pluginsForm['plugin_description'] = $dataForm[$key]['plugin_description'];
				$pluginsForm['plugin_state'] = $dataForm[$key]['plugin_state'];
			}
		}	

		if(!$validate->error) {
			$modelList->save($dataList);
			$input->set('jform', $pluginsForm);
			$modelForm->save($dataForm);
		}

		return json_encode($validate);
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
		$element = $listModel->getElements('id', true, false)[$idEl];
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

		// For new version - cause validations to be added and not overwritten
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
	private function validateElements($data) {
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
	 * We need update the id saved from input to edit the list correctly
	 *
	 * @param   array 	$data		Source of options
	 *
	 * @return  object
	 * 
	 * @since 	version 4.0
	 */
	private function validateList($data) {
		$validate = new stdClass();
		$validate->error = false;
		$validate->message = "";

		// Name is required
		if(empty($data['name_list'])) {
			$validate->error = true;
			$validate->message = Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ERROR_NAME_LIST_EMPTY');
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
		if(strpos($input->get('task'), 'filter') > 0 || strpos($input->get('task'), 'order') > 0 || $input->get('format') == 'csv') {
			return;
		}

		if (!is_array($data) || empty($item->id) || !$data['easyadmin'] || $context != 'com_fabrik.element') {
            return;
        }

		$app->getInput()->set("id", $id);
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
	 * Getter method to elements list variable
	 *
	 * @return  null
	 * 
	 * @since version 4.0
	 */
	public function getElementsList() {
		return $this->elementsList;
	}

	/**
	 * Setter method to list model variable
	 *
	 * @return  null
	 * 
	 * @since version 4.0
	 */
	public function setListModel($listModel) {
		$this->listModel = $listModel;
	}

	/**
	 * Getter method to list model variable
	 *
	 * @return  Object
	 * 
	 * @since version 4.0
	 */
	public function getListModel() {
		return $this->listModel;
	}

	/**
	 * Setter method to list id variable
	 *
	 * @return  null
	 * 
	 * @since version 4.0.2
	 */
	public function setListId($listId) {
		$this->listId = $listId;
	}

	/**
	 * Getter method to list id variable
	 *
	 * @return  String
	 * 
	 * @since version 4.0.2
	 */
	public function getListId() {
		return $this->listId;
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
		$this->images['trash'] = FabrikHelperHTML::image('trash.png', 'list');
	}

	/**
	 * Getter method to images variable
	 *
	 * @return  Object
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
