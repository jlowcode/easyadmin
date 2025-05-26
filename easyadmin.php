<?php
/**
 * Fabrik List Plugin
 *
 * @package     Joomla.Plugin
 * @subpackage  Fabrik.list.js
 * @copyright   Copyright (C) 2024 Jlowcode Org - All rights reserved.
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
use Joomla\Component\Modules\Administrator\Model\ModuleModel;
use Joomla\CMS\User\User;
use Joomla\Component\Users\Administrator\Model\UserModel;
use Joomla\CMS\Language\Transliterate;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Editor\Editor;
use Joomla\Component\Menus\Administrator\Model\ItemModel;
use Joomla\CMS\Date\Date;

// Requires 
// Change to namespaces on F5
require_once COM_FABRIK_FRONTEND . '/models/plugin-list.php';
require_once JPATH_PLUGINS . '/fabrik_element/field/field.php';
require_once JPATH_PLUGINS . '/fabrik_element/dropdown/dropdown.php';
require_once JPATH_PLUGINS . '/fabrik_element/databasejoin/databasejoin.php';
require_once JPATH_PLUGINS . '/fabrik_element/fileupload/fileupload.php';
require_once JPATH_PLUGINS . '/fabrik_list/easyadmin/easyadmin_script.php';
require_once JPATH_BASE . '/components/com_fabrik/models/element.php';
require_once JPATH_BASE . '/components/com_fabrik/models/list.php';
require_once JPATH_BASE . '/components/com_fabrik/models/form.php';
require_once JPATH_ADMINISTRATOR . '/components/com_fabrik/models/element.php';
require_once JPATH_ADMINISTRATOR . '/components/com_fabrik/models/list.php';
require_once JPATH_ADMINISTRATOR . '/components/com_fabrik/models/form.php';
require_once JPATH_ADMINISTRATOR . '/components/com_fabrik/models/group.php';

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

	private $plugins = ['databasejoin', 'date', 'field', 'textarea', 'fileupload', 'dropdown', 'rating', 'thumbs', 'display', 'youtube', 'link', 'user', 'internalid'];

	private $idModal = 'modal-elements';

	private $idModalList = 'modal-list';

	private $dbTableNameModal = 'fabrik_easyadmin_modal';

	private $prefixEl = 'easyadmin_modal';

	private $modalParams;

	/**
	 * Constructor
	 *
	 * @param   	Object 		&$subject 		The object to observe
	 * @param   	Array		$config   		An array that holds the plugin configuration
	 *
	 * @return		Null
	 */
	public function __construct(&$subject, $config) 
	{
		parent::__construct($subject, $config);

		$app = Factory::getApplication();
		$input = $app->input;
		$requestWorkflow = $input->getInt('requestWorkflow');

		$this->setListId($input->get('listid'));

		if(!$this->getListId()) return;

		//We don't have run
		if(!$this->mustRun() || !$this->authorized()) {
			return;
		}

		if(!$input->get('formid') && $input->get('view') == 'list' || $requestWorkflow) {
			$listModel = JModelLegacy::getInstance('List', 'FabrikFEModel');
			$listModel->setId($this->listId);

			$this->db_table_name = $listModel->getTable()->db_table_name;

			$this->setListModel($listModel);
			$this->setSubject($subject);
			$this->setModalParams();

			if(!$requestWorkflow) {
				$this->setElements();
				$this->setElementsList();
				$this->customizedStyle();
			}
		}
	}

	/**
	 * Init function
	 *
	 * @return  	Null
	 */
	protected function init() 
	{
		$db = Factory::getContainer()->get('DatabaseDriver');

		$this->jsScriptTranslation();
		$listModel = $this->getListModel();
		$elements = $listModel->getElements(true, true, false);

		$workflowExist = $this->workflowExists();
		$workflow = $this->getListModel()->getParams()->get('workflow_list', '1') && $workflowExist;

		if(!$this->authorized()) {
			return;
		}

		$opts = new StdClass;
		$opts->baseUri = URI::base();
		$opts->allElements = $this->processElements($elements);
		$opts->elements = $this->processElements($elements, true);
		$opts->elementsNames = $this->processElementsNames($elements);
		$opts->listUrl = $this->createListLink($this->getModel()->getId());
		$opts->actionMethod = $listModel->actionMethod();
		$opts->images = $this->getImages();
		$opts->idModal = $this->idModal;
		$opts->idModalList = $this->idModalList;
		$opts->dbPrefix = $db->getPrefix();
		$opts->workflow = $workflow;
		$opts->owner_id = $listModel->getFormModel()->getTable()->get('created_by');
		$opts->isAdmin = $this->user->authorise('core.admin');
		$opts->user = $this->user;

		echo $this->setUpModalElements();
		echo $this->setUpModalList();

		// Load the JS code and pass the opts
		$this->loadJS($opts);
	}

	/**
	 * This method verify if workflow extension is already installed
	 * 
	 * @return 		Boolean
	 */
    private function workflowExists()
    {
		$db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select($db->qn('extension_id'))
            ->from($db->qn('#__extensions'))
            ->where($db->qn('name') . ' = ' . $db->q('plg_fabrik_list_workflow_request'), 'OR')
            ->where($db->qn('name') . ' = ' . $db->q('plg_fabrik_form_workflow'));
        $db->setQuery($query);
        $result = $db->loadColumn();

        if (count($result) == 2) {
            return true;
        }

        return false;
    }

	/**
	 * Method to check if the user is authorized
	 * 
	 * @return  	Boolean
	 * 
	 * @since 		version 4.0.2
	 */
	private function authorized()
	{
		$listModel = JModelLegacy::getInstance('List', 'FabrikFEModel');
		$user = Factory::getUser();
		$db = Factory::getContainer()->get('DatabaseDriver');

		!empty($this->getListModel()) ? $listModel = $this->getListModel() : $listModel->setId($this->listId);
		$params = $listModel->getParams();
		$showOptions = (int) $params->get('show_options', 0);

		if($showOptions == 2) return false;
		if($user->authorise('core.admin')) return true;
		if($showOptions == 1) return false;

		$workflowExist = $this->workflowExists();
		$workflow = $listModel->getParams()->get('workflow_list', '1') && $workflowExist;

		$groupsLevels = $user->groups;
		$viewLevels = $user->getAuthorisedViewLevels();
		$levelEditList = (int) $listModel->getParams()->get("allow_edit_details");

		// If workflow set, only registered can suggest with data model
		if($workflow) {
			return in_array('2', $viewLevels);
		}

		$query = $db->getQuery(true);
		$query->select($db->qn("rules"))
			->from($db->qn("#__viewlevels"))
			->where($db->qn("id") . " = " . $db->q($levelEditList));
		$db->setQuery($query);
		$groups = json_decode($db->loadResult());

		return count(array_intersect($groupsLevels, $groups)) > 0 ? true : false;
	}

	/**
	 * This method says if the plugin must run or not
	 * 
	 * @return		Boolean
	 */
	private function mustRun() 
	{
		$app = Factory::getApplication();
		$input = $app->input;

		if(
			strpos($input->get('task'), 'filter') > 0 ||
			strpos($input->get('task'), 'order') > 0 ||
			$input->get('format') == 'csv' ||
			$input->get('format') == 'pdf' ||
			$input->get('view') == 'article' ||
			$input->get('task') == 'list.delete' ||
			in_array('form', explode('.', $input->get('task'))) &&
			($input->get('plugin') != 'easyadmin' || $input->get('view') != 'list') ||
			($input->get('view') == 'plugin' && $input->get('plugin') != 'easyadmin') ||
			($input->get('action') == 'getFilhos') ||
			isset($_REQUEST['resetfilters']) ||
			Factory::getApplication()->isClient('administrator')
		) {
			return false;
		}

		return true;
	}

	/**
	 * Method to load the javascript code for the plugin
	 * 
	 * @param   	Array		$opts 		Configuration array for javascript.
	 * 
	 * @return  	Null
	 */
	protected function loadJS($opts) 
	{
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
	 * Method that process the data of elements to edit them
	 *
	 * @param   	Object		$elements		Object of each element of the list
	 * @param   	Boolean		$div			True if the elements must return separated by trash elements and published elements
	 * 
	 * @return 		Object
	 */
	protected function processElements($elements, $div=false)
	{
		$processedElements = new stdClass;
		$processedElements->published = new stdClass;
		$processedElements->trash = new stdClass;

		foreach($elements as $key => $element) {
			$dataEl = new stdClass();
			$fullElementName = $this->processFullElementName($key);

			if(in_array($this->processFullElementName($key, true), ['indexing_text', 'created_ip', 'hits'])) continue;

			$link = $this->createLink($element->element->id);
			$idElement = $element->getId();
			$enable = $this->isEnabledEdit($element->getElement());

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
	 * Method that return if the type of plugin is treated by us or not
	 *
	 * @param   	Object			$element 		Object of the element
	 *
	 * @return 		Boolean
	 */
	private function isEnabledEdit($element) 
	{
		$type = $element->plugin;
		$name = $element->name;
		
		return in_array($type, $this->plugins) && !str_contains($name, 'indexing_text');
	}
	
	/**
	 * Method that set the element data to each element of the list
	 *
	 * @param   	Object			$dataEl 		Element data object
	 * @param   	Object			$element 		Element object
	 * @param   	Boolean			$enable 		The element is treated by us or not
	 * 
	 * @return 		Null
	 */
	private function setDataElementToEditModal($dataEl, $element, &$enable) 
	{
		$dataElement = $element->getElement();
		$params = json_decode($dataElement->params, true);
		$plugin = $dataElement->plugin;

		if(!$enable) {
			return;
		}

		preg_match('/(\d+)/', $params["tablecss_cell"], $matches);

		$dataEl->use_filter = $dataElement->filter_type ? true : false;
		$dataEl->required = $this->verifyRequiredValidation($element, 'notempty');
		$dataEl->show_in_list = $dataElement->show_in_list_summary ? true : false;
		$dataEl->width_field = $matches[0];
		$dataEl->name = $dataElement->label;
		$dataEl->ordering_elements = $element->getId();
		$dataEl->trash = $dataElement->published == 1 ? false : true;
		$dataEl->white_space = !str_contains($params["tablecss_cell"], 'nowrap');

		switch ($plugin) {
			case 'field':
			case 'textarea':
				$dataEl->default_value = $dataElement->default;
				$dataEl->text_format = $params['password'] == '5' ? 'url' : $params['text_format'];
				$dataEl->format_long_text = $params['use_wysiwyg'];
				$dataEl->type = $plugin == 'field' ? $params['element_link_easyadmin'] == '1' ? 'link' : 'text' : 'longtext';

				// Removed type integer and decimal because we have too many problems and not too much benefits
				if(in_array($params['text_format'], ['integer', 'decimal'])) {
					$dataEl->text_format = 'text';
				}

				// The element is field, but of type link
				if((bool) $params['element_link_easyadmin']) {
					$paramsForm = $this->getListModel()->getFormModel()->getParams();
					$dataEl->thumb_link = $paramsForm->get('thumb');
					$dataEl->title_link = $paramsForm->get('title');
					$dataEl->description_link = $paramsForm->get('description');
					$dataEl->subject_link = $paramsForm->get('subject');
					$dataEl->creator_link = $paramsForm->get('creator');
					$dataEl->date_link = $paramsForm->get('date');
					$dataEl->format_link = $paramsForm->get('format');
					$dataEl->coverage_link = $paramsForm->get('coverage');
					$dataEl->publisher_link = $paramsForm->get('publisher');
					$dataEl->identifier_link = $paramsForm->get('identifier');
					$dataEl->language_link = $paramsForm->get('language');
					$dataEl->type_link = $paramsForm->get('type');
					$dataEl->contributor_link = $paramsForm->get('contributor');
					$dataEl->relation_link = $paramsForm->get('relation');
					$dataEl->rights_link = $paramsForm->get('rights');
					$dataEl->source_link = $paramsForm->get('source');
				}
			break;

			case 'fileupload':
				$dataEl->ajax_upload = $params['ajax_upload'] == '1' ? true : false;
				$dataEl->type = 'file';
				break;

			case 'dropdown':
				$dataEl->multi_select = $params['multiple'] == '1' ? true : false;
				$dataEl->type = $params['allow_frontend_addtodropdown'] == '1' ? 'tags' : 'dropdown';
				$dataEl->options_dropdown = implode(', ', (array) $params['sub_options']['sub_labels']);
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
				$dataEl->listas = $params['join_db_name'];

				if(!in_array($params['database_join_display_type'], ['checkbox', 'auto-complete'])) {
					$enable = false;
					return;
				}

				$dataEl->multi_relation = $params['database_join_display_type'] == 'auto-complete' ? false : true;
				$dataEl->type = $params['database_join_display_style'] == 'only-autocomplete' ? 'autocomplete' : 'treeview';
				$dataEl->label =  $params['join_val_column'];
				$dataEl->father = $params['tree_parent_id'];

				if($params['moldTags']) {
					$dataEl->tags = 'tags';
				} else if($params['fabrikdatabasejoin_frontend_add']) {
					$dataEl->tags = 'popup_form';
				} else {
					$dataEl->tags = 'no';
				}

				break;

			case 'display':
				preg_match('/related_list_element-(.*?)\}/', $dataElement->get('default'), $matches);
				preg_match('/related_list-(.*?)\}/', $dataElement->get('default'), $matchesList);

				$dataEl->type = 'related_list';
				$dataEl->related_list = explode('-', $matchesList[0])[1];
				$dataEl->related_list_element = explode('-', $matches[0])[1];
				break;

			case 'thumbs':
				$dataEl->type = 'thumbs';
				$dataEl->show_down_thumb = isset($params['show_down']) ? $params['show_down'] ? true : false : true;
				break;

			default:
				$dataEl->type = $plugin;
				break;
		}
	}

	/**
	 * This method iterate by validations element and return if exist some the validation passed
	 * 
	 * @param		Object			$element			Element object
	 * @param		String			$validation			Validation name to search
	 * 
	 * @return 		Boolean
	 * 
	 * @since		v4.3.1
	 */
	private function verifyRequiredValidation($element, $validation)
	{
		$validations = $element->getValidations();

		foreach ($validations as $modelValidation) {
			if(stripos($modelValidation->getName(), $validation)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Method that process the name of elements to edit them
	 *
	 * @param   	Object			$elements 		Object of each element of the list
	 * @param   	Boolean			$mod 			Must be return label or name of the element
	 * 
	 * @return 		Object		
	 */
	protected function processElementsNames($elements, $mod=true) 
	{
		$processedElements = new stdClass;

		foreach($elements as $key => $element) {
			$idElement = $element->getId();
			$processedElements->$idElement = $mod ? $element->element->label : $element->element->name;	
		}

		return $processedElements;
	}

	/**
	 * Method that process the full name of elements to edit them
	 *
	 * @param   	String			$key 		The full element name to process
	 * @param   	Boolean			$func 		If true, return only lastName
	 * 
	 * @return 		String		
	 */
	protected function processFullElementName($key, $func=false) 
	{
		$pos = strpos($key, '.');
		$firstName = substr ($key , 1, $pos-2);
		$lastName = substr ($key , $pos+2);
		$lastName = substr ($lastName , 0, strlen($lastName) - 1);
		$processedKey = $firstName . "___" . $lastName;

		return $func ? $lastName : $processedKey;
	}

	/**
	 * Method that create the link to modal view to elements
	 *
	 * @param   	Int			$elementId 		The id of the element
	 *
	 * @return 		String		
	 */
	protected function createLink($elementId) 
	{
		$baseUri = URI::base();
		return $baseUri . "administrator/index.php?option=com_fabrik&view=element&layout=edit&id=". $elementId . "&modalView=1";
	}

	/**
	 * Method that create the link to modal view to list
	 *
	 * @param   	Int			$listId 		The id of the list
	 *
	 * @return 		String		
	 */
	protected function createListLink($listId) 
	{
		$baseUri = URI::base();
		return $baseUri ."administrator/index.php?option=com_fabrik&view=list&layout=edit&id=". $listId . "&modalView=1";
	}

	/**
	 * Method run on when list is being loaded. Used to trigger the init function
	 *
	 * @param   	Array		&$args		Arguments
	 * 
	 * @return 		Null
	 */
	public function onPreLoadData(&$args) 
	{
		//We don't have run
		if(!$this->mustRun() || !$this->authorized()) {
			return;
		}

		$this->setImages();
		$this->init();
	}

	/**
	 * Setting the object elements that need js files
	 * 
	 * @param   	Array 		$elements		Reference to databasejoin object
	 * 
	 * @return  	Null
	 * 
	 * @since 		version 4.0
	 */
	public function onLoadData(&$args)
	{
		$listModelModal = new FabrikFEModelList();
		$pluginManager = FabrikWorker::getPluginManager();
		$mediaFolder = FabrikHelperHTML::getMediaFolder();
		$modalParams = json_decode($this->getModalParams(), true);

		//We don't have run
		if(!$this->mustRun() || !$this->authorized()) {
			return;
		}

		$listModel = $this->getListModel();
		$formModel = $listModel->getFormModel();

		$listModelModal->setId($modalParams['list']);
		$formModelModal = $listModelModal->getFormModel();
		$formModelModal->getData();
		$formModelModal->getGroupsHiarachy();
		$elementsModal = $listModelModal->getElements('id');

		$elements = Array(
			'elements' => ['list', 'options_dropdown'],
			'elementsList' => ['admins_list', 'owner_list', 'thumb_list']
		);
		$srcs = array_merge(
			array(
				'FloatingTips' => $mediaFolder . '/tipsBootStrapMock.js',
				'FbForm' => $mediaFolder . '/form.js',
				'Fabrik' => $mediaFolder . '/fabrik.js'
			),
			FabrikHelperHTML::framework()
		);

		$srcs['Placeholder'] = 'media/com_fabrik/js/lib/form_placeholder/Form.Placeholder.js';
		$srcs['FormSubmit'] = $mediaFolder . '/form-submit.js';
		$srcs['Element'] = $mediaFolder . '/element.js';

		Factory::getDocument()->addScript('plugins/fabrik_element/fileupload/lib/plupload/js/plupload.js', 'plupload');
		Factory::getDocument()->addScript('plugins/fabrik_element/fileupload/lib/plupload/js/plupload.html5.js', 'plupload.html5');

		foreach ($elements as $key => $els) {
			foreach ($els as $nameElement) {
				$idEl = $modalParams['elementsId'][$nameElement];
				$obj = $idEl ? $elementsModal[$idEl] : $this->$key[$this->prefixEl . '___' . $nameElement]['objField'];

				$ref = $obj->elementJavascript(0);
				$ext = FabrikHelperHTML::isDebug() ? '.js' : '-min.js';

				if(is_array($ref) && count($ref) == 2) {
					$elementJs[] = $ref[1];
					$ref = $ref[0];
				}

				switch ($nameElement) {
					case 'options_dropdown':
						$plugin = 'ElementDropdown';
						$nameFile = 'dropdown';
						break;
					
					case 'thumb_list':
						$plugin = 'ElementFileupload';
						$nameFile = 'fileupload';
						break;

					default:
						$plugin = 'ElementDatabasejoin';
						$nameFile = 'databasejoin';
						break;
				}

				$path = "plugins/fabrik_element/{$nameFile}/{$nameFile}" . $ext;
				$srcs[$plugin] = $path;
				FabrikHelperHTML::script([$plugin => $path], json_encode($ref));

				if (!empty($ref)) {
					$elementJs[] = $ref;
				}
			}
		}

		$opts = $this->jsOpts();
		$formModel->jsOpts = $opts;
		$bKey = $formModel->jsKey();
		$key = $formModel->getId();
		$opts = json_encode($formModel->jsOpts);
		$groupId = array_keys($formModel->getGroups())[0];

		$groupedJs = new stdClass;
		$groupedJs->$groupId = $elementJs;
		$json = json_encode($groupedJs);
		$script   = array();
		$script[] = "\t\tvar $bKey = new FbForm(" . $key . ", $opts);";
		$script[] = "\t\tFabrik.addBlock('$bKey', $bKey);";
		$script[]  = "\t{$bKey}.addElements(";
		$script[] = $json;
		$script[] = "\t);";
		$str = implode("\n", $script);
		FabrikHelperHTML::script($srcs, $str);
	}

	/**
	 * Load the JavaScript ini options to render elements that need js files
	 * This functions is a cheap copy of the jsOpts function from components/com_fabrik/views/form/view.base.php
	 * 
	 * @return		stdClass
	 * 
	 * @since  		version 4.1.4
	 */
	protected function jsOpts()
	{
		$input = $this->app->getInput();

		/** @var FabrikFEModelForm $model */
		$listModel 			  = $this->getListModel();
		$model				  = $listModel->getFormModel();
		$fbConfig             = ComponentHelper::getParams('com_fabrik');
		$form                 = $model->getForm();
		$params               = $model->getParams();
		$listModel            = $model->getlistModel();
		$table                = $listModel->getTable();
		$opts                 = new stdClass;
		$opts->admin          = $this->app->isClient('administrator');
		$opts->ajax           = $model->isAjax();
		$opts->ajaxValidation = (bool) $params->get('ajax_validations');
		$opts->lang           = FabrikWorker::getMultiLangURLCode();
		$opts->toggleSubmit   = (bool) $params->get('ajax_validations_toggle_submit');
		$opts->showLoader     = (bool) $params->get('show_loader_on_submit', '0');
		$key                  = FabrikString::safeColNameToArrayKey($table->db_primary_key);
		$opts->primaryKey     = $key;
		$opts->error          = @$form->origerror;
		$opts->pages          = $model->getPages();
		$opts->plugins        = array();
		$opts->multipage_save = (int) $model->saveMultiPage();
		$opts->editable       = $model->isEditable();
		$opts->print          = (bool) $input->getInt('print');
		$opts->inlineMessage = (bool) $this->isMambot;
		$opts->rowid = (string) $model->getRowId();

		$errorIcon       = $fbConfig->get('error_icon', 'exclamation-sign');
		$this->errorIcon = FabrikHelperHTML::image($errorIcon, 'form', $this->tmpl);

		$imgs               = new stdClass;
		$imgs->alert        = FabrikHelperHTML::image($errorIcon, 'form', $this->tmpl, '', true);
		$imgs->action_check = FabrikHelperHTML::image('action_check.png', 'form', $this->tmpl, '', true);

		$imgs->ajax_loader = FabrikHelperHTML::icon('icon-spinner icon-spin');
		$opts->images      = $imgs;

		$opts->fabrik_window_id = $input->getRaw('fabrik_window_id', '');
		$opts->submitOnEnter    = (bool) $params->get('submit_on_enter', false);

		return $opts;
	}

	/**
     * Method sends message texts to javascript file
     *
	 * @return  	Null
	 * 
     * @since 		version 4.0
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
		Text::script('PLG_FABRIK_LIST_EASY_ADMIN_MESSAGE_CONFIRM_NEW_OWNER');
		Text::script('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TEXT_RELATIONSHIP_LOCKED');
		Text::script('PLG_FABRIK_LIST_EASY_ADMIN_ERROR');
    }

	/**
	 * Method that set up the modal to elements
	 *
	 * @return  	String
	 * 
	 * @since		version 4.0
	 */
	private function setUpModalElements() 
	{
		$config['title'] = Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TITLE');

		$body = $this->setUpBody('elements');
		$modal = $this->setUpModal($body, $config, 'elements');

		return $modal;
	}

	/**
	 * Method that set up the modal to list
	 * 
	 * @return  String
	 * 
	 * @since 	version 4.0
	 */
	private function setUpModalList()
	{
		$config['title'] = Text::_('PLG_FABRIK_LIST_EASY_ADMIN_LIST_TITLE');

		$body = $this->setUpBody('list');
		$modal = $this->setUpModal($body, $config, 'list');

		return $modal;
	}

	/**
	 * Method that set up the modal
	 *
	 * @param   	String 		$body 			Body string
	 * @param   	Array  		$config			Configuration array for modal.
	 *
	 * @return  	String  The modal
	 * 
	 * @since 		version 4.0
	 */
	private function setUpModal($body, $config, $type) 
	{
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
				'backdrop'    	=> 'static',
				'keyboard'    	=> false,
				'focus'			=> false,
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
	 * Method that set up the footer to modal
	 *
	 * @param		String			$type		Footer mode
	 * 
	 * @return  	String  		The footer
	 * 
	 * @since 		version 4.0
	 */
	private function setUpFooter($type) 
	{
		$viewLevelList = (int) $this->getListModel()->getParams()->get('allow_edit_details');

		$footer = '<div class="d-flex">';
		$footer .= 	'<button class="btn btn-easyadmin-modal" id="easyadmin_modal___submit_' . $type . '" data-dismiss="modal" aria-hidden="true" style="margin-right: 10px">' . Text::_("JAPPLY") . '</button>';

		if($type == 'list') {
			$footer .=  '<input type="hidden" id="easyadmin_modal___db_table_name" name="db_table_name" value="' . $this->db_table_name . '">';
		}
		
		$footer .=  '<input type="hidden" id="easyadmin_modal___history_type" name="history_type" value="">';
		$footer .=  '<input type="hidden" id="easyadmin_modal___viewLevel_list" name="viewLevel_list" value="' . $viewLevelList . '">';

		$footer .= '</div>';

		return $footer;
	}

	/**
	 * Method that redirect to set up the body modal
	 *
	 * @param		String 			$type		Type of modal
	 *
	 * @return 		String
	 * 
	 * @since 		version 4.0
	 */
	private function setUpBody($type) 
	{
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
	 * Method that set up the body modal to elements
	 * 
	 * @param		Int			$return			Choose to string return (0) or array return (1)
	 * 
	 * @return  	String|Array
	 * 
	 * @since 		version 4.0
	 */
	private function setUpBodyElements($return=0) 
	{
		$layoutBody = $this->getLayout('modal-body');
		$elements = $this->getElements();

		$data = new stdClass();
		$data->labelPosition = '0';

		foreach ($elements as $nameElement => $element) {
			$dEl = new stdClass();
			$data->label = $element['objLabel']->render((object) $element['dataLabel']);
			$data->element = isset($element['objField']) ? $element['objField']->render($element['dataField']) : '';
			$data->cssElement = $element['cssElement'];

			switch ($return) {
				case 1:
					$body[$nameElement] = $layoutBody->render($data);
					break;
				
				default:
					$body .= $layoutBody->render($data);
					break;
			}
		}

		return $body;
	}


	/**
	 * Method that set up the body modal to elements
	 *
	 * @return  	String
	 * 
	 * @since 		version 4.0
	 */
	private function setUpBodyList() 
	{
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
			$data->cssElement = $element['cssElement'];
			$body .= $layoutBody->render($data);
		}

		return $body;
	}

	/**
	 * Setter method to elements variable
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.0
	 */
	public function setElements() 
	{
		$subject = $this->getSubject();
		
		$elements = Array();
		$mainAuxLink = ['thumb', 'title', 'description'];
		$secondaryAuxLink = ['subject', 'creator', 'date', 'format', 'coverage', 'publisher', 'identifier', 'language', 'type', 'contributor', 'relation', 'rights', 'source'];

		$this->setElementName($elements, 'name');
		$this->setElementType($elements, 'type');
		$this->setElementTextFormat($elements, 'text_format');
		$this->setElementFormatToLongText($elements, 'format_long_text');
		$this->setElementAjaxUpload($elements, 'ajax_upload');
		$this->setElementFormat($elements, 'format');
		$this->setElementOptsDropdown($elements, 'options_dropdown');
		$this->setElementMultiSelect($elements, 'multi_select');
		$this->setElementList($elements, 'listas');
		$this->setElementLabel($elements, 'label');
		$this->setElementFather($elements, 'father');
		$this->setElementTags($elements, 'tags');
		$this->setElementMultiRelations($elements, 'multi_relation');
		$this->setElementAccessRating($elements, 'access_rating');
		$this->setElementUseFilter($elements, 'use_filter');
		$this->setElementsAuxLink($elements, 'mainAuxLink', $mainAuxLink);
		$this->setElementLabelAdvancedLink($elements, 'label_advanced_link');
		$this->setElementsAuxLink($elements, 'secondaryAuxLink', $secondaryAuxLink);
		$this->setElementShowDownThumb($elements, 'show_down_thumb');
		$this->setElementShowInList($elements, 'show_in_list');
		$this->setElementWidthField($elements, 'width_field');
		$this->setElementWhiteSpace($elements, 'white_space');
		$this->setElementRequired($elements, 'required');
		$this->setElementOrderingElements($elements, 'ordering_elements');
		$this->setElementRelatedList($elements, 'related_list');
		$this->setElementRelatedListElement($elements, 'related_list_element');
		$this->setElementTrash($elements, 'trash');

		$this->elements = $elements;
	}

	/**
	 * Setter method to elements list variable
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.0
	 */
	public function setElementsList() 
	{
		$subject = $this->getSubject();
		$elementsList = Array();

		$this->setElementNameList($elementsList, 'name_list');
		$this->setElementThumbList($elementsList, 'thumb_list');
		$this->setElementDescriptionList($elementsList, 'description_list');
		$this->setElementNameFormList($elementsList, 'name_form');
		$this->setElementUrlList($elementsList, 'url_list');
		$this->setElementOrderingList($elementsList, 'ordering_list');
		$this->setElementOrderingTypeList($elementsList, 'ordering_type_list');
		$this->setElementVisibilityList($elementsList, 'visibility_list');
		$this->setElementAdminsList($elementsList, 'admins_list');
		$this->setElementOwnerList($elementsList, 'owner_list');
		$this->setElementWidthList($elementsList, 'width_list');
		$this->setElementLayoutMode($elementsList, 'layout_mode');
		$this->setElementComparisonList($elementsList, 'comparison_list');
		$this->setElementWorkflowList($elementsList, 'workflow_list');
		$this->setElementApproveByVotesList($elementsList, 'approve_by_votes_list');
		$this->setElementVotesToApproveList($elementsList, 'votes_to_approve_list');
		$this->setElementVotesToDisapproveList($elementsList, 'votes_to_disapprove_list');
		$this->setElementCollab($elementsList, 'collab_list');
		$this->setElementTrashList($elementsList, 'trash_list');

		$this->elementsList = $elementsList;
	}

	/**
	 * Setter method to list name element
	 *
	 * @param   	Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.0
	 */
	private function setElementNameList(&$elements, $nameElement) 
	{
		$listModel = $this->getListModel();
		$subject = $this->getSubject();

		$tableList = $listModel->getTable();
		$val = $tableList->get('label');

		$id = $this->prefixEl . '___' . $nameElement;
		$dEl = new stdClass;

		// Options to set up the element
		$dEl->attributes = Array(
			'type' => 'text',
			'id' => $id,
			'name' => $id,
			'size' => 0,
			'maxlength' => '255',
			'class' => 'form-control fabrikinput inputbox text input-list',
			'value' => $val
		);

		$classField = new PlgFabrik_ElementField($subject);
		$elements[$id]['objField'] = $classField->getLayout('form');
		$elements[$id]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$id]['dataLabel'] = $this->getDataLabel(
			$id, 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LIST_NAME_LABEL'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LIST_NAME_DESC'), 
		);
		$elements[$id]['dataField'] = $dEl;
	}

	/**
	 * Setter method to description element of the list
	 *
	 * @param   	Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.3.4
	 */
	private function setElementThumbList(&$elements, $nameElement)
	{
		$listModelModal = new FabrikFEModelList();
		$db = Factory::getContainer()->get('DatabaseDriver');

		$formData = $this->getFormData();
		$listModel = $this->getListModel();

		$modalParams = json_decode($this->getModalParams(), true);
		$id = $db->getPrefix() . $this->dbTableNameModal . '___' . $nameElement;

		// Find the actual thumb
		$query = $db->getQuery(true);
		$query->select($db->qn('miniatura'))->from($db->qn('adm_cloner_listas'))->where($db->qn('id_lista') . ' = ' . $db->q($this->listId));
		$db->setQuery($query);
		$value = $db->loadResult();

		$listModelModal->setId($modalParams['list']);
		$formModelModal = $listModelModal->getFormModel();
		$formModelModal->getData();
		$formModelModal->getGroupsHiarachy();
		$elementsModal = $listModelModal->getElements('id');
		$idEl = $modalParams['elementsId'][$nameElement];

		$objFileupload = $elementsModal[$idEl];
		$objFileupload->setEditable(true);
		$objFileupload->reset();

		$elements[$id]['objField'] = $objFileupload;
		$elements[$id]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$id]['dataLabel'] = $this->getDataLabel(
			$id,
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LIST_THUMB_LABEL'),
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LIST_THUMB_DESC'),
			Array(),
			false,
			'list'
		);
		$elements[$id]['dataField'] = Array($id => $value, $id.'_raw' => $value);
	}

	/**
	 * Setter method to description element of the list
	 *
	 * @param   	Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.0
	 */
	private function setElementDescriptionList(&$elements, $nameElement) 
	{
		$listModel = $this->getListModel();
		$subject = $this->getSubject();

		$tableList = $listModel->getTable();
		$val = $tableList->get('introduction');

		$id = $this->prefixEl . '___' . $nameElement;
		$dEl = new stdClass;

		// Options to set up the element
		$rows = 10;
		$cols = 60;
		$editor = Editor::getInstance($this->config->get('editor'));
		$dEl->editor = $editor->display($nameElement, $val, '100%', 100+$rows * 15, $cols, $rows, true, $id, 'com_fabrik');

		$classField = new PlgFabrik_ElementTextarea($subject);
		$elements[$id]['objField'] = $classField->getLayout('wysiwyg');
		$elements[$id]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$id]['dataLabel'] = $this->getDataLabel(
			$id,
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LIST_DESCRIPTION_LABEL'),
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LIST_DESCRIPTION_DESC'),
		);
		$elements[$id]['dataField'] = $dEl;
	}

	/**
	 * Setter method of the form name element
	 *
	 * @param   	Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.3.2
	 */
	private function setElementNameFormList(&$elements, $nameElement) 
	{
		$listModel = $this->getListModel();
		$subject = $this->getSubject();

		$tableForm = $listModel->getFormModel()->getTable();
		$val = $tableForm->get('label');

		$id = $this->prefixEl . '___' . $nameElement;
		$dEl = new stdClass;

		// Options to set up the element
		$dEl->attributes = Array(
			'type' => 'text',
			'id' => $id,
			'name' => $id,
			'size' => 0,
			'maxlength' => '255',
			'class' => 'form-control fabrikinput inputbox text input-list',
			'value' => $val
		);

		$classField = new PlgFabrik_ElementField($subject);
		$elements[$id]['objField'] = $classField->getLayout('form');
		$elements[$id]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$id]['dataLabel'] = $this->getDataLabel(
			$id, 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LIST_FORM_NAME_LABEL'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LIST_FORM_NAME_DESC'), 
		);
		$elements[$id]['dataField'] = $dEl;
	}

	/**
	 * Setter method to ordering element
	 *
	 * @param   	Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.0
	 */
	private function setElementOrderingList(&$elements, $nameElement) 
	{
		$listModel = $this->getListModel();
		$formModel = $listModel->getFormModel();
		$subject = $this->getSubject();

		$id = $this->prefixEl . '___' . $nameElement;
		$dEl = new stdClass();

		// Options to set up the element
		$selected = $listModel->getOrderBys();
		$dEl->options = $formModel->getElementOptions(false, 'id', true, false);
		$dEl->name = $id;
		$dEl->id = $id;
		$dEl->selected = $selected;
		$dEl->multiple = '0';
		$dEl->attribs = 'class="fabrikinput form-select input-medium input-list"';
		$dEl->multisize = '';

		$classDropdown = new PlgFabrik_ElementDropdown($subject);
		$elements[$id]['objField'] = $classDropdown->getLayout('form');
		$elements[$id]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$id]['dataLabel'] = $this->getDataLabel(
			$id,
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_ORDERING_LABEL'),
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_ORDERING_DESC'),
		);
		$elements[$id]['dataField'] = $dEl;
	}

	/**
	 * Setter method to type ordering element
	 *
	 * @param   	Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.0
	 */
	private function setElementOrderingTypeList(&$elements, $nameElement) 
	{
		$listModel = $this->getListModel();
		$formModel = $listModel->getFormModel();
		$subject = $this->getSubject();

		$tableList = $listModel->getTable();
		$val = json_decode($tableList->get('order_dir'), true);

		$id = $this->prefixEl . '___' . $nameElement;
		$dEl = new stdClass();

		// Options to set up the element
		$dEl->options = $this->optionsElements(Array('ASC' => 'Crescente', 'DESC' => 'Decrescente'));
		$dEl->name = $id;
		$dEl->id = $id;
		$dEl->selected = $val;
		$dEl->multiple = '0';
		$dEl->attribs = 'class="fabrikinput form-select input-medium input-list"';
		$dEl->multisize = '';

		$classDropdown = new PlgFabrik_ElementDropdown($subject);
		$elements[$id]['objField'] = $classDropdown->getLayout('form');
		$elements[$id]['dataField'] = $dEl;
	}

	/**
	 * Setter method to collaboration element
	 *
	 * @param   	Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.0
	 */
	private function setElementCollab(&$elements, $nameElement) 
	{
		$listModel = $this->getListModel();
		$formModel = $listModel->getFormModel();
		$subject = $this->getSubject();

		$tableList = $listModel->getTable();
		$val = Array($formModel->getParams()->get('approve_for_own_records'));

		$id = $this->prefixEl . '___' . $nameElement;
		$dEl = new stdClass();

		// Options to set up the element
		$dEl->options = $this->optionsElements(Array(
			'0' => Text::_("PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_COLLAB_OPTION_0"),
			'1' => Text::_("PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_COLLAB_OPTION_1")
		));
		$dEl->name = $id;
		$dEl->id = $id;
		$dEl->selected = $val;
		$dEl->multiple = '0';
		$dEl->attribs = 'class="fabrikinput form-select input-medium input-list"';
		$dEl->multisize = '';

		$classDropdown = new PlgFabrik_ElementDropdown($subject);
		$elements[$id]['objField'] = $classDropdown->getLayout('form');
		$elements[$id]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);
		$elements[$id]['dataField'] = $dEl;
		$elements[$id]['dataLabel'] = $this->getDataLabel(
			$id,
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_COLLAB_LABEL'),
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_COLLAB_DESC'),
		);
	}

	/**
	 * Setter method to workflow element of the list
	 *
	 * @param   	Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.3
	 */
	private function setElementWorkflowList(&$elements, $nameElement) 
	{
		$listModel = $this->getListModel();
		$subject = $this->getSubject();

		$value = (int) $listModel->getParams()->get('workflow_list', '1');

		$id = $this->prefixEl . '___' . $nameElement;
		$dEl = new stdClass();

		// Options to set up the element
		$opts = Array(
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_NO'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_YES')
		);
		$elements[$id]['objField'] = new FileLayout('joomla.form.field.radio.switcher');
		$elements[$id]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$id]['dataLabel'] = $this->getDataLabel(
			$id,
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_WORKFLOW_LIST_LABEL'),
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_WORKFLOW_LIST_DESC'),
		);
		$elements[$id]['dataField'] = Array(
			'value' => $value,
			'options' => $this->optionsElements($opts),
			'name' => $id,
			'id' => $id,
			'class' => 'fbtn-default fabrikinput input-list',
			'dataAttribute' => 'style="margin-bottom: 0px; padding: 0px"',
		);
		$elements[$id]['cssElement'] = 'border-top: #ccc solid 2px;';
	}

	/**
	 * Setter method to approve by votes element of the list
	 *
	 * @param   	Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.3.1
	 */
	private function setElementApproveByVotesList(&$elements, $nameElement) 
	{
		$listModel = $this->getListModel();
		$subject = $this->getSubject();

		$value = (int) $listModel->getFormModel()->getParams()->get('workflow_approval_by_vote', '0');

		$id = $this->prefixEl . '___' . $nameElement;
		$dEl = new stdClass();

		// Options to set up the element
		$opts = Array(
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_NO'),
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_YES')
		);
		$elements[$id]['objField'] = new FileLayout('joomla.form.field.radio.switcher');
		$elements[$id]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$id]['dataLabel'] = $this->getDataLabel(
			$id,
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_APPROVE_BY_VOTES_LIST_LABEL'),
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_APPROVE_BY_VOTES_LIST_DESC'),
		);
		$elements[$id]['dataField'] = Array(
			'value' => $value,
			'options' => $this->optionsElements($opts),
			'name' => $id,
			'id' => $id,
			'class' => 'fbtn-default fabrikinput input-list',
			'dataAttribute' => 'style="margin-bottom: 0px; padding: 0px"',
		);
	}

		/**
	 * Setter method to votes to approve element of the list
	 *
	 * @param   	Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.3.1
	 */
	private function setElementVotesToApproveList(&$elements, $nameElement) 
	{
		$listModel = $this->getListModel();
		$subject = $this->getSubject();

		$value = (int) $listModel->getFormModel()->getParams()->get('workflow_votes_to_approve', '2');
		$value = $value <= 1 ? 2 : $value;

		$id = $this->prefixEl . '___' . $nameElement;
		$dEl = new stdClass;
		$showOnTypes = ['list-approve_by_votes_list'];

		// Options to set up the element
		$dEl->attributes = Array(
			'type' => 'text',
			'id' => $id,
			'name' => $id,
			'size' => 0,
			'maxlength' => '255',
			'class' => 'form-control fabrikinput inputbox text input-list',
			'value' => $value
		);

		$classField = new PlgFabrik_ElementField($subject);
		$elements[$id]['objField'] = $classField->getLayout('form');
		$elements[$id]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$id]['dataLabel'] = $this->getDataLabel(
			$id,
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_VOTES_TO_APPROVE_LIST_LABEL'),
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_VOTES_TO_APPROVE_LIST_DESC'),
			$showOnTypes,
			false,
			'list'
		);
		$elements[$id]['dataField'] = $dEl;
	}

	/**
	 * Setter method to votes to disapprove element of the list
	 *
	 * @param   	Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.3.1
	 */
	private function setElementVotesToDisapproveList(&$elements, $nameElement) 
	{
		$listModel = $this->getListModel();
		$subject = $this->getSubject();

		$value = (int) $listModel->getFormModel()->getParams()->get('workflow_votes_to_disapprove', '2');
		$value = $value <= 1 ? 2 : $value;

		$id = $this->prefixEl . '___' . $nameElement;
		$dEl = new stdClass;
		$showOnTypes = ['list-approve_by_votes_list'];

		// Options to set up the element
		$dEl->attributes = Array(
			'type' => 'text',
			'id' => $id,
			'name' => $id,
			'size' => 0,
			'maxlength' => '255',
			'class' => 'form-control fabrikinput inputbox text input-list',
			'value' => $value
		);

		$classField = new PlgFabrik_ElementField($subject);
		$elements[$id]['objField'] = $classField->getLayout('form');
		$elements[$id]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$id]['dataLabel'] = $this->getDataLabel(
			$id,
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_VOTES_TO_DISAPPROVE_LIST_LABEL'),
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_VOTES_TO_DISAPPROVE_LIST_DESC'),
			$showOnTypes,
			false,
			'list'
		);
		$elements[$id]['dataField'] = $dEl;
	}

	/**
	 * Setter method to width element of the list
	 *
	 * @param   	Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.1.1
	 */
	private function setElementWidthList(&$elements, $nameElement) 
	{
		$listModel = $this->getListModel();
		$subject = $this->getSubject();

		$listWidth = (int) $listModel->getParams()->get('width_list');

		$id = $this->prefixEl . '___' . $nameElement;
		$dEl = new stdClass;

		// Options to set up the element
		$dEl->attributes = Array(
			'type' => 'text',
			'id' => $id,
			'name' => $id,
			'size' => 0,
			'maxlength' => '255',
			'class' => 'form-control fabrikinput inputbox text input-list',
			'value' => $listWidth == 0 ? 100 : $listWidth
		);

		$classField = new PlgFabrik_ElementField($subject);
		$elements[$id]['objField'] = $classField->getLayout('form');
		$elements[$id]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$id]['dataLabel'] = $this->getDataLabel(
			$id,
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_WIDTH_LIST_LABEL'),
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_WIDTH_LIST_DESC'),
		);
		$elements[$id]['dataField'] = $dEl;
	}

	/**
	 * Setter method to layout mode element
	 *
	 * @param   	Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.1.1
	 */
	private function setElementLayoutMode(&$elements, $nameElement) 
	{
		$listModel = $this->getListModel();
		$subject = $this->getSubject();
		$elsList = $listModel->getElements('id');

		$layoutMode = (int) $listModel->getParams()->get('layout_mode');
		$val = Array($layoutMode);

		$id = $this->prefixEl . '___' . $nameElement;
		$dEl = new stdClass();

		// Options to set up the element
		$options = Array(
			'0' => Text::_("PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LAYOUT_MODE_OPTION_0"),
			'1' => Text::_("PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LAYOUT_MODE_OPTION_1")
		);

		foreach ($elsList as $el) {
			$params = $el->getParams();
			if(
				str_contains($el->getName(), 'Databasejoin') &&
				$params->get('database_join_display_type') == 'auto-complete' && 
				$params->get('join_db_name') == $listModel->getTable()->get('db_table_name') && 
				($params->get('database_join_display_style') == 'both-treeview-autocomplete' || $params->get('database_join_display_style') == 'only-treeview')
			) {
				$options['2'] = Text::_("PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LAYOUT_MODE_OPTION_2");
			}
		}
		
		if($listModel->canShowTutorialTemplate()) {
			$options['3'] = Text::_("PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LAYOUT_MODE_OPTION_3");
		}

		$dEl->options = $this->optionsElements($options);
		$dEl->name = $id;
		$dEl->id = $id;
		$dEl->selected = $val;
		$dEl->multiple = '0';
		$dEl->attribs = 'class="fabrikinput form-select input-medium input-list"';
		$dEl->multisize = '';

		$classDropdown = new PlgFabrik_ElementDropdown($subject);
		$elements[$id]['objField'] = $classDropdown->getLayout('form');
		$elements[$id]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);
		$elements[$id]['dataField'] = $dEl;
		$elements[$id]['dataLabel'] = $this->getDataLabel(
			$id,
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LAYOUT_MODE_LABEL'),
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LAYOUT_MODE_DESC'),
		);
	}

	/**
	 * Setter method to comparison element
	 *
	 * @param   	Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.3
	 */
	private function setElementComparisonList(&$elements, $nameElement) 
	{
		$listModel = $this->getListModel();
		$subject = $this->getSubject();

		$plgComparison = array_search('comparison', $listModel->getParams()->get('plugins'));
		$value = $plgComparison && (bool) $listModel->getParams()->get('plugin_state')[$plgComparison];

		$id = $this->prefixEl . '___' . $nameElement;
		$dEl = new stdClass();

		// Options to set up the element
		$opts = Array(
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_NO'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_YES')
		);
		$elements[$id]['objField'] = new FileLayout('joomla.form.field.radio.switcher');
		$elements[$id]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$id]['dataLabel'] = $this->getDataLabel(
			$id,
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_COMPARISON_LABEL'),
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_COMPARISON_DESC'),
		);
		$elements[$id]['dataField'] = Array(
			'value' => $value,
			'options' => $this->optionsElements($opts),
			'name' => $id,
			'id' => $id,
			'class' => 'fbtn-default fabrikinput input-list',
			'dataAttribute' => 'style="margin-bottom: 0px; padding: 0px"',
		);
	}

	/**
	 * Setter method to visibility of the list
	 *
	 * @param   	Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.1.2
	 */
	private function setElementVisibilityList(&$elements, $nameElement) 
	{
		$listModel = $this->getListModel();
		$subject = $this->getSubject();

		$accessLevel = (int) $listModel->getTable()->get('access');
		$val = $accessLevel > 2 ? '3' : $accessLevel;
		$id = $this->prefixEl . '___' . $nameElement;
		$dEl = new stdClass();

		// Options to set up the element
		$dEl->options = $this->optionsElements(Array(
			'1' => Text::_("PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_VISIBILITY_LIST_OPTION_0"),	// By default public access is 1
			'2' => Text::_("PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_VISIBILITY_LIST_OPTION_1"),	// By default registered access is 2
			'3' => Text::_("PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_VISIBILITY_LIST_OPTION_2")
		));
		$dEl->name = $id;
		$dEl->id = $id;
		$dEl->selected = (array) $val;
		$dEl->multiple = '0';
		$dEl->attribs = 'class="fabrikinput form-select input-medium input-list"';
		$dEl->multisize = '';

		$classDropdown = new PlgFabrik_ElementDropdown($subject);
		$elements[$id]['objField'] = $classDropdown->getLayout('form');
		$elements[$id]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);
		$elements[$id]['dataField'] = $dEl;
		$elements[$id]['dataLabel'] = $this->getDataLabel(
			$id,
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_VISIBILITY_LIST_LABEL'),
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_VISIBILITY_LIST_DESC'),
		);
	}

	/**
	 * Setter method to list admins element
	 *
	 * @param   	Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 *
	 * @return  	Null
	 *
	 * @since 		version 4.1.2
	 */
	private function setElementAdminsList(&$elements, $nameElement)
	{
		$subject = $this->getSubject();
		$objDatabasejoin = new PlgFabrik_ElementDatabasejoin($subject);

		$id = $this->prefixEl . '___' . $nameElement;

		$elContextModelElement = Array('name' => 'admins_list');
		$elContextTableElement = Array('label' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_ADMINS_LIST_LABEL'));
		$elContextTableJoin = Array('table_join' => '#__users', 'table_key' => 'id');
		$params = new Registry(json_encode(Array(
			'database_join_display_type' => 'checkbox',
			'database_join_display_style' => 'only-autocomplete',
			'join_db_name' => '#__users',
			'join_val_column' => 'name',
			'join_key_column' => 'id',
			'database_join_show_please_select' => '1',
			'dbjoin_autocomplete_rows' => 10,
			'database_join_where_sql' => ''
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

		$elements[$id]['objField'] = $objDatabasejoin;
		$elements[$id]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$id]['dataLabel'] = $this->getDataLabel(
			$id, 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_ADMINS_LIST_LABEL'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_ADMINS_LIST_DESC'), 
			Array(),
			false,
			'list'
		);
		$elements[$id]['dataField'] = Array();
	}

	/**
	 * Setter method to owner list element
	 *
	 * @param   	Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 *
	 * @return  	Null
	 *
	 * @since 		version 4.3.2
	 */
	private function setElementOwnerList(&$elements, $nameElement) 
	{
		$listModelModal = new FabrikFEModelList();
		$db = Factory::getContainer()->get('DatabaseDriver');

		$formData = $this->getFormData();
		$subject = $this->getSubject();
		$listModel = $this->getListModel();

		$modalParams = json_decode($this->getModalParams(), true);
		$id = $db->getPrefix() . $this->dbTableNameModal . '___' . $nameElement;
		$value = $listModel->getTable()->get('created_by');

		$listModelModal->setId($modalParams['list']);
		$formModelModal = $listModelModal->getFormModel();
		$formModelModal->getData();
		$formModelModal->getGroupsHiarachy();
		$elementsModal = $listModelModal->getElements('id');
		$idEl = $modalParams['elementsId'][$nameElement];

		$objDatabasejoin = $elementsModal[$idEl];
		$objDatabasejoin->setEditable(true);
		$objDatabasejoin->reset();

		$elements[$id]['objField'] = $objDatabasejoin;
		$elements[$id]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$id]['dataLabel'] = $this->getDataLabel(
			$id,
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_OWNER_LIST_LABEL'),
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_OWNER_LIST_DESC'),
			Array(),
			false,
			'list'
		);
		$elements[$id]['dataField'] = Array($id => $value, $id.'_raw' => $value);
	}

	/**
	 * Setter method to trash element of the list
	 *
	 * @param   	Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.1.2
	 */
	private function setElementTrashList(&$elements, $nameElement) 
	{
		$subject = $this->getSubject();
		$id = $this->prefixEl . '___' . $nameElement;
		$dEl = new stdClass();

		// Options to set up the element
		$opts = Array(
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_NO'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_YES')
		);
		$elements[$id]['objField'] = new FileLayout('joomla.form.field.radio.switcher');
		$elements[$id]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$id]['dataLabel'] = $this->getDataLabel(
			$id,
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TRASH_LIST_LABEL'),
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TRASH_LIST_DESC'),
		);
		$elements[$id]['dataField'] = Array(
			'value' => 0,
			'options' => $this->optionsElements($opts),
			'name' => $id,
			'id' => $id,
			'class' => 'fbtn-default fabrikinput input-list',
			'dataAttribute' => 'style="margin-bottom: 0px; padding: 0px"',
		);
		$elements[$id]['cssElement'] = 'border-top: #ccc solid 2px;';
	}

	/**
	 * Method to get the layout options.
	 * Method copied from administrator/components/com_fabrik/models/fields/fabriktemplate.php (getOptions) because couldnt instanciate the class correctly
	 * 
	 * @return  	Array
	 * 
	 * @since 		version 4.0
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
	 * @param   	Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.0
	 */
	private function setElementName(&$elements, $nameElement) 
	{
		$formData = $this->getFormData();
		$subject = $this->getSubject();

		$idEasy = $this->prefixEl . '___' . $nameElement;
		$id = $idEasy . ($this->getRequestWorkflow() ? '_wfl' : '') . ($this->getRequestWorkflowOrig() ? '_orig' : '');
		$value = $formData[$idEasy];
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
		$this->getRequestWorkflow() ? $dEl->attributes['disabled'] = 'disabled' : '';

		$classField = new PlgFabrik_ElementField($subject);
		$elements[$idEasy]['objField'] = $classField->getLayout('form');
		$elements[$idEasy]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$idEasy]['dataLabel'] = $this->getDataLabel(
			$id, 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_NAME_LABEL') . ($this->getRequestWorkflowOrig() ? ' - Original' : ''), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_NAME_DESC'), 
		);
		$elements[$idEasy]['dataField'] = $dEl;
	}

	/**
	 * Setter method to type element
	 *
	 * @param   	Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.0
	 */
	private function setElementType(&$elements, $nameElement) 
	{
		$formData = $this->getFormData();
		$subject = $this->getSubject();
	
		$idEasy = $this->prefixEl . '___' . $nameElement;
		$id = $idEasy . ($this->getRequestWorkflow() ? '_wfl' : '') . ($this->getRequestWorkflowOrig() ? '_orig' : '');
		$value = $formData[$idEasy];
		$dEl = new stdClass();
		$showOnTypes = ['text', 'longtext', 'file', 'date', 'dropdown', 'autocomplete', 'treeview', 'related_list', 'rating', 'youtube', 'link', 'thumbs', 'tags'];

		// Options to set up the element
		$opts = Array(
			'text' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TYPE_TEXT'),
			'longtext' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TYPE_LONGTEXT'),
			'file' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TYPE_FILE'),
			'date' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TYPE_DATE'),
			'dropdown' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TYPE_DROPDOWN'),
			'autocomplete' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TYPE_AUTOCOMPLETE'),
			'treeview' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TYPE_TREEVIEW'),
			'related_list' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TYPE_RELATED_LIST'),
			'rating' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TYPE_RATING'),
			'youtube' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TYPE_YOUTUBE'),
			'link' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TYPE_LINK'),
			'thumbs' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TYPE_THUMBS'),
			'tags' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TYPE_TAGS'),
			'internalid' => 'internalid',
			'user' => 'user'
		);
		$dEl->options = $this->optionsElements($opts);
		$dEl->name = $id;
		$dEl->id = $id;
		$dEl->selected = Array($value);
		$dEl->multiple = '0';
		$dEl->attribs = 'class="fabrikinput form-select input-medium"' . ($this->getRequestWorkflow() ? ' disabled' : '');
		$dEl->multisize = '';

		$classDropdown = new PlgFabrik_ElementDropdown($subject);
		$elements[$idEasy]['objField'] = $classDropdown->getLayout('form');
		$elements[$idEasy]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$idEasy]['dataLabel'] = $this->getDataLabel(
			$id,
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TYPE_LABEL') . ($this->getRequestWorkflowOrig() ? ' - Original' : ''),
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TYPE_DESC'),
			$showOnTypes,
			false
		);
		$elements[$idEasy]['dataField'] = $dEl;
	}

	/**
	 * Method that set up the options(labels and values) to elements
	 *
	 * @param		Array		$opts		Options with value and label
	 * 
	 * @return  	Array
	 * 
	 * @since 		version 4.0
	 */
	private function optionsElements($opts)
	{
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
	 * Setter method to show down thumb element
	 *
	 * @param   	Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.3.1
	 */
	private function setElementShowDownThumb(&$elements, $nameElement) 
	{
		$formData = $this->getFormData();
		$subject = $this->getSubject();

		$idEasy = $this->prefixEl . '___' . $nameElement;
		$id = $idEasy . ($this->getRequestWorkflow() ? '_wfl' : '') . ($this->getRequestWorkflowOrig() ? '_orig' : '');
		$value = $formData[$idEasy] == 'true' || $formData[$idEasy] ? 1 : 0;

		$dEl = new stdClass();
		$showOnTypes = ['thumbs'];

		// Options to set up the element
		$opts = Array(
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_NO'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_YES')
		);
		$elements[$idEasy]['objField'] = new FileLayout('joomla.form.field.radio.switcher');
		$elements[$idEasy]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$idEasy]['dataLabel'] = $this->getDataLabel(
			$id, 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_SHOW_DOWN_THUMB_LABEL') . ($this->getRequestWorkflowOrig() ? ' - Original' : ''), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_SHOW_DOWN_THUMB_DESC'),
			$showOnTypes, 
			false
		);
		$elements[$idEasy]['dataField'] = Array(
			'value' => $value,
			'options' => $this->optionsElements($opts),
			'name' => $id,
			'id' => $id,
			'class' => 'fbtn-default fabrikinput',
			'dataAttribute' => 'style="margin-bottom: 0px; padding: 0px"',
		);
		$this->getRequestWorkflow() ? $elements[$idEasy]['dataField']['disabled'] = 'disabled' : '';
	}

	/**
	 * Setter method to show in list element
	 *
	 * @param   	Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.0.1
	 */
	private function setElementShowInList(&$elements, $nameElement) 
	{
		$formData = $this->getFormData();
		$subject = $this->getSubject();

		$idEasy = $this->prefixEl . '___' . $nameElement;
		$id = $idEasy . ($this->getRequestWorkflow() ? '_wfl' : '') . ($this->getRequestWorkflowOrig() ? '_orig' : '');
		$value = $formData[$idEasy] == 'true' || $formData[$idEasy] ? 1 : 0;

		$dEl = new stdClass();
		$showOnTypes = ['text', 'longtext', 'file', 'date', 'dropdown', 'autocomplete', 'treeview', 'rating', 'thumbs', 'tags', 'youtube', 'link', 'user', 'internalid'];

		// Options to set up the element
		$opts = Array(
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_NO'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_YES')
		);
		$elements[$idEasy]['objField'] = new FileLayout('joomla.form.field.radio.switcher');
		$elements[$idEasy]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$idEasy]['dataLabel'] = $this->getDataLabel(
			$id, 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_SHOW_IN_LIST_LABEL') . ($this->getRequestWorkflowOrig() ? ' - Original' : ''), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_SHOW_IN_LIST_DESC'),
			$showOnTypes, 
			false
		);
		$elements[$idEasy]['dataField'] = Array(
			'value' => $value,
			'options' => $this->optionsElements($opts),
			'name' => $id,
			'id' => $id,
			'class' => 'fbtn-default fabrikinput',
			'dataAttribute' => 'style="margin-bottom: 0px; padding: 0px"',
		);
		$this->getRequestWorkflow() ? $elements[$idEasy]['dataField']['disabled'] = 'disabled' : '';
	}

	/**
	 * Setter method to width field element
	 *
	 * @param   	Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.0.2
	 */
	private function setElementWidthField(&$elements, $nameElement) 
	{
		$formData = $this->getFormData();
		$subject = $this->getSubject();

		$idEasy = $this->prefixEl . '___' . $nameElement;
		$id = $idEasy . ($this->getRequestWorkflow() ? '_wfl' : '') . ($this->getRequestWorkflowOrig() ? '_orig' : '');
		$value = $formData[$idEasy];
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
		$this->getRequestWorkflow() ? $dEl->attributes['disabled'] = 'disabled' : '';

		$classField = new PlgFabrik_ElementField($subject);
		$elements[$idEasy]['objField'] = $classField->getLayout('form');
		$elements[$idEasy]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$idEasy]['dataLabel'] = $this->getDataLabel(
			$id, 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_WIDTH_FIELD_LABEL') . ($this->getRequestWorkflowOrig() ? ' - Original' : ''), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_WIDTH_FIELD_DESC'),
			$showOnTypes, 
			false
		);
		$elements[$idEasy]['dataField'] = $dEl;
	}

	/**
	 * Setter method to ordering elements element
	 *
	 * @param   	Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.0.2
	 */
	private function setElementOrderingElements(&$elements, $nameElement) 
	{
		$formData = $this->getFormData();
		$listModel = $this->getListModel();
		$subject = $this->getSubject();

		$idEasy = $this->prefixEl . '___' . $nameElement;
		$id = $idEasy . ($this->getRequestWorkflow() ? '_wfl' : '') . ($this->getRequestWorkflowOrig() ? '_orig' : '');
		$value = $formData[$idEasy];
		$dEl = new stdClass();
		$showOnTypes = ['text', 'longtext', 'file', 'date', 'dropdown', 'autocomplete', 'treeview', 'rating', 'thumbs', 'tags', 'youtube', 'link', 'user', 'internalid'];

		// Options to set up the element
		$opts = $this->getElementsToOrderingInList();

		$dEl->options = $this->optionsElements($opts);
		$dEl->name = $id;
		$dEl->id = $id;
		$dEl->selected = Array($value);
		$dEl->multiple = '0';
		$dEl->attribs = 'class="fabrikinput form-select input-medium"' . ($this->getRequestWorkflow() ? ' disabled' : '');
		$dEl->multisize = '';

		$classDropdown = new PlgFabrik_ElementDropdown($subject);
		$elements[$idEasy]['objField'] = $classDropdown->getLayout('form');
		$elements[$idEasy]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$idEasy]['dataLabel'] = $this->getDataLabel(
			$id, 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_ORDERING_ELEMENTS_LABEL') . ($this->getRequestWorkflowOrig() ? ' - Original' : ''), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_ORDERING_ELEMENTS_DESC'),
			$showOnTypes, 
			false
		);
		$elements[$idEasy]['dataField'] = $dEl;
	}

	/**
     * Get the elements that are in list to ordering it
	 * 
     * @return  	Array
     *
     * @since   	version 4.0.2
     */
    public function getElementsToOrderingInList()
    {
		$listModel = $this->getListModel();
		$listModel->setId($this->getListId());

		$options = Array();
		$options['-1'] = Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_ORDERING_ELEMENTS_OPTION_FIRST');
		foreach ($listModel->getElements('id') as $id => $element) {
			if($element->getName() != 'PlgFabrik_ElementIp' && $element->getElement()->name != 'indexing_text') {
				$options[$id] = $element->getElement()->label;
			}
		}
		$options['-2'] = Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_ORDERING_ELEMENTS_OPTION_LAST');

		return $options;
	}

	/**
	 * Setter method to white space element
	 * 
	 * @param   	Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 * 
	 * @return  	Null
	 * 
	 * @since 		version 4.3.1
	 */
	private function setElementWhiteSpace(&$elements, $nameElement) 
	{
		$formData = $this->getFormData();
		$subject = $this->getSubject();

		$idEasy = $this->prefixEl . '___' . $nameElement;
		$id = $idEasy . ($this->getRequestWorkflow() ? '_wfl' : '') . ($this->getRequestWorkflowOrig() ? '_orig' : '');
		$value = $formData[$idEasy] == 'true' || $formData[$idEasy] ? 1 : 0;

		$dEl = new stdClass();
		$showOnTypes = ['element-show_in_list'];

		// Options to set up the element
		$opts = Array(
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_NO'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_YES')
		);
		$elements[$idEasy]['objField'] = new FileLayout('joomla.form.field.radio.switcher');
		$elements[$idEasy]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$idEasy]['dataLabel'] = $this->getDataLabel(
			$id, 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_WHITE_SPACE_LABEL') . ($this->getRequestWorkflowOrig() ? ' - Original' : ''), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_WHITE_SPACE_DESC'),
			$showOnTypes, 
			false
		);
		$elements[$idEasy]['dataField'] = Array(
			'value' => $value,
			'options' => $this->optionsElements($opts),
			'name' => $id,
			'id' => $id,
			'class' => 'fbtn-default fabrikinput',
			'dataAttribute' => 'style="margin-bottom: 0px; padding: 0px"',
		);
		$this->getRequestWorkflow() ? $elements[$idEasy]['dataField']['disabled'] = 'disabled' : '';
	}

	/**
	 * Setter method to required element
	 *
	 * @param   	Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.0
	 */
	private function setElementRequired(&$elements, $nameElement) 
	{
		$formData = $this->getFormData();
		$subject = $this->getSubject();

		$idEasy = $this->prefixEl . '___' . $nameElement;
		$id = $idEasy . ($this->getRequestWorkflow() ? '_wfl' : '') . ($this->getRequestWorkflowOrig() ? '_orig' : '');
		$value = $formData[$idEasy] == 'true' || $formData[$idEasy] ? 1 : 0;

		$dEl = new stdClass();
		$showOnTypes = ['text', 'longtext', 'file', 'date', 'dropdown', 'autocomplete', 'treeview', 'tags', 'youtube', 'link'];

		// Options to set up the element
		$opts = Array(
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_NO'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_YES')
		);
		$elements[$idEasy]['objField'] = new FileLayout('joomla.form.field.radio.switcher');
		$elements[$idEasy]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$idEasy]['dataLabel'] = $this->getDataLabel(
			$id, 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_REQUIRED_LABEL') . ($this->getRequestWorkflowOrig() ? ' - Original' : ''), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_REQUIRED_DESC'),
			$showOnTypes, 
			false
		);
		$elements[$idEasy]['dataField'] = Array(
			'value' => $value,
			'options' => $this->optionsElements($opts),
			'name' => $id,
			'id' => $id,
			'class' => 'fbtn-default fabrikinput',
			'dataAttribute' => 'style="margin-bottom: 0px; padding: 0px"',
		);
		$this->getRequestWorkflow() ? $elements[$idEasy]['dataField']['disabled'] = 'disabled' : '';
	}

	/**
	 * Setter method to related list element
	 *
	 * @param   	Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.1.0
	 */
	private function setElementRelatedList(&$elements, $nameElement) 
	{
		$formData = $this->getFormData();
		$subject = $this->getSubject();

		$idEasy = $this->prefixEl . '___' . $nameElement;
		$id = $idEasy . ($this->getRequestWorkflow() ? '_wfl' : '') . ($this->getRequestWorkflowOrig() ? '_orig' : '');
		$value = $formData[$idEasy];
		$showOnTypes = ['related_list'];
		$dEl = new stdClass();

		// Options to set up the element
		$dEl->options = $this->optionsElements($this->searchRelatedLists());
		$dEl->name = $id;
		$dEl->id = $id;
		$dEl->selected = Array($value);
		$dEl->multiple = '0';
		$dEl->attribs = 'class="fabrikinput form-select input-medium"' . ($this->getRequestWorkflow() ? ' disabled' : '');
		$dEl->multisize = '';

		$classDropdown = new PlgFabrik_ElementDropdown($subject);
		$elements[$idEasy]['objField'] = $classDropdown->getLayout('form');
		$elements[$idEasy]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);
		
		$elements[$idEasy]['dataLabel'] = $this->getDataLabel(
			$id,
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_RELATED_LIST_LABEL') . ($this->getRequestWorkflowOrig() ? ' - Original' : ''),
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_RELATED_LIST_DESC'),
			$showOnTypes,
			false
		);
		$elements[$idEasy]['dataField'] = $dEl;
	}

	/**
	 * Setter method to related list element of the element
	 *
	 * @param   	Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.1.0
	 */
	private function setElementRelatedListElement(&$elements, $nameElement) 
	{
		$formData = $this->getFormData();
		$subject = $this->getSubject();

		$idEasy = $this->prefixEl . '___' . $nameElement;
		$id = $idEasy . ($this->getRequestWorkflow() ? '_wfl' : '') . ($this->getRequestWorkflowOrig() ? '_orig' : '');
		$value = $formData[$idEasy];
		$showOnTypes = ['related_list'];
		$dEl = new stdClass();

		// Options to set up the element
		$dEl->options = $this->optionsElements($this->searchRelatedLists($this->listModel->getTable()->get('db_table_name')));
		$dEl->name = $id;
		$dEl->id = $id;
		$dEl->selected = Array($value);
		$dEl->multiple = '0';
		$dEl->attribs = 'class="fabrikinput form-select input-medium"' . ($this->getRequestWorkflow() ? ' disabled' : '');
		$dEl->multisize = '';

		$classDropdown = new PlgFabrik_ElementDropdown($subject);
		$elements[$idEasy]['objField'] = $classDropdown->getLayout('form');
		$elements[$idEasy]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);
		
		$elements[$idEasy]['dataLabel'] = $this->getDataLabel(
			$id,
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_RELATED_ELEMENT_LIST_LABEL') . ($this->getRequestWorkflowOrig() ? ' - Original' : ''),
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_RELATED_ELEMENT_LIST_DESC'),
			$showOnTypes,
			false
		);
		$elements[$idEasy]['dataField'] = $dEl;
	}

	/**
	 * Method that with cURL call the ajax fields function to return the list elements
	 * 
	 * @return			Array
	 * 
	 * @since 			version 4.2.1
	 * 
	 * @deprecated		since v4.3.1 because rules about show options in link elements changed. Will be removed in 5.0
	 */
	private function callAjaxFields() 
	{
		$optsFormated = Array();
		$url = COM_FABRIK_LIVESITE . 'index.php?option=com_fabrik&format=raw&task=plugin.pluginAjax&g=element&plugin=field&method=ajax_fields&showall=0&t=' . $this->getListId() . '&published=1';

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);

		if (!curl_errno($ch)) {
			$opts = json_decode($response);

			foreach ($opts as $opt) {
				$optsFormated[$opt->value] = $opt->label;
			}
		}

		curl_close($ch);

		return $optsFormated;
	}

	/**
	 * Method that search the related lists in database to render the options to user
	 * 
	 * @param		String			$table			Optional to get the name of join element
	 * 
	 * @return		Array
	 * 
	 * @since 		version 4.1.0
	 */
	private function searchRelatedLists($table='')
	{
		$db = Factory::getContainer()->get('DatabaseDriver');

		$findJoin = false;
		$table == '' ?  $table = $this->db_table_name : $findJoin = true;

		$query = $db->getQuery(true);
		$query->select('DISTINCT l.label AS label, l.id AS id, e.name AS elementJoin, e.label AS labelEl')
			->from($db->qn('#__fabrik_elements') . ' AS e')
			->join('LEFT', $db->qn('#__fabrik_groups') . ' AS g ON g.id = e.group_id')
			->join('LEFT', $db->qn('#__fabrik_formgroup') . ' AS `fg` ON fg.group_id = g.id')
			->join('LEFT', $db->qn('#__fabrik_forms') . ' AS `f` ON f.id = fg.form_id')
			->join('LEFT', $db->qn('#__fabrik_lists') . ' AS `l` ON l.form_id = f.id')
			->where('e.plugin = ' . $db->q('databasejoin'))
			->where('JSON_EXTRACT(e.`params`,"$.join_db_name") = ' . $db->q($table));
		$db->setQuery($query);
		$results = $db->loadObjectList();

		$opts = Array();
		foreach ($results as $list) {
			$findJoin ? $opts[$list->elementJoin] = $list->labelEl : $opts[$list->id] = $list->label;
		}

		return $opts;
	}

	/**
	 * Setter method to trash element
	 *
	 * @param   	Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.0.3
	 */
	private function setElementTrash(&$elements, $nameElement) 
	{
		$formData = $this->getFormData();
		$subject = $this->getSubject();
		
		$idEasy = $this->prefixEl . '___' . $nameElement;
		$id = $idEasy . ($this->getRequestWorkflow() ? '_wfl' : '') . ($this->getRequestWorkflowOrig() ? '_orig' : '');
		$value = $formData[$idEasy] == 'true' || $formData[$idEasy] ? 1 : 0;

		$dEl = new stdClass();
		$showOnTypes = ['text', 'longtext', 'file', 'date', 'dropdown', 'autocomplete', 'treeview', 'rating', 'thumbs', 'related_list', 'tags', 'youtube', 'link'];

		// Options to set up the element
		$opts = Array(
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_NO'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_YES')
		);
		$elements[$idEasy]['objField'] = new FileLayout('joomla.form.field.radio.switcher');
		$elements[$idEasy]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$idEasy]['dataLabel'] = $this->getDataLabel(
			$id,
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TRASH_LABEL') . ($this->getRequestWorkflowOrig() ? ' - Original' : ''),
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TRASH_DESC'),
			$showOnTypes,
			false
		);
		$elements[$idEasy]['dataField'] = Array(
			'value' => $value,
			'options' => $this->optionsElements($opts),
			'name' => $id,
			'id' => $id,
			'class' => 'fbtn-default fabrikinput',
			'dataAttribute' => 'style="margin-bottom: 0px; padding: 0px"',
		);
		$elements[$idEasy]['cssElement'] = 'border-top: #ccc solid 2px;';
		$this->getRequestWorkflow() ? $elements[$idEasy]['dataField']['disabled'] = 'disabled' : '';
	}
	
	/**
	 * Setter method to format element to the long text type
	 *
	 * @param   	Array 	$elements		Reference to all elements
	 * @param		String	$nameElement	Identity of the element
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.1.3
	 */
	private function setElementFormatToLongText(&$elements, $nameElement) 
	{
		$formData = $this->getFormData();
		$subject = $this->getSubject();

		$idEasy = $this->prefixEl . '___' . $nameElement;
		$id = $idEasy . ($this->getRequestWorkflow() ? '_wfl' : '') . ($this->getRequestWorkflowOrig() ? '_orig' : '');
		$value = $formData[$idEasy];
		$dEl = new stdClass();
		$showOnTypes = ['longtext'];

		// Options to set up the element
		$opts = Array(
			'0' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_FORMAT_LONG_TEXT_SIMPLE'),
			'1' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_FORMAT_LONG_TEXT_RICH'),
		);
		$dEl->options = $this->optionsElements($opts);
		$dEl->name = $id;
		$dEl->id = $id;
		$dEl->selected = Array($value);
		$dEl->multiple = '0';
		$dEl->attribs = 'class="fabrikinput form-select input-medium"' . ($this->getRequestWorkflow() ? ' disabled' : '');
		$dEl->multisize = '';

		$classDropdown = new PlgFabrik_ElementDropdown($subject);
		$elements[$idEasy]['objField'] = $classDropdown->getLayout('form');
		$elements[$idEasy]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$idEasy]['dataLabel'] = $this->getDataLabel(
			$id,
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_FORMAT_LONG_TEXT_LABEL') . ($this->getRequestWorkflowOrig() ? ' - Original' : ''),
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_FORMAT_LONG_TEXT_DESC'),
			$showOnTypes,
			false
		);
		$elements[$idEasy]['dataField'] = $dEl;
	}

	/**
	 * Setter method to text format element
	 *
	 * @param   	Array 	$elements		Reference to all elements
	 * @param		String	$nameElement	Identity of the element
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.1.3
	 */
	private function setElementTextFormat(&$elements, $nameElement) 
	{
		$formData = $this->getFormData();
		$subject = $this->getSubject();

		$idEasy = $this->prefixEl . '___' . $nameElement;
		$id = $idEasy . ($this->getRequestWorkflow() ? '_wfl' : '') . ($this->getRequestWorkflowOrig() ? '_orig' : '');
		$value = $formData[$idEasy];
		$dEl = new stdClass();
		$showOnTypes = ['text'];

		// Options to set up the element
		$opts = Array(
			'text' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TEXT_FORMAT_TEXT'),
			// Removed type integer and decimal because we have too many problems and not too much benefits
			//'integer' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TEXT_FORMAT_INTEGER'),
			//'decimal' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TEXT_FORMAT_DECIMAL'),
			'url' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TEXT_FORMAT_URL')
		);
		$dEl->options = $this->optionsElements($opts);
		$dEl->name = $id;
		$dEl->id = $id;
		$dEl->selected = Array($value);
		$dEl->multiple = '0';
		$dEl->attribs = 'class="fabrikinput form-select input-medium"' . ($this->getRequestWorkflow() ? ' disabled' : '');
		$dEl->multisize = '';

		$classDropdown = new PlgFabrik_ElementDropdown($subject);
		$elements[$idEasy]['objField'] = $classDropdown->getLayout('form');
		$elements[$idEasy]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$idEasy]['dataLabel'] = $this->getDataLabel(
			$id,
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TEXT_FORMAT_LABEL') . ($this->getRequestWorkflowOrig() ? ' - Original' : ''),
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TEXT_FORMAT_DESC'),
			$showOnTypes,
			false
		);
		$elements[$idEasy]['dataField'] = $dEl;
	}

	/**
	 * Setter method to default value element
	 *
	 * @param   	Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.0
	 * 
	 * @deprecated  since 4.3.1 	This method was remove by admin option 
	 *
	 */
	private function setElementDefaultValue(&$elements, $nameElement) 
	{
		$formData = $this->getFormData();
		$subject = $this->getSubject();

		$idEasy = $this->prefixEl . '___' . $nameElement;
		$id = $idEasy . ($this->getRequestWorkflow() ? '_wfl' : '') . ($this->getRequestWorkflowOrig() ? '_orig' : '');
		$value = $formData[$idEasy];
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
		$this->getRequestWorkflow() ? $dEl->attributes['disabled'] = 'disabled' : '';

		$classField = new PlgFabrik_ElementField($subject);
		$elements[$idEasy]['objField'] = $classField->getLayout('form');
		$elements[$idEasy]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$idEasy]['dataLabel'] = $this->getDataLabel(
			$id, 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_DEFAULT_VALUE_LABEL') . ($this->getRequestWorkflowOrig() ? ' - Original' : ''), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_DEFAULT_VALUE_DESC'), 
			$showOnTypes, 
			false
		);
		$elements[$idEasy]['dataField'] = $dEl;
	}

	/**
	 * Setter method to use filter element
	 *
	 * @param   	Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.0
	 */
	private function setElementUseFilter(&$elements, $nameElement) 
	{
		$formData = $this->getFormData();
		$subject = $this->getSubject();

		$idEasy = $this->prefixEl . '___' . $nameElement;
		$id = $idEasy . ($this->getRequestWorkflow() ? '_wfl' : '') . ($this->getRequestWorkflowOrig() ? '_orig' : '');
		$value = $formData[$idEasy] == 'true' || $formData[$idEasy] ? 1 : 0;

		$dEl = new stdClass();
		$showOnTypes = ['text', 'longtext', 'date', 'dropdown', 'autocomplete', 'treeview', 'date', 'rating', 'tags', 'user', 'internalid'];

		// Options to set up the element
		$opts = Array(
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_NO'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_YES')
		);
		$elements[$idEasy]['objField'] = new FileLayout('joomla.form.field.radio.switcher');
		$elements[$idEasy]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$idEasy]['dataLabel'] = $this->getDataLabel(
			$id, 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_USE_FILTER_LABEL') . ($this->getRequestWorkflowOrig() ? ' - Original' : ''), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_USE_FILTER_DESC'), 
			$showOnTypes, 
			false
		);
		$elements[$idEasy]['dataField'] = Array(
			'value' => $value,
			'options' => $this->optionsElements($opts),
			'name' => $id,
			'id' => $id,
			'class' => 'fbtn-default fabrikinput',
			'dataAttribute' => 'style="margin-bottom: 10px; padding: 0px"',
		);
		$this->getRequestWorkflow() ? $elements[$idEasy]['dataField']['disabled'] = 'disabled' : '';
	}

	/**
	 * Setter method to ajax upload element
	 *
	 * @param   	Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.0
	 */
	private function setElementAjaxUpload(&$elements, $nameElement) 
	{
		$formData = $this->getFormData();
		$subject = $this->getSubject();

		$idEasy = $this->prefixEl . '___' . $nameElement;
		$id = $idEasy . ($this->getRequestWorkflow() ? '_wfl' : '') . ($this->getRequestWorkflowOrig() ? '_orig' : '');
		$value = $formData[$idEasy] == 'true' || $formData[$idEasy] ? 1 : 0;

		$dEl = new stdClass();
		$showOnTypes = ['file'];

		// Options to set up the element
		$opts = Array(
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_NO'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_YES')
		);
		$elements[$idEasy]['objField'] = new FileLayout('joomla.form.field.radio.switcher');
		$elements[$idEasy]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$idEasy]['dataLabel'] = $this->getDataLabel(
			$id, 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_AJAX_ELEMENT_LABEL') . ($this->getRequestWorkflowOrig() ? ' - Original' : ''), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_AJAX_ELEMENT_DESC'), 
			$showOnTypes, 
			false
		);
		$elements[$idEasy]['dataField'] = Array(
			'value' => $value,
			'options' => $this->optionsElements($opts),
			'name' => $id,
			'id' => $id,
			'class' => 'fbtn-default fabrikinput',
			'dataAttribute' => 'style="margin-bottom: 10px; padding: 0px"',
		);
		$this->getRequestWorkflow() ? $elements[$idEasy]['dataField']['disabled'] = 'disabled' : '';
	}

	/**
	 * Setter method to format element
	 *
	 * @param   	Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.0
	 */
	private function setElementFormat(&$elements, $nameElement) 
	{
		$formData = $this->getFormData();
		$subject = $this->getSubject();

		$idEasy = $this->prefixEl . '___' . $nameElement;
		$id = $idEasy . ($this->getRequestWorkflow() ? '_wfl' : '') . ($this->getRequestWorkflowOrig() ? '_orig' : '');
		$value = $formData[$idEasy];
		$dEl = new stdClass();
		$showOnTypes = ['date'];

		// Options to set up the element
		$opts = Array(
			'd/m/Y' => 'DD/MM/AAAA',
			'm/d/Y' => 'MM/DD/AAAA',
			'Y/m/d' => 'AAAA/MM/DD',
			'd/m/Y H:i:s' => 'DD/MM/AAAA hh:mm:ss',
			'm/d/Y H:i:s' => 'MM/DD/AAAA hh:mm:ss',
			'Y/m/d H:i:s' => 'AAAA/MM/DD hh:mm:ss',
			'd-m-Y' => 'DD-MM-AAAA',
			'm-d-Y' => 'MM-DD-AAAA',
			'Y-m-d' => 'AAAA-MM-DD',
			'd-m-Y H:i:s' => 'DD-MM-AAAA hh:mm:ss',
			'm-d-Y H:i:s' => 'MM-DD-AAAA hh:mm:ss',
			'Y-m-d H:i:s' => 'AAAA-MM-DD hh:mm:ss',
		);
		$dEl->options = $this->optionsElements($opts);
		$dEl->name = $id;
		$dEl->id = $id;
		$dEl->selected = Array($value);
		$dEl->multiple = '0';
		$dEl->attribs = 'class="fabrikinput form-select input-medium"' . ($this->getRequestWorkflow() ? ' disabled' : '');
		$dEl->multisize = '';

		$classDropdown = new PlgFabrik_ElementDropdown($subject);
		$elements[$idEasy]['objField'] = $classDropdown->getLayout('form');
		$elements[$idEasy]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$idEasy]['dataLabel'] = $this->getDataLabel(
			$id,
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_FORMAT_LABEL') . ($this->getRequestWorkflowOrig() ? ' - Original' : ''),
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_FORMAT_DESC'),
			$showOnTypes,
			false
		);
		$elements[$idEasy]['dataField'] = $dEl;
	}

	/**
	 * Setter method to options drodown element
	 *
	 * @param   	Array 			$elements			Reference to all elements
	 * @param		String			$nameElement		Identity of the element
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.0
	 */
	private function setElementOptsDropdown(&$elements, $nameElement) 
	{
		$listModel = $this->getListModel();
		$formModel = $listModel->getFormModel();
		$formData = $this->getFormData();
		$subject = $this->getSubject();

		$classDropdown = new PlgFabrik_ElementDropdown($subject);

		$idEasy = $this->prefixEl . '___' . $nameElement;
		$sufix = ($this->getRequestWorkflow() ? '_wfl' : '') . ($this->getRequestWorkflowOrig() ? '_orig' : '');
		$id = $idEasy . $sufix;
		$values = explode(',', $formData[$idEasy]);

		$dEl = new stdClass;
		$showOnTypes = ['dropdown', 'tags'];

		// Options to set up the element
		$options = Array();
		$elContextModelElement = Array('name' => $nameElement . $sufix);
		$elContextTableElement = Array('label' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_OPTIONS_DROPDOWN_LABEL') . ($this->getRequestWorkflowOrig() ? ' - Original' : ''));
		$params = new Registry(json_encode(Array(
			'allow_frontend_addtodropdown' => $this->getRequestWorkflow() ? '0' : '1', 
			'allow_frontend_addto' => $this->getRequestWorkflow() ? '0' : '1', 
			'allowadd-onlylabel' => $this->getRequestWorkflow() ? '0' : '1',
			'dd-allowadd-onlylabel' => $this->getRequestWorkflow() ? '0' : '1',
			'savenewadditions' => $this->getRequestWorkflow() ? '0' : '1',
			'dd-savenewadditions' => $this->getRequestWorkflow() ? '0' : '1',
			'advanced_behavior' => $this->getRequestWorkflow() ? '0' : '1',
			'multiple' => '1',
			'sub_options' => Array(Array(
				'sub_values' => array_map(function($opt) {return $this->formatValue($opt);}, $values),
				'sub_labels' => $values,
				'sub_initial_selection' => $values
			))
		)));

		$classDropdown->setParams($params, 0);
		$classDropdown->setEditable(true);
		$classDropdown->getListModel()->getTable()->bind(Array('db_table_name' => 'easyadmin_modal'));
		$classDropdown->getFormModel()->getTable()->bind(Array('record_in_database' => '1'));
		$classDropdown->getFormModel()->getData();
		$classDropdown->getElement()->bind($elContextTableElement);
		$classDropdown->bindToElement($elContextModelElement);		
		$json = json_encode($classDropdown->elementJavascript(0));

		$elements[$idEasy]['objField'] = $classDropdown;
		$elements[$idEasy]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$idEasy]['dataLabel'] = $this->getDataLabel(
			$id,
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_OPTIONS_DROPDOWN_LABEL') . ($this->getRequestWorkflowOrig() ? ' - Original' : ''),
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_OPTIONS_DROPDOWN_DESC'),
			$showOnTypes,
			false
		);
		$elements[$idEasy]['dataField'] = Array();
	}

	/**
	 * Setter method to multi select element
	 *
	 * @param   	Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.0
	 */
	private function setElementMultiSelect(&$elements, $nameElement) 
	{
		$formData = $this->getFormData();
		$subject = $this->getSubject();

		$idEasy = $this->prefixEl . '___' . $nameElement;
		$id = $idEasy . ($this->getRequestWorkflow() ? '_wfl' : '') . ($this->getRequestWorkflowOrig() ? '_orig' : '');
		$value = $formData[$idEasy] == 'true' || $formData[$idEasy] ? 1 : 0;

		$dEl = new stdClass();
		$showOnTypes = ['dropdown'];

		// Options to set up the element
		$opts = Array(
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_NO'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_YES')
		);
		$elements[$idEasy]['objField'] = new FileLayout('joomla.form.field.radio.switcher');
		$elements[$idEasy]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);
		
		$elements[$idEasy]['dataLabel'] = $this->getDataLabel(
			$id, 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_MULTI_SELECT_LABEL') . ($this->getRequestWorkflowOrig() ? ' - Original' : ''), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_MULTI_SELECT_DESC'), 
			$showOnTypes, 
			false
		);
		$elements[$idEasy]['dataField'] = Array(
			'value' => $value,
			'options' => $this->optionsElements($opts),
			'name' => $id,
			'id' => $id,
			'class' => 'fbtn-default fabrikinput',
			'dataAttribute' => 'style="margin-bottom: 10px; padding: 0px"',
		);
		$this->getRequestWorkflow() ? $elements[$idEasy]['dataField']['disabled'] = 'disabled' : '';
	}

	/**
	 * Setter method to list element
	 *
	 * @param   	Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 *
	 * @return  	Null
	 *
	 * @since 		version 4.0
	 */
	private function setElementList(&$elements, $nameElement) 
	{
		$listModelModal = new FabrikFEModelList();
		$db = Factory::getContainer()->get('DatabaseDriver');

		$formData = $this->getFormData();
		$subject = $this->getSubject();

		$modalParams = json_decode($this->getModalParams(), true);

		$id = $db->getPrefix() . $this->dbTableNameModal . '___' . $nameElement;
		$idEasy = $this->prefixEl . '___' . $nameElement;
		$value = $formData[$id] ? $formData[$id] : $formData[$idEasy];
		$showOnTypes = ['autocomplete', 'treeview'];

		$listModelModal->setId($modalParams['list']);
		$formModelModal = $listModelModal->getFormModel();
		$formModelModal->getData();
		$groupsModal = $formModelModal->getGroupsHiarachy();
		$elementsModal = $listModelModal->getElements('id');
		$idEl = $modalParams['elementsId']['list'];

		$objDatabasejoin = $elementsModal[$idEl];
		$objDatabasejoin->setEditable(true);
		$objDatabasejoin->reset();

		$elements[$id]['objField'] = $objDatabasejoin;
		$elements[$id]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$id]['dataLabel'] = $this->getDataLabel(
			$id,
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LIST_LABEL') . ($this->getRequestWorkflowOrig() ? ' - Original' : ''),
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LIST_DESC'),
			$showOnTypes,
			false
		);
		$elements[$id]['dataField'] = Array($id => $value, $id.'_raw' => $value);
	}

	/**
	 * Setter method to label element
	 *
	 * @param   	Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 *
	 * @return 		Null
	 * 
	 * @since 		version 4.0
	 */
	private function setElementLabel(&$elements, $nameElement) 
	{
		$formData = $this->getFormData();
		$subject = $this->getSubject();

		$idEasy = $this->prefixEl . '___' . $nameElement;
		$id = $idEasy . ($this->getRequestWorkflow() ? '_wfl' : '') . ($this->getRequestWorkflowOrig() ? '_orig' : '');
		$value = $formData[$idEasy];

		$dEl = new stdClass();
		$showOnTypes = ['autocomplete', 'treeview'];

		// Options to set up the element
		$dEl->options = $this->optionsElements(Array());
		$dEl->name = $id;
		$dEl->id = $id;
		$dEl->selected = Array($value);
		$dEl->multiple = '0';
		$dEl->attribs = 'class="fabrikinput form-select input-medium child-element-list"'  . ($this->getRequestWorkflow() ? ' disabled' : '');
		$dEl->multisize = '';
		$dEl->attributes = Array(
			'type' => 'text',
			'id' => $id,
			'name' => $id,
			'size' => 0,
			'maxlength' => '255',
			'class' => 'form-control fabrikinput inputbox text',
			'value' => $value
		);
		$this->getRequestWorkflow() ? $dEl->attributes['disabled'] = 'disabled' : '';

		$class = $this->getRequestWorkflow() ? new PlgFabrik_ElementField($subject) : new PlgFabrik_ElementDropdown($subject);
		$elements[$idEasy]['objField'] = $class->getLayout('form');
		$elements[$idEasy]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$idEasy]['dataLabel'] = $this->getDataLabel(
			$id,
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LABEL_LABEL') . ($this->getRequestWorkflowOrig() ? ' - Original' : ''),
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LABEL_DESC'),
			$showOnTypes,
			false
		);
		$elements[$idEasy]['dataField'] = $dEl;
	}

	/**
	 * Setter method to father element
	 *
	 * @param   	Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.0
	 */
	private function setElementFather(&$elements, $nameElement) 
	{
		$formData = $this->getFormData();
		$subject = $this->getSubject();

		$idEasy = $this->prefixEl . '___' . $nameElement;
		$id = $idEasy . ($this->getRequestWorkflow() ? '_wfl' : '') . ($this->getRequestWorkflowOrig() ? '_orig' : '');
		$value = $formData[$idEasy];
		$showOnTypes = ['treeview'];
		$dEl = new stdClass();

		// Options to set up the element
		$dEl->options = $this->optionsElements(Array());
		$dEl->name = $id;
		$dEl->id = $id;
		$dEl->selected = Array($value);
		$dEl->multiple = '0';
		$dEl->attribs = 'class="fabrikinput form-select input-medium child-element-list"' . ($this->getRequestWorkflow() ? ' disabled' : '');
		$dEl->multisize = '';
		$dEl->attributes = Array(
			'type' => 'text',
			'id' => $id,
			'name' => $id,
			'size' => 0,
			'maxlength' => '255',
			'class' => 'form-control fabrikinput inputbox text',
			'value' => $value
		);
		$this->getRequestWorkflow() ? $dEl->attributes['disabled'] = 'disabled' : '';

		$class = $this->getRequestWorkflow() ? new PlgFabrik_ElementField($subject) : new PlgFabrik_ElementDropdown($subject);
		$elements[$idEasy]['objField'] = $class->getLayout('form');
		$elements[$idEasy]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);
		
		$elements[$idEasy]['dataLabel'] = $this->getDataLabel(
			$id, 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_FATHER_LABEL') . ($this->getRequestWorkflowOrig() ? ' - Original' : ''), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_FATHER_DESC'), 
			$showOnTypes, 
			false
		);
		$elements[$idEasy]['dataField'] = $dEl;
	}

	/**
	 * Setter method to multi relation element
	 *
	 * @param   	Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.0
	 */
	private function setElementMultiRelations(&$elements, $nameElement) 
	{
		$formData = $this->getFormData();
		$subject = $this->getSubject();

		$idEasy = $this->prefixEl . '___' . $nameElement;
		$id = $idEasy . ($this->getRequestWorkflow() ? '_wfl' : '') . ($this->getRequestWorkflowOrig() ? '_orig' : '');
		$value = $formData[$idEasy] == 'true' || $formData[$idEasy] ? 1 : 0;

		$dEl = new stdClass();
		$showOnTypes = ['autocomplete', 'treeview'];

		// Options to set up the element
		$opts = Array(
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_NO'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENTS_YESNO_YES')
		);

		$elements[$idEasy]['objField'] = new FileLayout('joomla.form.field.radio.switcher');
		$elements[$idEasy]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$idEasy]['dataLabel'] = $this->getDataLabel(
			$id, 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_MULTI_RELATIONS_LABEL') . ($this->getRequestWorkflowOrig() ? ' - Original' : ''), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_MULTI_RELATIONS_DESC'), 
			$showOnTypes, 
			false
		);
		$elements[$idEasy]['dataField'] = Array(
			'value' => $value,
			'options' => $this->optionsElements($opts),
			'name' => $id,
			'id' => $id,
			'class' => 'fbtn-default fabrikinput',
			'dataAttribute' => 'style="margin-bottom: 10px; padding: 0px"',
		);
		$this->getRequestWorkflow() ? $elements[$idEasy]['dataField']['disabled'] = 'disabled' : '';
	}

	/**
	 * Setter method to tags element
	 *
	 * @param   	Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.3.1
	 */
	private function setElementTags(&$elements, $nameElement) 
	{
		$formData = $this->getFormData();
		$subject = $this->getSubject();

		$idEasy = $this->prefixEl . '___' . $nameElement;
		$id = $idEasy . ($this->getRequestWorkflow() ? '_wfl' : '') . ($this->getRequestWorkflowOrig() ? '_orig' : '');
		$value = $formData[$idEasy];
		$dEl = new stdClass();
		$showOnTypes = ['autocomplete'];

		// Options to set up the element
		$opts = Array(
			'tags' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TAGS_OPTION_TAGS'),
			'popup_form' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TAGS_OPTION_POPUP_FORM'),
			'no' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TAGS_OPTION_NO'),
		);
		$dEl->options = $this->optionsElements($opts);
		$dEl->name = $id;
		$dEl->id = $id;
		$dEl->selected = Array($value);
		$dEl->multiple = '0';
		$dEl->attribs = 'class="fabrikinput form-select input-medium"' . ($this->getRequestWorkflow() ? ' disabled' : '');
		$dEl->multisize = '';

		$classDropdown = new PlgFabrik_ElementDropdown($subject);
		$elements[$idEasy]['objField'] = $classDropdown->getLayout('form');
		$elements[$idEasy]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$idEasy]['dataLabel'] = $this->getDataLabel(
			$id,
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TAGS_LABEL') . ($this->getRequestWorkflowOrig() ? ' - Original' : ''),
			Text::sprintf('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TAGS_DESC', Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TAGS_OPTION_TAGS'), Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TAGS_OPTION_POPUP_FORM'), Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TAGS_OPTION_NO')),
			$showOnTypes,
			false
		);
		$elements[$idEasy]['dataField'] = $dEl;
	}

	/**
	 * Setter method to access rating element
	 * 
	 * @param		Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 * 
	 * @return  	Null
	 * 
	 * @since 		version 4.0
	 */
	private function setElementAccessRating(&$elements, $nameElement) 
	{
		$formData = $this->getFormData();
		$subject = $this->getSubject();
		$listModel = $this->getListModel();

		$idEasy = $this->prefixEl . '___' . $nameElement;
		$id = $idEasy . ($this->getRequestWorkflow() ? '_wfl' : '') . ($this->getRequestWorkflowOrig() ? '_orig' : '');
		$value = $formData[$idEasy];
		$dEl = new stdClass();
		$showOnTypes = ['rating'];

		// Options to set up the element
		$opts = $this->getViewLevels();
		$opts = Array(
			'1' => Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_ACCESS_RATING_PUBLIC'),
			$listModel->getParams()->get('allow_edit_details') => $listModel->getTable()->get('label')
		);
		$dEl->options = $this->optionsElements($opts);
		$dEl->name = $id;
		$dEl->id = $id;
		$dEl->selected = Array($value);
		$dEl->multiple = '0';
		$dEl->attribs = 'class="fabrikinput form-select input-medium"' . ($this->getRequestWorkflow() ? ' disabled' : '');
		$dEl->multisize = '';

		$classDropdown = new PlgFabrik_ElementDropdown($subject);
		$elements[$idEasy]['objField'] = $classDropdown->getLayout('form');
		$elements[$idEasy]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$idEasy]['dataLabel'] = $this->getDataLabel(
			$id,
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_ACCESS_RATING_LABEL') . ($this->getRequestWorkflowOrig() ? ' - Original' : ''),
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_ACCESS_RATING_DESC'),
			$showOnTypes,
			false
		);
		$elements[$idEasy]['dataField'] = $dEl;
	}

	/**
	 * Setter method to auxiliary elements of the link element
	 *
	 * @param   	Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 * @param		String		$ids				The elements ids
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.2.1
	 */
	private function setElementsAuxLink(&$elements, $nameElement, $ids)
	{
		$subject = $this->getSubject();
		$classDropdown = new PlgFabrik_ElementDropdown($subject);
		$elementsList = $this->getListModel()->getElements();
		$showOnTypes = $nameElement == 'mainAuxLink' ? ['link'] : ['element-label_advanced_link' . ($this->getRequestWorkflow() ? '_wfl' : '') . ($this->getRequestWorkflowOrig() ? '_orig' : '')];

		foreach ($ids as $idEl) {
			$optsDropdown = Array();
			$idEasy = $this->prefixEl . '___' . $idEl . '_link';
			$id = $idEasy . ($this->getRequestWorkflow() ? '_wfl' : '') . ($this->getRequestWorkflowOrig() ? '_orig' : '');
			$value = $formData[$idEasy];

			foreach ($elementsList as $classEl) {
				$el = $classEl->getElement();

				$hide = ['id', 'created_by', 'indexing_text', 'created_ip'];
				if(in_array($el->get('name'), $hide) || $classEl->getParams()->get('element_link_easyadmin')) continue;

				switch ($idEl) {
					case 'thumb':
						$el->get('plugin') == 'fileupload' ? $optsDropdown[$el->get('id')] = $el->get('label') : null;
						break;

					case 'title':
						$el->get('plugin') == 'field' ? $optsDropdown[$el->get('id')] = $el->get('label') : null;
						break;

					case 'description':
						$el->get('plugin') == 'textarea' ? $optsDropdown[$el->get('id')] = $el->get('label') : null;
						break;
					
					default:
						$default = true;
						if($el->get('plugin') != 'fileupload') {
							$optsDropdown[$el->get('id')] = $el->get('label');
						}
						break;
				}
			}

			$default ? array_unshift($optsDropdown, Text::_("COM_FABRIK_PLEASE_SELECT")): null;
			$opts = $this->optionsElements($optsDropdown);

			// Options to set up the element
			$dEl = new stdClass();
			$dEl->name = $id;
			$dEl->id = $id;
			$dEl->options = $opts;
			$dEl->selected = Array($value);
			$dEl->multiple = '0';
			$dEl->attribs = 'class="fabrikinput form-select input-medium"' . ($this->getRequestWorkflow() ? ' disabled' : '');
			$dEl->multisize = '';

			$elements[$idEasy]['objField'] = $classDropdown->getLayout('form');
			$elements[$idEasy]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

			$elements[$idEasy]['dataLabel'] = $this->getDataLabel(
				$id,
				Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_' . strtoupper($idEl) .'_LINK_LABEL') . ($this->getRequestWorkflowOrig() ? ' - Original' : ''),
				Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_' . strtoupper($idEl) .'_LINK_DESC'),
				$showOnTypes,
				false
			);
			$elements[$idEasy]['dataField'] = $dEl;
		}
	}

	/**
	 * Setter method to display the advanced settings label of the link type
	 *
	 * @param   	Array 		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.2.1
	 */
	private function setElementLabelAdvancedLink(&$elements, $nameElement) 
	{
		$subject = $this->getSubject();

		$idEasy = $this->prefixEl . '___' . $nameElement;
		$id = $idEasy . ($this->getRequestWorkflow() ? '_wfl' : '') . ($this->getRequestWorkflowOrig() ? '_orig' : '');
		$showOnTypes = ['link'];

		$elements[$idEasy]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);
		$elements[$idEasy]['dataLabel'] = $this->getDataLabel(
			$id,
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LABEL_ADVANCED_LINK_LABEL') . ($this->getRequestWorkflowOrig() ? ' - Original' : ''),
			Text::_(''),
			$showOnTypes,
			false
		);
		$elements[$idEasy]['cssElement'] = 'text-decoration: underline;';

	}

	/**
     * Get the list of all view levels
     *
     * @return  	Object
     *
     * @since   	4.0
     */
    public function getViewLevels()
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        // Get all the available view levels
        $query->select($db->qn('id'))
            ->select($db->qn('title'))
            ->from($db->qn('#__viewlevels'))
            ->order($db->qn('id'))
			->where($db->qn("id") . " IN ('" . implode("','", [1, $this->getListModel()->getParams()->get('allow_edit_details')]) . "')");

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
	 * Method that save the modal data from ajax request
	 * 
	 * @return  	String
	 * 
	 * @since 		version 4.0
	 */
	public function onSaveModal()
	{
		$listModel = new FabrikFEModelList();
		$model = JModelLegacy::getInstance('Element', 'FabrikAdminModel');

		$listId = $_POST['easyadmin_modal___listid'];
		$listModel->setId($listId);

		$data = $listModel->removeTableNameFromSaveData($_POST);
		$mode = $data['mode'];

		switch ($mode) {
			case 'elements':
				if(in_array($data['history_type'], ['related_list', 'longtext'])) {
					// Changing the element related_list to another type, the group must to be the principal
					$idEl = $data['valIdEl'];
					$element = $listModel->getElements('id', true, false)[$idEl];
					preg_match('/{loadmoduleid (\d+)}/', $element->getElement()->get('default'), $match);

					$data['group_id_old'] = (string) $element->getGroup()->getId();
					$data['module_id_old'] = $match[1];
					$group_id = array_keys($listModel->getFormModel()->getGroups())[0];
				} else if($data['valIdEl'] != '0') {
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
			
			case 'columns':
				$modelElement = new FabrikAdminModelElement();
				$r = $this->saveOrder($modelElement, $data, $listModel);
				break;
		}

		echo $r;
	}

	/**
	 * Method that save the modal data to elements
	 *
	 * @param		Array			$data				The data sent
	 * @param		Int				$group_id			Group id of the list
	 * @param		Object			$listModel			Object of the frontend list model
	 *
	 * @return  	String			Success or false
	 *
	 * @since 		version 4.0
	 */
	private function saveModalElements($data, $group_id, $listModel)
	{
		$app = Factory::getApplication();
		$db = Factory::getContainer()->get('DatabaseDriver');

		$modelElement = new FabrikAdminModelElement();
		$modelForm = new FabrikAdminModelForm();

		$input = $app->input;
		$elChangedType = false;

		$labelElement = $data['name'];
		$validate = $this->validateElements($data, $listModel);
		if($validate->error) {
			return json_encode($validate);
		}

		$opts = Array();
		$params = Array();
		$validation = Array();

		$nameEl = $this->formatValue($data['name']);

		// If the user change the type we need create a new element and send to trash the old one
		if($data['valIdEl'] != '0' && $data['history_type'] != $data['type'] && !empty($data['history_type'])) {
			$oldId = $data['valIdEl'];
			$data['valIdEl'] = '0';

			$element = $listModel->getElements('id', true, false)[$oldId];

			$optsOld['id'] = $oldId;
			$optsOld['published'] = '-2';
			$optsOld['params'] = json_decode($element->getParams(), true);
			$optsOld['validationrule'] = $optsOld['params']['validations'];
			$this->syncParams($optsOld, $listModel);
			$modelElement->getState(); 	//We need do this to set __state_set before the save
			$modelElement->save($optsOld);

			$nameEl = $this->checkNameElementToChangeType($nameEl, $listModel);
			$elChangedType = true;
		}

		$opts['easyadmin'] = true;
		$opts['asset_id'] = '';
		$opts['id'] = $data['valIdEl'];
		$opts['label'] = $labelElement;
		$opts['name'] = $opts['id'] == '0' ? $nameEl : '';
		$opts['group_id'] = $group_id;
		$opts['published'] = $data['trash'] == 'true' ? '0' : '1';
		$opts['show_in_list_summary'] = $data['show_in_list'] != '' ? '1' : '0';
		$opts['access'] = '1';
		$opts['modelElement'] = $modelElement;
		$opts['link_to_detail'] = '1';

		// Filter rules
		if($data['use_filter']) {
			$opts['filter_exact_match'] = '0';
			$params['filter_access'] = '1';
			$params['filter_length'] = '20';
			$params['filter_required'] = '0';
			$params['filter_build_method'] = '2';
			$params['filter_groupby'] = 'text';
			$params['filter_class'] = 'input-xxlarge';
			$params['filter_responsive_class'] = '';
		} else {
			$opts['filter_type'] = '';
		}

		$type = $data["type"];
		switch ($type) {
			case 'text':
			case 'longtext':
				$params['maxlength'] = 255;
				$params['field_thousand_sep'] = ',';
				$params['field_decimal_sep'] = '.';

				$opts['hidden'] = '0';
				$opts['default'] = $data['default_value'];
				$opts['plugin'] = 'field';

				$data['use_filter'] ? $opts['filter_type'] = 'auto-complete' : null;
				$params['field_use_number_format'] = $data['text_format'] == 'decimal' ? '1' : '0';

				if($type == 'text') {
					if(in_array($data['text_format'], ['integer', 'decimal'])) {
						$pluginValidation[] = 'isgreaterorlessthan';
						$publishedValidation[] = '1';
						$validateInValidation[] = 'both';
						$validateOnValidation[] = 'both';
						$validateHidenValidation[] = '0';
						$mustValidateValidation[] = '1';
						$showIconValidation[] = '1';

						$params['isgreaterorlessthan-message'][0] = Text::_("PLG_FABRIK_LIST_EASY_ADMIN_MESSAGE_ERROR_VALIDATION_IS_GRATER_THAN_OR_LESS_THAN");
						$params['isgreaterorlessthan_greaterthan'][0] = '0';
						$params['compare_value'][0] = 2000000000;
						$params['isgreaterorlessthan_allow_empty'][0] = '1';
						
					}
				}

				if($type == 'longtext') {
					$opts['plugin'] = 'textarea';
					$opts['filter_type'] = 'field';

					$params['bootstrap_class'] = 'col-sm-12';
					$params['textarea_field_type'] = 'MEDIUMTEXT';
					$params['use_wysiwyg'] = $data['format_long_text'];
					$params['height'] = '6';

					if($data['format_long_text']) {
						$params['height'] = '20';

						$groupIdRelated = $this->groupToLongtextElement($listModel, $opts, $params);
						$opts['group_id'] = $groupIdRelated;
					} else {
						$opts['group_id_old'] = $data['group_id_old'];
						$this->groupToLongtextElement($listModel, $opts, $params, true);
					}
				}

				if($data['text_format'] == 'url') {
					$opts['link_to_detail'] = '0';
					$params['guess_linktype'] = '1';
					$params['link_target_options'] = '_blank';
					$params['text_format'] = 'text';
					$params['password'] = '5';
				} else {
					$params['password'] = in_array($data['text_format'], ['integer', 'decimal']) ? '6' : '0';
					$params['text_format'] = $data['text_format'];
				}

				break;

			case 'file':
				$validFileupload = '
				if(isset($_REQUEST["wfl_action"])) {
					if ($_REQUEST["wfl_action"] == "list_requests") {
						return false;
					} else {
						return true;
					}
				}
					
				return true;';

				$opts['plugin'] = 'fileupload';
				$params['ul_max_file_size'] = '1048576';
				$params['ul_directory'] = 'images/stories/';
				$params['image_library'] = 'gd2';
				$params['fileupload_crop_dir'] = 'images/stories/crop';
				$params['ul_max_file_size'] = '1048576';
				$params['ul_max_file_size'] = '1048576';
				$params['ul_file_increment'] = '1';
				$params['ajax_show_widget'] = '0';
				$params['random_filename'] = '1';
				$params['length_random_filename'] = '12';
				$params['fu_make_pdf_thumb'] = '0';
				$params['make_thumbnail'] = '0';
				$params['ajax_max'] = '50';
				$params['ajax_dropbox_width'] = '0';

				if($data['ajax_upload']) {
					$params['ajax_upload'] = '1';
					$params['fu_show_image_in_table'] = '3';
					$params['fu_show_image'] = '3';
				} else {
					$params['ajax_upload'] = '0';
					$params['fu_show_image_in_table'] = '2';
					$params['fu_show_image'] = '2';
				}

				$data['use_filter'] ? $opts['filter_type'] = 'auto-complete' : null;
				$params['notempty-validation_condition'][0] = $data['required'] ? $validFileupload : '';
				break;

			case 'dropdown':
				$opts['plugin'] = 'dropdown';
				$params['multiple'] = $data['multi_select'] ? '1' : '0';
				$params['advanced_behavior'] = $params['multiple'];

				$this->configOptsDropdown($data, $params);

				$data['use_filter'] ? $opts['filter_type'] = 'dropdown' : null;
				break;

			case 'date':
				$opts['plugin'] = 'date';
				$params['date_table_format'] = $data['format'];
				$params['date_form_format'] = $data['format'];
				$params['date_which_time_picker'] = str_contains($data['format'], 'H:i:s') ? 'clock' : 'wicked';
				$params['date_showtime'] = str_contains($data['format'], 'H:i:s') ? '1' : '0';

				$data['use_filter'] ? $opts['filter_type'] = 'range' : null;
				break;

			case 'rating':
				$opts['plugin'] = 'rating';
				$opts['hidden'] = '0';
				$params['rating_access'] = $data['access_rating'];
				$params['rating-mode'] = 'user-rating';
				$params['rating-nonefirst'] = '1';
				$params['rating-rate-in-form'] = '1';
				$params['rating_float'] = '0';

				$data['use_filter'] ? $opts['filter_type'] = 'stars' : null;
				break;

			case 'autocomplete':
			case 'treeview':
				$opts['link_to_detail'] = '0';
				$opts['plugin'] = 'databasejoin';

				$params['join_conn_id'] = '1';
				$params['join_db_name'] = $data['listas'];
				$params['join_key_column'] = 'id';
				$params['join_val_column'] =  $data['label'];
				$params['database_join_show_please_select'] =  '1';
				$params['dbjoin_autocomplete_rows'] =  10;
				$params['databasejoin_readonly_link'] = '1';
				$params['fabrikdatabasejoin_frontend_add'] =  '0';

				$params['database_join_display_type'] = $data['multi_relation'] ? 'checkbox' : 'auto-complete';

				if($data['use_filter']) {
					$type == 'autocomplete' ? $opts['filter_type'] = 'auto-complete' : $opts['filter_type'] = 'treeview';
				}

				if($type == 'autocomplete') {
					$params['database_join_display_style'] =  'only-autocomplete';
					$params['jsSuggest'] =  '1';
					$data['tags'] = $this->checkRestrictList($data['listas']) ? 'no' : $data['tags'];

					switch ($data['tags']) {
						case 'tags':
							$params['moldTags'] = '1';
							$formPopup = false;
							break;

						case 'popup_form':
							$params['moldTags'] = '0';
							$formPopup = true;
							break;

						default:
							$params['moldTags'] = '0';
							$formPopup = false;
							break;
					}
				} else {
					$params['database_join_display_style'] =  'only-treeview';
					$params['tree_parent_id'] =  $data['father'];
					$formPopup = true;
				}

				if($formPopup) {
					$params['fabrikdatabasejoin_frontend_add'] =  '1';
					$params['fabrikdatabasejoin_frontend_blank_page'] =  '0';
					$params['join_popupwidth'] =  '80%';
					$params['rollover'] = Text::_("PLG_FABRIK_LIST_EASY_ADMIN_ROLLOVER_DATABASEJOIN");
					$params['databasejoin_popupform'] = $this->getIdPopupForm($data['listas']);
					$params['moldTags'] = '0';
				}

				break;

			case 'thumbs':
				$opts['plugin'] = 'thumbs';
				$opts['hidden'] = '0';

				$params['rate_in_from'] =  '0';
				$params['show_down'] =  $data['show_down_thumb'] != '' ? '1' : '0';
				break;

			case 'related_list':
				$opts['related_list'] = $data['related_list'];
				$opts['group_id_old'] = $data['group_id_old'];
				$opts['module_id_old'] = $data['module_id_old'];
				$opts['related_list_element'] = $data['related_list_element'];

				$groupIdRelated = $this->groupToElementRelatedList($listModel, $opts, $params);
				$moduleId = $this->moduleToElementRelatedList($listModel, $opts, $params);
				$this->configureListToElementRelatedList($listModel, $opts, $params);
				$this->configureFormToElementRelatedList($listModel, $opts, $params);

				$opts['plugin'] = 'display';
				$opts['default'] = "<!-- {related_list_element-" . $opts['related_list_element'] . "-} {related_list-" . $opts['related_list'] . "-}--> {loadmoduleid $moduleId}";
				$opts['group_id'] = $groupIdRelated;
				$params['display_showlabel'] = "0";
				break;

			case 'tags':
				$opts['plugin'] = 'dropdown';

				$params['multiple'] = '1';
				$params['advanced_behavior'] = '1';
				$params['allow_frontend_addtodropdown'] = '1';
				$params['dd-allowadd-onlylabel'] = '1';
				$params['dd-savenewadditions'] = '1';
				
				$params['sub_options'] = Array(
					'sub_values' => '',
					'sub_labels' => '',
				);
				
				$this->configOptsDropdown($data, $params);

				$data['use_filter'] ? $opts['filter_type'] = 'auto-complete' : null;
				break;

			case 'youtube':
				$opts['plugin'] = 'youtube';
				$params['width'] = '30';
				$params['player_size'] = 'medium';
				$params['display_in_table'] = '2';
				$params['youtube_autoplay'] = '0';

				$params['php-message'][0] = Text::_("PLG_FABRIK_LIST_EASY_ADMIN_ERROR_YOUTUBE_LINK");
				$params['php-code'][0] = '
					if (filter_var($data, FILTER_VALIDATE_URL)) {
						$domain = parse_url($data, PHP_URL_HOST);
						$domain = strtolower($domain);

						if ((strpos($domain, "youtube.com") !== false || strpos($domain, "youtu.be") !== false) && !str_contains($data, "list=")) {
							return true;
						}
					}

					return false;';
				$pluginValidation[] = 'php';
				$publishedValidation[] = '1';
				$validateInValidation[] = 'both';
				$validateOnValidation[] = 'both';
				$validateHidenValidation[] = '0';
				$mustValidateValidation[] = '1';
				$showIconValidation[] = '1';

				break;

			case 'link':
				$opts['plugin'] = 'field';
				$opts['link_to_detail'] = '0';
				$params['element_link_easyadmin'] = '1';
				$params['maxlength'] = 255;
				$params['guess_linktype'] = '1';
				$params['link_target_options'] = '_blank';
				$params['isuniquevalue_message-0'] = Text::_("PLG_FABRIK_LIST_EASY_ADMIN_MESSAGE_ERROR_VALIDATION_ISUNIQUE");

				$pluginValidation[] = 'isuniquevalue';
				$publishedValidation[] = '1';
				$validateInValidation[] = 'both';
				$validateOnValidation[] = 'both';
				$validateHidenValidation[] = '0';
				$mustValidateValidation[] = '1';
				$showIconValidation[] = '1';
				break;

			case 'user':
				$data['use_filter'] ? $opts['filter_type'] = 'dropdown' : null;
				$params['filter_build_method'] = '1';
				break;

			case 'internalid':
				$data['use_filter'] ? $opts['filter_type'] = 'field' : null;
				break;
		}

		// Required rules
		if($data['required']) {
			$pluginValidation[] = 'notempty';
			$publishedValidation[] = '1';
			$validateInValidation[] = 'both';
			$validateOnValidation[] = 'both';
			$validateHidenValidation[] = '0';
			$mustValidateValidation[] = '1';
			$showIconValidation[] = '1';
			$params['notempty-message'] = Text::_("PLG_FABRIK_LIST_EASY_ADMIN_REQUIRED_FIELD");
		}

		// Show in list rules
		if($data['show_in_list']) {
			$width = !$data['width_field'] ? '10' : $data['width_field'];
			$css = 'overflow: hidden; text-overflow: ellipsis; white-space: nowrap;';
			$data['white_space'] == 'true' ? $cssCel = '' : $cssCel = $css;

			$cssCel = 'width: ' . $width . '%; max-width: 1px; ' . $cssCel;
			$cssHeader = 'width: ' . $width . '%; ' . $css;

			$params['tablecss_cell'] = $width ? $cssCel : "";
			$params['tablecss_header'] = $width ? $cssHeader : "";

			// We need add the width to width of the list
			$verifyWidth = $this->verifyWidthList($width, $opts['id'], $listModel, $elChangedType);
			if($verifyWidth['overWidth']) {
				$this->resizeWidthElements($verifyWidth['resize'], $verifyWidth['weigth'], $opts['id'], $listModel, $elChangedType);
			}
		}

		// Validation rules
			if(isset($pluginValidation)) {
			$validation['plugin'] = $pluginValidation;
			$validation['plugin_published'] = $publishedValidation;
			$validation['validate_in'] = $validateInValidation;
			$validation['validate_on'] = $validateOnValidation;
			$validation['validate_hidden'] = $validateHidenValidation;
			$validation['must_validate'] = $mustValidateValidation;
			$validation['show_icon'] = $showIconValidation;
			$opts['validationrule'] = $validation;
		}

		$params['can_order'] = '1';
		$opts['params'] = $params;

		if($opts['id'] != '0') {
			$opts['id'] = $data['history_type'] != $data['type'] ? $oldId : $opts['id'];

			$origName = $this->syncParams($opts, $listModel);
			$input->set('name_orig', $origName);

			$opts['id'] = $data['history_type'] != $data['type'] ? '0' : $opts['id'];
		}

		$modelElement->getState(); 	//We need do this to set __state_set before the save
		$modelElement->save($opts);
		$data["valIdEl"] = $modelElement->getState('element.id');

		$saveOrder = $this->saveOrder($modelElement, $data, $listModel);
		if(!$saveOrder) {
			$validate->error = Text::_("PLG_FABRIK_LIST_EASYADMIN_ERROR_ORDERING");
		}

		if($data['history_type'] == 'related_list') {
			// Changing the element related_list to another type, the group and module must to be unpublished
			$opts['group_id_old'] = $data['group_id_old'];
			$opts['module_id_old'] = $data['module_id_old'];

			$this->groupToElementRelatedList($listModel, $opts, $params, true);
			$this->moduleToElementRelatedList($listModel, $opts, $params, true);
		}

		//Save the form to add metadata_extract plugin and configure it
		if($type == 'link' && $opts['published'] == '1') {
			$formModel = $listModel->getFormModel();
			$propertiesForm = $formModel->getTable()->getProperties();
			$groupsForm = $formModel->getGroups();
			$dataForm['current_groups'] = array_keys($groupsForm);
			$dataForm['database_name'] = $propertiesForm['db_table_name'];
			$pluginsForm = Array();
			foreach ($propertiesForm as $key => $val) {
				if(!array_key_exists($key, $dataForm)) {
					$dataForm[$key] = $propertiesForm[$key];
				}

				if($key == 'params') {
					$dataForm[$key] = json_decode($dataForm[$key], true);
					$dataForm[$key]['thumb'] = $data['thumb_link'];
					$dataForm[$key]['link'] = $modelElement->getState('element.id');
					$dataForm[$key]['title'] = $data['title_link'];
					$dataForm[$key]['description'] = $data['description_link'];
					$dataForm[$key]['subject'] = $data['subject_link'];
					$dataForm[$key]['creator'] = $data['creator_link'];
					$dataForm[$key]['date'] = $data['date_link'];
					$dataForm[$key]['format'] = $data['format_link'];
					$dataForm[$key]['coverage'] = $data['coverage_link'];
					$dataForm[$key]['publisher'] = $data['publisher_link'];
					$dataForm[$key]['identifier'] = $data['identifier_link'];
					$dataForm[$key]['language'] = $data['language_link'];
					$dataForm[$key]['type'] = $data['type_link'];
					$dataForm[$key]['contributor'] = $data['contributor_link'];
					$dataForm[$key]['relation'] = $data['relation_link'];
					$dataForm[$key]['rights'] = $data['rights_link'];
					$dataForm[$key]['source'] = $data['source_link'];

					$pluginsForm['plugin'] = $dataForm[$key]['plugins'];
					$pluginsForm['plugin_locations'] = $dataForm[$key]['plugin_locations'];
					$pluginsForm['plugin_events'] = $dataForm[$key]['plugin_events'];
					$pluginsForm['plugin_description'] = $dataForm[$key]['plugin_description'];
					$pluginsForm['plugin_state'] = $dataForm[$key]['plugin_state'];
				}
			}

			// Data to configure the new plugin metadata_extract
			if(!in_array('metadata_extract', $pluginsForm['plugin'])) {
				$pluginsForm['plugin'][] = 'metadata_extract';
				$pluginsForm['plugin_locations'][] = 'both';
				$pluginsForm['plugin_events'][] = 'both';
				$pluginsForm['plugin_description'][] = Text::_('PLG_FABRIK_LIST_EASY_ADMIN_PLUGIN_METADATA_EXTRACT_DESC');
				$pluginsForm['plugin_state'][] = '1';
			}
	
			$input->set('jform', $pluginsForm);
			$modelForm->getState(); 	//We need do this to set __state_set before the save
			$modelForm->save($dataForm);
		}

		return json_encode($validate);
	}

	/**
	 * This method format the options selected to dropdown element
	 * 
	 * @param		Array			$data			Request data
	 * @param		Array			$params			Request params			
	 */
	private function configOptsDropdown($data, &$params)
	{
		if($data['options_dropdown'] === ',') return;

		$subOptions = explode(',', $data['options_dropdown']);

		foreach ($subOptions as $key => $option) {
			if(empty($option)) {
				unset($subOptions[$key]);
			}
		}

		$subOptions = array_values($subOptions);
		$params['sub_options'] = Array(
			'sub_values' => array_map(function($opt) {return $this->formatValue($opt);}, $subOptions),
			'sub_labels' => $subOptions,
			'sub_initial_selection' => ''
		);
	}

	/**
	 * This method get in database the list id
	 * 
	 * @param			String			$tableName			Table name requiered
	 * 
	 * @return			Int
	 * 
	 * @since			v4.1
	 */
	private function getIdPopupForm($tableName)
	{
		$db = Factory::getContainer()->get('DatabaseDriver');

		$query = $db->getQuery(true);
		$query->select('f.id AS value, f.label AS text, l.id AS listid')->from('#__fabrik_forms AS f')
			->join('LEFT', '#__fabrik_lists As l ON f.id = l.form_id')
			->where('l.db_table_name = ' . $db->q($tableName));
		$db->setQuery($query);
		$options = $db->loadObjectList();

		return $options[0]->value;
	}
	
	/**
	 * This method checks if the width of the elements exceeds the width of the list.
	 * 
	 * @param		Int				$widthElement			The new width to add
	 * @param		Int				$elId					The id element
	 * @param		Object			$listModel				Object of the frontend list model
	 * @param		Boolean			$elChangedType			Does element changed the type?
	 * 
	 * @return		Array
	 * 
	 * @since		v4.3.1
	 */
	private function verifyWidthList($widthElement, $elId, $listModel, $elChangedType)
	{
		$response = Array();
		$elements = $listModel->getElements();
		$widthList = (int) $listModel->getParams()->get('width_list');
		$width = 0;

		foreach ($elements as $el) {
			if(!$el->getElement()->show_in_list_summary || $el->getId() == (int) $elId || $elChangedType) {
				continue;
			}

			$cssCel = $el->getParams()->get('tablecss_cell');
			preg_match('/\d+(\.\d+)?/', $cssCel, $matches);
			$width += (float) $matches[0];
		}

		$response['overWidth'] = ($width + (float) $widthElement) > $widthList + 2;
		$response['resize'] = ($width + (float) $widthElement) - $widthList;
		$response['weigth'] = $width;

		return $response;
	}

	/**
	 * This method resize all elements of the list to ensure that the sum do not exceed 100%
	 * 
	 * @param		Float			$resize					The percent to resize
	 * @param		Float			$elId					The id element
	 * @param		Float			$weigth					Weigth
	 * @param		Object			$listModel				Object of the frontend list model
	 * @param		Boolean			$elChangedType			Does element changed the type?
	 * 
	 * @return		Null
	 * 
	 * @since		v4.3.1
	 */
	private function resizeWidthElements($resize, $weigth, $elId, $listModel, $elChangedType) 
	{
		$elements = $listModel->getElements();

		foreach ($elements as $el) {
			if(!$el->getElement()->show_in_list_summary || $el->getId() == (int) $elId || $elChangedType) {
				continue;
			}

			$opts = Array();
			$params = $el->getParams();
			$paramsEl = json_decode($params, true);

			$cssCel = $params->get('tablecss_cell');
			$cssHeader = $params->get('tablecss_header');
			preg_match('/\d+(\.\d+)?/', $cssCel, $matches);

			$actualWidth = (float) $matches[0];
			$newWidth = round($actualWidth - ($actualWidth / $weigth * $resize), 2);
			$cssCellNew = preg_replace('/'.$actualWidth.'/', $newWidth, $cssCel, 1);
			$cssHeaderNew = preg_replace('/'.$actualWidth.'/', $newWidth, $cssHeader, 1);

			$paramsEl['tablecss_cell'] = $cssCellNew;
			$paramsEl['tablecss_header'] = $cssHeaderNew;

			$opts['id'] = $el->getId();
			$opts['published'] = $el->getElement()->published;
			$opts['validationrule'] = $paramsEl['validations'];
			$opts['params'] = $paramsEl;
			$this->syncParams($opts, $listModel);

			$modelElement = new FabrikAdminModelElement();
			$modelElement->getState(); 	//We need do this to set __state_set before the save
			$modelElement->save($opts);
		}
	}

	/**
	 * This method adds a flag on element name to change the type
	 * 
	 * @param		String		$name			The name to check
	 * @param		Object			$listModel			Object of the frontend list model
	 * 
	 * @return		String
	 * 
	 * @since		v4.3.1
	 */
	private function checkNameElementToChangeType($name, $listModel) 
	{
		$db = Factory::getContainer()->get('DatabaseDriver');

		$query = $db->getQuery(true);
		$query->select('name')->from('#__fabrik_elements')->where('group_id IN (' . implode(',', array_keys($listModel->getFormModel()->getGroups())) . ')');
		$db->setQuery($query);
		$elements = $db->loadColumn();

        $continue = false;
        $flag = 1;

        while ($continue === false) {
            if($flag == 1) {
                $result = in_array($name, $elements);
            } else {
                $result = in_array($name . '_' . $flag, $elements);
            }

            if ($result) {
                $flag++;
            } else {
                $continue = true;
            }
        }

        if($flag == 1) {
            $result = $name;
        } else {
            $result = $name . "_{$flag}";
        }

        return $result;
    }

	/**
	 * This method verify if the element need to update the structure and update them
	 * 
	 * @param		Object			$modelElement			Object of the element model
	 * 
	 * @return		Boolean
	 * 
	 * @since		v4.3
	 */
	private function updateElement($modelElement) 
	{
		$updateElement = empty($modelElement->app->getUserState('com_fabrik.redirect'));

		if($updateElement) {
			return false;
		}

		return true;
	}

	/**
	 * Method that format the string to remove special caracters and accents
	 * 
	 * @param		Object		$val		The listmodel object
	 * 
	 * @return  	Bool
	 * 
	 * @since 		version 4.1.3
	 */
	private function formatValue($val)
	{
		return preg_replace('/[^A-Za-z0-9]/', '_', trim(strtolower((new Transliterate)->utf8_latin_to_ascii($val))));
	}

	/**
	 * Method that save the related list element, creating/editing the new group
	 * 
	 * @param		Object			$listModel			The listmodel object
	 * @param		Array			&$opts				The element options
	 * @param		Array			&params				The element params
	 * @param		Boolean			$trash				The element will be moved to trash or not
	 * 
	 * @return  	Bool
	 * 
	 * @since		version 4.1.0
	 */
	private function groupToElementRelatedList($listModel, &$opts, &$params, $trash=false)
	{
		$idForm = $listModel->getFormModel()->getId();

		if($opts['id'] == '0' || !isset($opts['group_id_old'])) {
			$new = true;
			$optsGroup['form'] = (string) $idForm;
		} else {
			if($opts['group_id_old'] == $opts['group_id']) {
				return;
			}

			$new = false;
			$optsGroup['id'] = (string) $opts['group_id_old'];
		}

		$optsGroup['name'] = $opts['label'];
		$optsGroup['published'] = $trash ? '0' : '1';
		$optsGroup['params']['repeat_group_show_first'] = $opts['published'] == '0' ? '0' : "2";

		return $this->newGroupToElements($listModel, $optsGroup, $new);
	}

	/**
	 * Method that save the longtext element, creating/editing the new group
	 * 
	 * @param		Object			$listModel			The listmodel object
	 * @param		Array			&$opts				The element options
	 * @param		Array			&params				The element params
	 * @param		Boolean			$trash				The element will be moved to trash or not
	 * 
	 * @return  	Boolean
	 * 
	 * @since		version 4.3.1
	 */
	private function groupToLongtextElement($listModel, &$opts, &$params, $trash=false)
	{
		$idForm = $listModel->getFormModel()->getId();

		if(!$trash) {
			$new = true;
			$optsGroup['form'] = (string) $idForm;
		} else {
			if($opts['group_id_old'] == $opts['group_id']) {
				return;
			}

			$new = false;
			$optsGroup['id'] = (string) $opts['group_id_old'];
		}

		$optsGroup['name'] = $opts['label'];
		$optsGroup['published'] = $trash ? '0' : '1';
		$optsGroup['params']['repeat_group_show_first'] = $opts['published'] == '0' ? '0' : "1";

		return $this->newGroupToElements($listModel, $optsGroup, $new);
	}

	/**
	 * Method that create/edit the new group
	 * 
	 * @param		Object			$listModel			The listmodel object
	 * @param		Array			$optsGroup			The group options
	 * @param		Boolean			$new				Is it a new element?
	 * 
	 * @return  	Boolean
	 * 
	 * @since		version 4.3.1
	 */
	private function newGroupToElements($listModel, $optsGroup, $new)
	{
		$modelGroup = new FabrikAdminModelGroup();

		$optsGroup['label'] = '';
		$optsGroup['is_join'] = "0";
		$optsGroup['tags'] = Array();

		$optsGroup['params']['repeat_group_button'] = "0";
		$optsGroup['params']['group_columns'] = "1";
		$optsGroup['params']['labels_above'] = "1";
		$optsGroup['params']['labels_above_details'] = "1";

		$modelGroup->setState('task', 'apply');
		$modelGroup->save($optsGroup);

		return $new ? $modelGroup->getState('group.id') : $optsGroup['id'];
	}

	/**
	 * Method that save the related list element, creating/editing the new module
	 * 
	 * @param		Object			$listModel			The listmodel object
	 * @param		Array			&$opts				The element options
	 * @param		Array			&$params			The element params
	 * @param		Boolean			$trash				The element will be moved to trash or not
	 * 
	 * @return  	Boolean
	 * 
	 * @since 		version 4.1.0
	 */
	private function moduleToElementRelatedList($listModel, &$opts, &$params, $trash=false) 
	{
		$modelModule = new ModuleModel();
		$listModelRelated = new FabrikFEModelList();

		$listModelRelated->setId($opts['related_list']);
		$idRelatedList = $opts['related_list'];

		if($opts['id'] == '0' || !isset($opts['module_id_old'])) {
			$new = true;
		} else {
			$new = false;
			if($opts['group_id_old'] == $opts['group_id']) {
				return;
			}

			if($trash) {
				$optsModule['id'] = $opts['module_id_old'];
			} else {
				$displayElement = $listModel->getElements('id')[$opts['id']]->getElement();
				$defaultColumn = $displayElement->get('default');
				preg_match('/{loadmoduleid (\d+)}/', $defaultColumn, $match);
				$optsModule['id'] = $match[1];
			}
		}

		// Data to pre filters
		$relatedTable = $listModelRelated->getTable()->get('db_table_name');
		$optsPreFilters['filter-join'][] = 'AND';
		$optsPreFilters['filter-fields'][] =  $relatedTable. '.' . $opts['related_list_element'] . '_raw';
		$optsPreFilters['filter-conditions'][] = 'equals';
		$optsPreFilters['filter-value'][] = '$app = JFactory::getApplication(); $jinput = $app->getInput(); $id = $jinput->getInt("rowid", 0);  return $id;';
		$optsPreFilters['filter-eval'][] = '1';
		$optsPreFilters['filter-access'][] = '1';

		// Data to configure the module
		$optsModule['title'] = Text::sprintf('PLG_FABRIK_LIST_EASY_ADMIN_NAME_MODULE', $idRelatedList);
		$optsModule['module'] = 'mod_fabrik_list';
		$optsModule['published'] = $trash ? '0' : $opts['published'];
		$optsModule['is_join'] = "0";
		$optsModule['access'] = "1";
		$optsModule['tags'] = Array();
		$optsModule['language'] = "*";

		// Data to params
		$optsModule['params']['list_id'] = $idRelatedList;
		$optsModule['params']['useajax'] = "0";
		$optsModule['params']['fabriklayout'] = "jlowcode_admin";
		$optsModule['params']['show_filters'] = "0";
		$optsModule['params']['prefilters'] = json_encode($optsPreFilters);

		$modelModule->getState();
		$modelModule->save($optsModule);
		return $new ? $modelModule->getState('module.id') : $optsModule['id'];
	}

	/**
	 * Method that save the related list element, editing the related list
	 * 
	 * @param		Object		$listModel		The listmodel object
	 * @param		Array		&$opts			The element options
	 * @param		Array		&$params		The element params
	 * 
	 * @return  	Boolean
	 * 
	 * @since 		version 4.1.0
	 */
	private function configureListToElementRelatedList($listModel, &$opts, &$params) 
	{
		$listModelRelated = new FabrikAdminModelList();
		$listModelRelatedFE = new FabrikFEModelList();

		$listModelRelatedFE->setId($opts['related_list']);
		$tableName = $listModelRelatedFE->getTable()->get('db_table_name');
		$tableNameActual = $listModel->getTable()->get('db_table_name');
		$relatedColumn = $opts['related_list_element'];

		// Data to configure the module
		$optsList['id'] = $opts['related_list'];

		// Data to params
		$optsList['params']['addurl'] = "?{$tableName}___{$relatedColumn}_raw={{$tableNameActual}___id}";

		$this->syncParams($optsList, $listModelRelatedFE, true);
		$listModelRelated->getState();
		$listModelRelated->save($optsList);

		return true;
	}

	/**
	 * Method that save the related list element, editing the related form
	 * 
	 * @param		Object		$listModel		The listmodel object
	 * @param		Array		&$opts			The element options
	 * @param		Array		&$params		The element params
	 * 
	 * @return  	Boolean
	 * 
	 * @since 		version 4.1.0
	 */
	private function configureFormToElementRelatedList($listModel, &$opts, &$params) 
	{
		$app = Factory::getApplication();
		$listModel->reset();
		$input = $app->input;

		$formModelRelated = new FabrikAdminModelForm();
		$formModelRelatedFE = new FabrikFEModelForm();
		$listModelRelatedFE = new FabrikFEModelList();

		$listModelRelatedFE->setId($opts['related_list']);
		$idFormRelated = $listModelRelatedFE->getFormModel()->getId();
		$idForm = $listModel->getFormModel()->getId();
		$formModelRelatedFE->setId($idFormRelated);

		$groupsForm = $formModelRelatedFE->getGroups();
		$propertiesForm = $formModelRelatedFE->getTable()->getProperties();
		$properties = $listModel->getFormModel()->getTable()->getProperties();
		$groups = $listModel->getFormModel()->getGroups();
		$tableName = $listModelRelatedFE->getTable()->get('db_table_name');
		$tableNameActual = $listModel->getTable()->get('db_table_name');
		$relatedColumn = $opts['related_list_element'];

		/**
		 * Configure related form
		 */
		// Data to configure the module
		$optsForm['id'] = $idFormRelated;
		$optsForm['current_groups'] = array_keys($groupsForm);
		$optsForm['database_name'] = $propertiesForm['db_table_name'];
		$jumpPage = "/" . explode('/', trim(FabrikWorker::goBackAction(), '"\''))[3] . "/details/{$idForm}/{{$tableName}___{$relatedColumn}_raw}";
		$redirectCond = '
			use Joomla\CMS\Uri\Uri;
			$uri = Uri::getInstance();
			$var = $uri->getVar("' . $tableName . '___' . $relatedColumn . '_raw");
			return $var != "{' . $tableNameActual . '___id}" ? true : false;
		';

		$pluginsForm = Array();
		foreach ($propertiesForm as $key => $val) {
			if(!array_key_exists($key, $optsForm)) {
				$optsForm[$key] = $propertiesForm[$key];
			}

			if($key == 'params') {
				$optsForm[$key] = json_decode($optsForm[$key], true);
				$optsForm[$key]['jump_page'] = $jumpPage;
				$optsForm[$key]['redirect_conditon'] = $redirectCond;
				$pluginsForm['plugin'] = $optsForm[$key]['plugins'];
				$pluginsForm['plugin_locations'] = $optsForm[$key]['plugin_locations'];
				$pluginsForm['plugin_events'] = $optsForm[$key]['plugin_events'];
				$pluginsForm['plugin_description'] = $optsForm[$key]['plugin_description'];
				$pluginsForm['plugin_state'] = $optsForm[$key]['plugin_state'];
			}
		}

		// Data to configure the new plugin redirect
		if(!in_array('redirect', json_decode($propertiesForm['params'], true)['plugins'])) {
			$pluginsForm['plugin'][] = 'redirect';
			$pluginsForm['plugin_locations'][] = 'both';
			$pluginsForm['plugin_events'][] = 'new';
			$pluginsForm['plugin_description'][] = Text::_("PLG_FABRIK_LIST_EASY_ADMIN_PLUGIN_REDIRECT_DESC");
			$pluginsForm['plugin_state'][] = '1';
		}

		$input->set('jform', $pluginsForm);
		$formModelRelated->getState(); 	//We need do this to set __state_set before the save
		$formModelRelated->save($optsForm);

		/**
		 * Configure main form
		 */
		// Data to configure the form
		$placeholderId = "{{$tableNameActual}___id}";
		$jumpPage = "/" . explode('/', trim(FabrikWorker::goBackAction(), '"\''))[3] . "/details/{$idForm}/{$placeholderId}";
		$pluginsForm = Array();
		$optsForm = Array();
		$optsForm['id'] = $idForm;
		$optsForm['current_groups'] = array_keys($groups);
		$optsForm['database_name'] = $properties['db_table_name'];

		foreach ($properties as $key => $val) {
			if(!array_key_exists($key, $optsForm)) {
				$optsForm[$key] = $properties[$key];
			}

			if($key == 'params') {
				$optsForm[$key] = json_decode($optsForm[$key], true);
				$optsForm[$key]['jump_page'] = $jumpPage;
				$optsForm[$key]['redirect_conditon'] = '';
				$pluginsForm['plugin'] = $optsForm[$key]['plugins'];
				$pluginsForm['plugin_locations'] = $optsForm[$key]['plugin_locations'];
				$pluginsForm['plugin_events'] = $optsForm[$key]['plugin_events'];
				$pluginsForm['plugin_description'] = $optsForm[$key]['plugin_description'];
				$pluginsForm['plugin_state'] = $optsForm[$key]['plugin_state'];
			}
		}

		// Data to configure the new plugin redirect
		if(!in_array('redirect', json_decode($properties['params'], true)['plugins'])) {
			$pluginsForm['plugin'][] = 'redirect';
			$pluginsForm['plugin_locations'][] = 'both';
			$pluginsForm['plugin_events'][] = 'both';
			$pluginsForm['plugin_description'][] = Text::_("PLG_FABRIK_LIST_EASY_ADMIN_PLUGIN_REDIRECT_MAIN_FORM_DESC");
			$pluginsForm['plugin_state'][] = '1';
		}

		$input->set('jform', $pluginsForm);
		$formModelRelated->getState(); 	//We need do this to set __state_set before the save
		$formModelRelated->save($optsForm);

		return true;
	}

	/**
	 * Method that treated the data and save the order of the elements
	 * 
	 * @param		Object			$modelElement			Object of the admin list model
	 * @param		Array			$data					The data sent
	 * @param		Object			$listModel				Object of the frontend list model
	 * 
	 * @return  	Null
	 * 
	 * @since 		version 4.0.2
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
		if(($data['show_in_list'] == '' && !$idAtual && $data['type'] != 'link') || $idAtual == $idOrder) {
			return true;
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

		array_push($order, $x+1);
		if($idOrder == -1) {
			array_unshift($pks, $idAtual);
		}

		if($idOrder == -2) {
			array_push($pks, $idAtual);
		}
		

		// Before to save the ordering we need to change the permissions and later change again
		$originalRules = $this->changeRulesPermissons("change");

		try {
			$modelElement->saveorder($pks, $order);
		} catch (\Throwable $th) {
			$this->changeRulesPermissons("recover", $originalRules);
		}
		
		return $this->changeRulesPermissons("recover", $originalRules);
	}

	/**
	 * Method that change or recover the rules of table #__assets
	 * We need to do this because the elements ordering must be done originally only by admin users
	 * 
	 * @param		String			$mode			Object of the admin list model
	 * @param		String			$rule			The data sent
	 * 
	 * @return  	String|Boolean		
	 * 
	 * @since 		version 4.0.2
	 */
	private function changeRulesPermissons($mode, $rule=null)
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
		$user = Factory::getApplication()->getIdentity();
		$groups = $user->groups;

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
	 * Method that save the modal data to list
	 * 
	 * @param		Array			$data				The data sent
	 * @param		Object			$listModel			Object of the frontend list model
	 * 
	 * @return  	String			Success or false
	 * 
	 * @since 		version 4.0
	 */
	private function saveModalList($data, $listModel)
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
		$app = Factory::getApplication();
		$input = $app->input;

		$modelList = new FabrikAdminModelList();
		$modelForm = new FabrikAdminModelForm();
		$formModel = new FabrikFEModelForm();

		$formModel->setId($listModel->getFormModel()->getId());
		$groupsForm = $formModel->getGroups();

		$visibilityList = $data['visibility_list'];
		$viewLevelList = $listModel->getParams()->get('allow_edit_details');
		$viewLevel = $visibilityList == '3' ? $viewLevelList : $visibilityList;

		$properties = $listModel->getTable()->getProperties();
		$propertiesForm = $listModel->getFormModel()->getTable()->getProperties();

		$validate = $this->validateList($data);
		if($validate->error) {
			return json_encode($validate);
		}

		$dataList['label'] = $data['name_list'];
		$dataList['introduction'] = $data['description_list'];
		//$dataList['order_by'] = array($data['ordering_list']);			//Updated by input data order_by (js)
		//$dataList['order_dir'] = array($data['ordering_type_list']);		//Updated by input data order_dir (js)
		$dataList['access'] = $viewLevel;
		$dataList['created_by'] = $data['owner_list'];
		$dataList['created_by_alias'] = JFactory::getUser($data['owner_list'])->get('username');
		$dataList['published'] = $data['trash_list'] ? '0' : '1';

		foreach ($properties as $key => $val) {
			if(!array_key_exists($key, $dataList)) {
				$dataList[$key] = $properties[$key];
			}

			if($key == 'params') {
				$dataList[$key] = json_decode($dataList[$key], true);
				$dataList[$key]['width_list'] = $data['width_list'];
				$dataList[$key]['layout_mode'] = $data['layout_mode'];
				$dataList[$key]['allow_view_details'] = $viewLevel;
				$dataList[$key]['workflow_list'] = $data['workflow_list'] == 'true' ? '1' : '0';

				$this->configurePluginComparison($data, $dataList[$key], $listModel);
			}
		}

		$dataForm['label'] = !empty($data['name_form']) ? $data['name_form'] : $dataList['label'];
		$dataForm['current_groups'] = array_keys($groupsForm);
		$dataForm['database_name'] = $propertiesForm['db_table_name'];
		$dataForm['created_by'] = $data['owner_list'];
		$dataForm['created_by_alias'] = JFactory::getUser($data['owner_list'])->get('username');
		$dataForm['published'] = $data['trash_list'] ? '0' : '1';

		$pluginsForm = Array();
		foreach ($propertiesForm as $key => $val) {
			if(!array_key_exists($key, $dataForm)) {
				$dataForm[$key] = $propertiesForm[$key];
			}

			if($key == 'params') {
				$dataForm[$key] = json_decode($dataForm[$key], true);
				$dataForm[$key]['approve_for_own_records'] = $data['collab_list'];
				$dataForm[$key]['workflow_approval_by_vote'] = $data['approve_by_votes_list'] == 'true' ? '1' : '0';
				$dataForm[$key]['workflow_votes_to_approve'] = $data['votes_to_approve_list'];
				$dataForm[$key]['workflow_votes_to_disapprove'] = $data['votes_to_disapprove_list'];
				$pluginsForm['plugin'] = $dataForm[$key]['plugins'];
				$pluginsForm['plugin_locations'] = $dataForm[$key]['plugin_locations'];
				$pluginsForm['plugin_events'] = $dataForm[$key]['plugin_events'];
				$pluginsForm['plugin_description'] = $dataForm[$key]['plugin_description'];
				$pluginsForm['plugin_state'] = $dataForm[$key]['plugin_state'];
			}
		}

		if(!$validate->error) {
			try {
				$responseExtras = $this->extras($data, 'list');
			} catch (\Throwable $e) {
				$validate->error = true;
				$validate->message = $e->getMessage();
				return json_encode($validate);
			}

			$modelList->getState();
			$modelList->save($dataList);
			$input->set('jform', $pluginsForm);
			$modelForm->getState(); 	//We need do this to set __state_set before the save
			$modelForm->save($dataForm);

			// Configure admins list
			$data['admins_list'][] = $data['owner_list'];
			array_unique($data['admins_list']);
			$oldAdmins = $this->onGetUsersAdmins($viewLevelList);
			$this->configureAdminsList($data['admins_list'], $viewLevelList, $oldAdmins);
		}

		$validate = (object) array_merge((array)$responseExtras, (array)$validate);
		return json_encode($validate);
	}

	/**
	 * This method verify if plugin comparison was request or not
	 * 
	 * @param		Array			$data				The data sent
	 * @param		Array			$params				Params data of the list
	 * @param		Object			$listModel			Object of the frontend list model
	 * 
	 * @return		Null
	 * 
	 * @since		v4.3.1
	 */
	private function configurePluginComparison($data, &$params, $listModel) 
	{
		$trash = empty($data['comparison_list']);

		$plgComparison = array_search('comparison', $params['plugins']);
		if((!$plgComparison && $trash) || ($plgComparison && !$trash)) {
			return;
		}

		if($trash) {
			unset($params['plugins'][$plgComparison]);
			unset($params['plugin_description'][$plgComparison]);
			unset($params['plugin_state'][$plgComparison]);
			return;
		}

		$params['plugins'][] = 'comparison';
		$params['plugin_description'][] = Text::_("PLG_FABRIK_LIST_EASY_ADMIN_PLUGIN_COMPARISON_DESCRIPTION");
		$params['plugin_state'][] = "1";

		$elements = $listModel->getElements('id', true, true);
		foreach ($elements as $classEl) {
			$el = $classEl->getElement();

			$hide = ['id', 'created_by', 'indexing_text', 'created_ip', 'date_time'];
			if(in_array($el->get('name'), $hide)) continue;

			switch ($el->get('plugin')) {
				case 'fileupload':
					!isset($elFile) ? $elFile = $el->get('id') : $columns['comparison_columns'][] = $el->get('id');
					break;

				case 'field':
					$el->get('name') == 'name' && !isset($name) ? $elName = $el->get('id') : null;
					$columns['comparison_columns'][] = $el->get('id');
					break;

				default:
					$columns['comparison_columns'][] = $el->get('id');
					break;
			}
		}

		$params['main_column'] = $elName;
		$params['thumb_column'] = isset($elFile) ? $elFile : '';
		$params['comparison_access'] = '1';
		$params['list_comparison_columns'] = json_encode($columns);
	}

	/**
	 * This method execute extras configuration when we save the modal
	 * 
	 * @param		Array			$data				The data sent
	 * @param		String			$mode				List modal or element modal?
	 * 
	 * @return 		Object
	 * 
	 * @since 		version 4.0
	 */
	private function extras($data, $mode)
	{
		$db = Factory::getContainer()->get('DatabaseDriver');

		$response = new stdClass;
		switch ($mode) {
			case 'list':
				// Settings to update url
				$oldUrl = ltrim(Uri::getInstance()->getPath(), '/');
				$url = trim(strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $data['url_list'])), '-')), '_');
				$updateLink = ($url != $oldUrl);
				if ($updateLink) {
					$response->updateUrl = $this->updateUrlMenu($url, $data['listid']);
					$response->newUrl = $url;
				}

				// Settings to update list's thumb
				$listModel = JModelLegacy::getInstance('List', 'FabrikFEModel');
				$listModel->setId('1');
				$els = $listModel->getElements();
				foreach ($els as $el) {
					if($el->getElement()->name == 'miniatura') {
						$path = $el->getParams()->get('ul_directory');
					}
				}

				// Settings to update adm_cloner_listas table
				$update = new stdClass();
				$update->name = $data['name_list'];
				$update->description = $data['description_list'];
				$update->id_lista = $data['listid'];
				$update->user = $data['owner_list'];
				$update->link = "/" . ($updateLink ? $url : $oldUrl);
				$update->status = $data['trash_list'] ? '0' : '1';
				$update->miniatura = !empty($data['thumb_list']) ? $path . str_replace(' ', '_', $data['thumb_list']) : '';

				$db->updateObject('adm_cloner_listas', $update, 'id_lista');
				break;
			
			case 'element':
				break;
		}

		return $response;
	}

	/**
	 * We verify the admin users
	 * New admin users will be added or removed
	 * 
	 * @param		Array			$users				The users to verify
	 * @param		String			$viewLevel			The view level od the list
	 * @param		Object			$oldAdmins			The original admins of the list
	 * 
	 * @return		Boolean
	 * 
	 * @since		version 4.1.2	
	 */
	private function configureAdminsList($users, $viewLevel, $oldAdmins) 
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
		$userModel = new UserModel();

		$db->setQuery("SELECT `rules` FROM `#__viewlevels` WHERE `id` = $viewLevel;");
		$rules = json_decode($db->loadResult());
		unset($rules[array_search('8', $rules)]); // Dont show super users

		$groupId = $rules[0];
		$data = Array();
		$usersExclused = array_diff($oldAdmins, $users);

		// Adding users
		foreach ($users as $idUser) {
			$user = User::getInstance($idUser);
			$groups = array_keys($user->groups);

			if(!in_array($groupId, $groups)) {
				$groups[] = $groupId;
				$data['id'] = $idUser;
				$data['groups'] = $groups;

				$userModel->getState();
				$userModel->save($data);
			}
		}

		//Removing users
		foreach ($usersExclused as $idUser) {
			$user = User::getInstance($idUser);
			$groups = array_keys($user->groups);

			if(in_array($groupId, $groups)) {
				unset($groups[array_search($groupId, $groups)]);
				$data['id'] = $idUser;
				$data['groups'] = $groups;

				$userModel->getState();
				$userModel->save($data);
			}
		}

		return true;
	}

	/**
	 * We need update the params that already exists in elements
	 * 
	 * @param   	Array 			$opts				Options and params
	 * @param   	Object 			$listModel			Object of list
	 * @param		Boolean			$list				False for elements and true for lists
	 * 
	 * @return  	String|Null
	 * 
	 * @since 		version 4.0
	 */
	private function syncParams(&$opts, $listModel, $list=false) 
	{
		if($list) {
			$dataEl = $listModel->getTable()->getProperties();
		} else {
			$idEl = $opts['id'];
			$element = $listModel->getElements('id', true, false)[$idEl];
			$dataEl = $element->element->getProperties();

			// For new version - cause validations to be added and not overwritten
			foreach ($element->getValidations() as $key => $validation) {
				$a = $opts['validationrule'];
			}

			$origName = $element->element->name;
		}


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

		return $origName;
	}

	/**
	 * We need update the id saved from input to create the elements correctly
	 * 
	 * @param   	Array 			$data				Source of options
	 * @param		Object			$listModel			Object of the frontend list model
	 * 
	 * @return  	Object
	 * 
	 * @since 		version 4.0
	 */
	private function validateElements(&$data, $listModel) 
	{
		$validate = new stdClass();
		$validate->error = false;
		$validate->message = "";

		// If the element is auto-complete, label must be exists
		if($data['type'] == 'autocomplete' && empty($data['label'])) {
			$validate->error = true;
			empty($data['label']) ? $validate->message = Text::sprintf('PLG_FABRIK_LIST_EASY_ADMIN_ERROR_ELEMENT_EMPTY', Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LABEL_LABEL')) : '';
			empty($data['listas']) ? $validate->message = Text::sprintf('PLG_FABRIK_LIST_EASY_ADMIN_ERROR_ELEMENT_EMPTY', Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LIST_LABEL')) : '';
		}

		// If the element is treeview, label and father must be exists
		if($data['type'] == 'treeview' && (empty($data['label']) || empty($data['father']))) {
			$validate->error = true;
			empty($data['father']) ? $validate->message = Text::sprintf('PLG_FABRIK_LIST_EASY_ADMIN_ERROR_ELEMENT_EMPTY', Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_FATHER_LABEL')) : '';
			empty($data['label']) ? $validate->message = Text::sprintf('PLG_FABRIK_LIST_EASY_ADMIN_ERROR_ELEMENT_EMPTY', Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LABEL_LABEL')) : '';
			empty($data['listas']) ? $validate->message = Text::sprintf('PLG_FABRIK_LIST_EASY_ADMIN_ERROR_ELEMENT_EMPTY', Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LIST_LABEL')) : '';
		}

		// If the element is dropdown or tags, options must be exists
		if(($data['type'] == 'dropdown' || $data['type'] == 'tags') && empty($data['options_dropdown'])) {
			$validate->error = true;
			$validate->message = Text::sprintf('PLG_FABRIK_LIST_EASY_ADMIN_ERROR_ELEMENT_EMPTY', Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_OPTIONS_DROPDOWN_LABEL'));
		}

		// If the element is related list, the list must be exists
		if($data['type'] == 'related_list' && empty($data['related_list'])) {
			$validate->error = true;
			$validate->message = Text::sprintf('PLG_FABRIK_LIST_EASY_ADMIN_ERROR_ELEMENT_EMPTY', Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_RELATED_LIST_LABEL'));
		}

		// If the element is related list, the list must be exists
		if($data['type'] == 'related_list' && empty($data['related_list_element'])) {
			$validate->error = true;
			$validate->message = Text::sprintf('PLG_FABRIK_LIST_EASY_ADMIN_ERROR_ELEMENT_EMPTY', Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_RELATED_ELEMENT_LIST_LABEL'));
		}

		// If the element is link, thumb, title and description must be exists
		if($data['type'] == 'link' && (empty($data['thumb_link']) || empty($data['title_link']) || empty($data['description_link']))) {
			$validate->error = true;
			$validate->message = Text::sprintf('PLG_FABRIK_LIST_EASY_ADMIN_ERROR_ELEMENTS_EMPTY', Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_THUMB_LINK_LABEL'), Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TITLE_LINK_LABEL'), Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_DESCRIPTION_LINK_LABEL'));
		}

		// The new element must be a type
		if($data['type'] == '') {
			$validate->error = true;
			$validate->message = Text::sprintf('PLG_FABRIK_LIST_EASY_ADMIN_ERROR_ELEMENT_EMPTY', Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_TYPE_LABEL'));
		}

		// The new element must be a name
		if($data['name'] == '') {
			$validate->error = true;
			$validate->message = Text::sprintf('PLG_FABRIK_LIST_EASY_ADMIN_ERROR_ELEMENT_EMPTY', Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_NAME_LABEL'));
		}

		// Element width cant be zero
		if($data['show_in_list'] && $data['width_field'] == '0'&& $data['valIdEl'] != '0') {
			$validate->error = true;
			$validate->message = Text::sprintf('PLG_FABRIK_LIST_EASY_ADMIN_ERROR_WIDTH_ELEMENT', Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_WIDTH_FIELD_LABEL'));
		}

		// The new name must be unique
		if(!$this->checkColumnName($data['name'], $listModel) && $data['valIdEl'] == '0') {
			$validate->error = true;
			$validate->message = Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ERROR_ELEMENT_NAME_UNIQUE');
		}

		return $validate;
	}
	
	/**
	 * Method that check if the name of the new element is already in use
	 * 
	 * @param		String			$name				The name to check
	 * @param		Object			$listModel			Object of the frontend list model
	 * 
	 * @return		Boolean
	 * 
	 * @since		version 4.1.2
	 */
	private function checkColumnName(&$name, $listModel) 
	{
		$columnsNames = (array) $this->processElementsNames($listModel->getElements(true), false);
		$name = substr(strtolower($name), 0, 40);

		if(in_array($name, $columnsNames)) {
			return false;
		}

		return true;
	}

	/**
	 * We need update the id saved from input to edit the list correctly
	 *
	 * @param		Array 		$data		Source of options
	 *
	 * @return  	Object
	 * 
	 * @since 		version 4.0
	 */
	private function validateList($data) 
	{
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
	 * Method that returns the admins users of the list 
	 * 
	 * @param		String				$viewLevel		View level to search the users related
	 * 
	 * @return  	String|Array		Json Data|Array data
	 * 
	 * @since 		version 4.1.2
	 */
	public function onGetUsersAdmins($viewLevel=null)
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
		
		$req = $viewLevel ? false : true;
		$viewLevel = $req ? $_POST['viewLevel'] : $viewLevel;

		$db->setQuery("SELECT `rules` FROM `#__viewlevels` WHERE `id` = $viewLevel;");
		$rules = json_decode($db->loadResult());
		unset($rules[array_search('8', $rules)]); // Dont show super users

		$query = $db->getQuery(true);
		$query->select(['u.'.$db->qn('id'), 'u.'.$db->qn('name')])
			->from($db->qn('#__users') . ' AS u')
			->join('LEFT', $db->qn('#__user_usergroup_map') . ' AS ug_map ON ug_map.' . $db->qn('user_id') . ' = u.' . $db->qn('id'))
			->where('ug_map.' . $db->qn('group_id') . ' IN ("' . implode('","', $rules) . '")');
		$db->setQuery($query);
		$users = $db->loadObjectList();

		/**
		 * If we are a ajax request send the json users, if not return the users object
		 */
		if($req) {
			echo json_encode($users);
		} else {
			$idsUsers = Array();
			foreach ($users as $user) {
				$idsUsers[] = $user->id;
			}
			return $idsUsers;
		}
	}

	/**
	 * We need update the id saved from input to create the elements correctly
	 *
	 * @param		String			$context		Context of the application
	 * @param		Object			$item			Item data
	 * @param		Boolean			$isNew			Is new or not?
	 * @param		Array			$data			Data of the context
	 * 
	 * @return  	Null
	 * 
	 * @since 		version 4.0
	 */
	public function onContentAfterSave($context, $item, $isNew, $data=[]) 
	{
		$app = Factory::getApplication();
		$input = $app->input;
		$id = $item->id;
		
		//We don't have run
		if(!$this->mustRun()) {
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
	 * @param		String			$id					Identity of the element
	 * @param		String			$label				Label of the element
	 * @param		String			$tip				Tip of the element
	 * @param   	Array 			$showOnTypes		When each element must show on each type of elements (Used in js)
	 * @param		Boolean			$fixed				If the element is fixed always or must show and hide depending of the types above
	 * @param		String			$modal				If the element is at list modal or element modal
	 *
	 * @return  	Array
	 * 
	 * @since 		version 4.0
	 */
	private function getDataLabel($id, $label, $tip, $showOnTypes='', $fixed=true, $modal='element') 
	{
		$class = $fixed ?  '' : "modal-$modal type-" . implode(' type-', $showOnTypes);

		$data = Array(
			'canView' => true,
			'id' => $id,
			'canUse' => true,
			'label' => $label,
			'hasLabel' => true,
			'view' => 'form',
			'tipText' => $tip,
			'tipOpts' => (object) ['formTip' => true, 'position' => 'top-left', 'trigger' => 'hover', 'notice' => true],
			'labelClass' =>  "form-label fabrikLabel {$class}",
		);

		return $data;
	}

	/**
	 * Adding css style
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.0
	 */
	private function customizedStyle() 
	{
		$document = Factory::getDocument();
		$css = '.dropdown-menu {z-index: 9999 !important;}';
		$css .= '.select2-dropdown {z-index: 9999 !important;}';
		$css .= '.btn-easyadmin-modal {min-height: 30px; width: 100%; border-radius: 12px; color: rgb(255, 255, 255); background-color: rgb(0, 62, 161);}';
		$document->addStyleDeclaration($css);
	}

	/**
	 * Method that receives the request when installing the plugin to create the list, form and elements 
	 * needed to render the modal on the front end
	 * 
	 * @return  	Null
	 * 
	 * @since 		version 4.2
	 */
	public function onRequestInstall() 
	{
		$db = Factory::getContainer()->get('DatabaseDriver');

		$paramsToSave = Array();
		$response = new stdClass();

		$response->msg = Text::_('PLG_FABRIK_LIST_EASYADMIN_REQUEST_INSTALL_SUCCESS');
		$response->success = true;

		$dbTableName = $db->getPrefix() . $this->dbTableNameModal;
		$exist = PlgFabrik_ListEasyAdminInstallerScript::verifyTableExist($dbTableName);

		if($exist) {
			try {
				$this->updateStrutureNewVersion($dbTableName);
			} catch (\Throwable $e) {
				$response->msg = $e->getMessage();
				$response->success = false;
			}

			echo json_encode($response);
			return;
		}

		try {
			$formId = $this->createForm();
			$listId = $this->createList($formId, $dbTableName);
			$groupId = $this->createGroup();
			$elementsId = $this->createElements($groupId);

			$this->createBondFormGroup($formId, $groupId);
			$this->createTable($dbTableName);
			$this->updateStrutureNewVersion();
        }
        catch (\Throwable $e) {
			$response->msg = $e->getMessage();
			$response->success = false;
        }

		$paramsToSave['form'] = $formId;
		$paramsToSave['list'] = $listId;
		$paramsToSave['groupId'] = $groupId;
		$paramsToSave['elementsId'] = $elementsId;
		$this->saveParams($dbTableName, $paramsToSave);

		echo json_encode($response);
	}

	/**
	 * This method redirect to methods that really make the differency in structure of plugin
	 * Run always when the plugin is updated, but in each method we verify if the update must run or not
	 * 
	 * @param		String 		$dbTableName		The name of the reference table
	 * 
	 * @return		Null
	 * 
	 * @since		v4.3.2
	 */
	private function updateStrutureNewVersion($dbTableName)
	{
		$this->updateStructureV432($dbTableName);
		$this->updateStructureV434($dbTableName);
	}

	/**
	 * This method update the struture to version 4.3.2
	 * In this case we need add a new column in reference table called owner_list,
	 * create a new element in #__fabrik_elements and update the params in the reference table
	 * 
	 * @param		String 		$dbTableName		The name of the reference table
	 * 
	 * @return		Null
	 * 
	 * @since		v4.3.2
	 */
	private function updateStructureV432($dbTableName)
	{
		$db = Factory::getContainer()->get('DatabaseDriver');

		// Check column
		$query = $db->getQuery(true);
		$query->select('1')
			->from($db->qn('information_schema.columns'))
			->where($db->qn('table_schema') . ' = (SELECT DATABASE())')
			->where($db->qn('table_name') . ' = ' . $db->q($dbTableName))
			->where($db->qn('column_name') . ' = ' . $db->q('owner_list'));
		$db->setQuery($query);
		$exist = (Boolean) $db->loadResult();

		if($exist) return;

		$query = $db->getQuery(true);
		$query->select($db->qn('params'))
			->from($db->qn($dbTableName));
		$db->setQuery($query);
		$params = json_decode($db->loadResult(), true);
		$groupId = $params['groupId'];

		// Create element
		$idElements = Array();
		$idElement = $this->createElementOwnerList($groupId);

		// Create column
		$sql = "ALTER TABLE $dbTableName ADD COLUMN `owner_list` int DEFAULT NULL AFTER listas";
		$db->setQuery($sql);
		$db->execute();

		$params['elementsId']['owner_list'] = $idElement;
		$query = $db->getQuery(true);
		$query->update($db->qn($dbTableName))
			->set($db->qn('params') . ' = ' . $db->q(json_encode($params)));
		$db->setQuery($query);
		$db->execute();
	}

	/**
	 * This method update the struture to version 4.3.4
	 * In this case we need add a new column in reference table called thumb_list,
	 * create a new element in #__fabrik_elements and update the params in the reference table
	 * 
	 * @param		String 		$dbTableName		The name of the reference table
	 * 
	 * @return		Null
	 * 
	 * @since		v4.3.4
	 */
	private function updateStructureV434($dbTableName)
	{
		$db = Factory::getContainer()->get('DatabaseDriver');

		// Check column
		$query = $db->getQuery(true);
		$query->select('1')
			->from($db->qn('information_schema.columns'))
			->where($db->qn('table_schema') . ' = (SELECT DATABASE())')
			->where($db->qn('table_name') . ' = ' . $db->q($dbTableName))
			->where($db->qn('column_name') . ' = ' . $db->q('thumb_list'));
		$db->setQuery($query);
		$exist = (Boolean) $db->loadResult();

		if($exist) return;

		$query = $db->getQuery(true);
		$query->select($db->qn('params'))
			->from($db->qn($dbTableName));
		$db->setQuery($query);
		$params = json_decode($db->loadResult(), true);
		$groupId = $params['groupId'];

		// Create element
		$idElements = Array();
		$idElement = $this->createElementThumbList($groupId);

		// Create column
		$sql = "ALTER TABLE $dbTableName ADD COLUMN `thumb_list` int DEFAULT NULL AFTER owner_list";
		$db->setQuery($sql);
		$db->execute();

		$params['elementsId']['thumb_list'] = $idElement;
		$query = $db->getQuery(true);
		$query->update($db->qn($dbTableName))
			->set($db->qn('params') . ' = ' . $db->q(json_encode($params)));
		$db->setQuery($query);
		$db->execute();
	}

	/**
	 * Method that create the databasejoin owner list element
	 * 
	 * @param		Int 		$groupId			The id of the group related
	 * 
	 * @return  	Int|Boolean
	 * 
	 * @since 		version 4.3.2
	 */
	private function createElementOwnerList($groupId)
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
		$date = Factory::getDate();

		$params = json_encode(Array(
			'database_join_display_type' => 'auto-complete',
			'database_join_display_style' => 'only-autocomplete',
			'join_db_name' => '#__users',
			'join_val_column' => 'name',
			'join_key_column' => 'id',
			'database_join_show_please_select' => '1',
			'dbjoin_autocomplete_rows' => 10,
			'database_join_where_sql' => ''
		));

		$info = new stdClass();
		$info->id = 0;
		$info->name = 'owner_list';
		$info->group_id = $groupId;
		$info->plugin = 'databasejoin';
		$info->label = Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_OWNER_LIST_LABEL');
		$info->created = $date->toSql();
        $info->created_by = $this->user->id;
        $info->created_by_alias = $this->user->username;
        $info->modified = $date->toSql();
        $info->modified_by = $this->user->id;
        $info->published = 1;
        $info->access = 1;
        $info->params = $params;

		$insert = $db->insertObject('#__fabrik_elements', $info, 'id');

		return $insert ? $db->insertid() : false;
	}

	/**
	 * Method that create the databasejoin thumb list element
	 * 
	 * @param		Int 		$groupId			The id of the group related
	 * 
	 * @return  	Int|Boolean
	 * 
	 * @since 		version 4.3.4
	 */
	private function createElementThumbList($groupId)
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
		$date = Factory::getDate();

		$params = json_encode(Array(
			'ul_max_file_size' => '1048576', 
			'ul_directory' => 'images/stories/',
			'image_library' => 'gd2',
			'fileupload_crop_dir' => 'images/stories/crop',
			'ul_file_increment' => '1',
			'ajax_show_widget' => '0',
			'fu_make_pdf_thumb' => '0',
			'make_thumbnail' => '0',
			'ajax_max' => '1',
			'ajax_dropbox_width' => '0',
			'ajax_upload' => '1',
			'fu_show_image_in_table' => '1',
			'fu_show_image' => '2',
		));

		$info = new stdClass();
		$info->id = 0;
		$info->name = 'thumb_list';
		$info->group_id = $groupId;
		$info->plugin = 'fileupload';
		$info->label = Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LIST_THUMB_LABEL');
		$info->created = $date->toSql();
        $info->created_by = $this->user->id;
        $info->created_by_alias = $this->user->username;
        $info->modified = $date->toSql();
        $info->modified_by = $this->user->id;
        $info->published = 1;
        $info->access = 1;
        $info->params = $params;

		$insert = $db->insertObject('#__fabrik_elements', $info, 'id');

		return $insert ? $db->insertid() : false;
	}

	/**
	 * Method that create the form
	 * 
	 * @return  	String|Boolean
	 * 
	 * @since 		version 4.2
	 */
	private function createForm()
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
		$date = Factory::getDate();

		$info = new stdClass();
        $info->id = 0;
        $info->label = Text::_('PLG_FABRIK_LIST_EASY_ADMIN_FORM_NAME');
        $info->record_in_database = '1';
        $info->intro = '';
        $info->created = $date->toSql();
        $info->created_by = $this->user->id;
        $info->created_by_alias = $this->user->username;
        $info->modified = $date->toSql();
        $info->modified_by = $this->user->id;
        $info->publish_up = $date->toSql();
        $info->published = 1;
        $info->params = Text::_('PLG_FABRIK_LIST_EASYADMIN_FORM_PARAMS');

        $insert = $db->insertObject('#__fabrik_forms', $info, 'id');

		return $insert ? $db->insertid() : false;
	}

	/**
	 * Method that create the list
	 * 
	 * @param		Int 		$formId				The id of the form related
	 * @param		String 		$dbTableName		The name of the table that will be create
	 * 
	 * @return  	String|Boolean
	 * 
	 * @since 		version 4.2
	 */
	private function createList($formId, $dbTableName)
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
		$date = Factory::getDate();

		$info = new stdClass();
        $info->id = 0;
        $info->label = Text::_('PLG_FABRIK_LIST_EASY_ADMIN_LIST_NAME');
        $info->introduction = Text::_('PLG_FABRIK_LIST_EASY_ADMIN_LIST_INTRODUCTION');
        $info->form_id = $formId;
        $info->db_table_name = $dbTableName;
        $info->db_primary_key = $dbTableName . '.id';
        $info->auto_inc = 1;
        $info->connection_id = 1;
        $info->created = $date->toSql();
        $info->created_by = $this->user->id;
        $info->created_by_alias = $this->user->username;
        $info->modified = $date->toSql();
        $info->modified_by = $this->user->id;
        $info->published = 1;
        $info->publish_up = $date->toSql();
        $info->access = 1;
        $info->rows_per_page = 10;
        $info->filter_action = 'onchange';
        $info->params = Text::_('PLG_FABRIK_LIST_EASYADMIN_FORM_PARAMS');

        $insert = $db->insertObject('#__fabrik_lists', $info, 'id');

		return $insert ? $db->insertid() : false;
	}

	/**
	 * Method that create the group
	 * 
	 * @return  	String|Boolean
	 * 
	 * @since 		version 4.2
	 */
	private function createGroup()
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
		$date = Factory::getDate();

		$info = new stdClass();
        $info->id = 0;
        $info->name = Text::_('PLG_FABRIK_LIST_EASY_ADMIN_FORM_NAME');
        $info->label = Text::_('PLG_FABRIK_LIST_EASY_ADMIN_FORM_NAME');
        $info->css = '';
        $info->published = 1;
        $info->intro = '';
        $info->created = $date->toSql();
        $info->created_by = $this->user->id;
        $info->created_by_alias = $this->user->username;
        $info->modified = $date->toSql();
        $info->modified_by = $this->user->id;
        $info->params = Text::_('PLG_FABRIK_LIST_EASYADMIN_GROUP_PARAMS');

        $insert = $db->insertObject('#__fabrik_groups', $info, 'id');

		return $insert ? $db->insertid() : false;
	}

	/**
	 * Method that create the bond between form and group created
	 * 
	 * @param		Int 		$formId			The id of the form related
	 * @param		Int 		$groupId		The id of the group related
	 * 
	 * @return  	String|Boolean
	 * 
	 * @since 		version 4.2
	 */
	private function createBondFormGroup($formId, $groupId)
	{
		$db = Factory::getContainer()->get('DatabaseDriver');

		$info = new stdClass();
		$info->id = 0;
		$info->form_id = $formId;
		$info->group_id = $groupId;
		$info->ordering = 1;

		$insert = $db->insertObject('#__fabrik_formgroup', $info, 'id');

		return $insert ? $db->insertid() : false;
	}

	/**
	 * Method that create the elements needed
	 * 
	 * @param		Int 		$groupId				The id of the group related
	 * 
	 * @return  	Array
	 * 
	 * @since 		version 4.2
	 */
	private function createElements($groupId)
	{
		$idElements = Array();
		$idElements['list'] = $this->createElementList($groupId);

		return $idElements;
	}

	/**
	 * Method that create the databasejoin list element
	 * 
	 * @param		Int 		$groupId			The id of the group related
	 * 
	 * @return  	Int|Boolean
	 * 
	 * @since 		version 4.2
	 */
	private function createElementList($groupId) 
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
		$date = Factory::getDate();

		$params = json_encode(Array(
			'database_join_display_type' => 'auto-complete', 
			'database_join_display_style' => 'only-autocomplete',
			'join_db_name' => '#__fabrik_lists',
			'join_val_column' => 'label',
			'join_key_column' => 'db_table_name',
			'database_join_show_please_select' => '1',
			'dbjoin_autocomplete_rows' => 10,
			'database_join_where_sql' => 'SUBSTRING(`label`, 1, 1) != "_"'
		));

		$info = new stdClass();
		$info->id = 0;
		$info->name = 'listas';
		$info->group_id = $groupId;
		$info->plugin = 'databasejoin';
		$info->label = Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LIST_LABEL');
		$info->created = $date->toSql();
        $info->created_by = $this->user->id;
        $info->created_by_alias = $this->user->username;
        $info->modified = $date->toSql();
        $info->modified_by = $this->user->id;
        $info->published = 1;
        $info->access = 1;
        $info->params = $params;

		$insert = $db->insertObject('#__fabrik_elements', $info, 'id');

		return $insert ? $db->insertid() : false;
	}

	/**
	 * Method that create the table
	 * 
	 * @param		String 		$dbTableName		The name of the table that will be create
	 * 
	 * @return  	Boolean
	 * 
	 * @since 		version 4.2
	 */
	private function createTable($dbTableName)
	{
		$db = Factory::getContainer()->get('DatabaseDriver');

		$query = "
		CREATE TABLE `$dbTableName` (
			`id` int NOT NULL AUTO_INCREMENT,
			`date_time` datetime DEFAULT NULL,
			`listas` int DEFAULT NULL,
  			`params` mediumtext,
			PRIMARY KEY (`id`)
		)
		";

		$db->setQuery($query);
        $insert = $db->execute();

		return $insert ? true : false;
	}

	/**
	 * Method that save the params of the modal table
	 * 
	 * @return  	Null
	 * 
	 * @since 		version 4.2
	 */
	public function saveParams($dbTableName, $paramsToSave) 
	{
		$db = Factory::getContainer()->get('DatabaseDriver');

		$query = $db->getQuery(true);
		$query->insert($db->qn($dbTableName))
			->columns($db->qn('params'))
			->values($db->q(json_encode($paramsToSave)));
		$db->setQuery($query);
		$db->execute();
	}

	/**
	 * This method call methods processElements() and setUpBodyElements() to render the elements on workflow request
	 * 
	 * @return		Null
	 */
	public function onWorkflowBuildForm() 
	{
		$app = Factory::getApplication();
		$input = $app->input;

		if(!$this->getListModel()->getParams()->get('workflow_list', '1')) {
			return;
		}

		$formData = $input->getString('formData');
		$this->setFormData($formData);
		$this->setRequestWorkflow(true);
		$this->setElements();
		$body = $this->setUpBodyElements();

		echo $body;
	}

	/**
	 * This method returns the elements to build the edit form on workflow
	 * 
	 * @return		Null
	 */
	public function onBuildFormEditFieldsWfl() 
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
		$app = Factory::getApplication();

		$input = $app->input;
		$x = 0;

		if(!$this->getListModel()->getParams()->get('workflow_list', '1')) {
			return;
		}

		$this->setRequestWorkflow(true);

		$listModel = $this->getListModel();
		$elements = $listModel->getElements(true, true, false);

		// Set new fields
		$newFormData = $input->getString('formData');
		$this->setFormData($newFormData);
		$this->setRequestWorkflowOrig(false);
		$this->setElements();
		$newFields = $this->setUpBodyElements(1);

		// Set old fields
		$idEl = $newFormData[$this->prefixEl . '___valIdEl'];
		if($input->getString('req_status') == 'verify') {
			$oldFormData = (Array) $this->processElements($elements)->$idEl;
			$oldFormData = array_combine(
				array_map(fn($key) => $this->prefixEl . '___' .  $key, array_keys($oldFormData)),
				$oldFormData
			);
		} else {
			$oldFormData = $this->getLastRecordFormData($this->getListId(), $idEl);
		}

		$this->setFormData($oldFormData);
		$this->setRequestWorkflowOrig(true);
		$this->setElements();
		$oldFields = $this->setUpBodyElements(1);

		$changedFields = Array();
		foreach ($newFormData as $key => $value) {
			$value = $value == 'true' ? true : $value;
			$keyOrig = $key;
			$key = $key == $db->getPrefix() . $this->dbTableNameModal . '___listas' ? $this->prefixEl . '___listas' : $key;
			$oldVal = $oldFormData[$key];
			if(array_key_exists($key, $oldFormData)) {
				switch ($key) {
					case $this->prefixEl . '___options_dropdown':
						$arrVal = explode(',', $value);
						$arrValOld = array_map(function($opt) {return trim($opt);}, explode(',', $oldVal));
						$changed = count(array_diff($arrVal, $arrValOld));
						break;

					case $this->prefixEl . '___ordering_elements':
						$changed = $oldVal != '-2' && $value != '-2' ? $oldVal != $value : false;
						break;

					default:
						$changed = $oldVal != $value;
						break;
				}

				if($changed) {
					$changedFields[$x]['new'] = $newFields[$keyOrig];
					$changedFields[$x]['old'] = $oldFields[$keyOrig];
					$x++;
				}
			}
		}

		echo json_encode($changedFields);
	}

	/**
     * This method get the last formData from #__fabrik_requests table to compare on approve a edit request
     * 
	 * @param		Int			$listId				Id of the list
	 * @param		Int			$recordId			Id of the record
	 * 
     * @return      Array
     */
    private function getLastRecordFormData($listId, $recordId)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);
        $query->select($db->qn('form_data'))
            ->from($db->qn('#__fabrik_requests'))
            ->where($db->qn('req_record_id') . ' = ' . $db->q($recordId))
            ->where($db->qn('req_list_id') . ' = ' . $db->q($listId))
            ->where($db->qn('req_status') . ' <> ' . $db->q('verify'))
            ->order('req_id desc');
        $db->setQuery($query);

        return json_decode($db->loadResult(), true);
    }

	/**
	 * This method call validate elements method to validate the request for workflow
	 * 
	 */
	public function onValidateElements()
	{
		$app = Factory::getApplication();
		$input = $app->input;

		$listModel = $this->getListModel();
		$data = $listModel->removeTableNameFromSaveData($input->getString('formData'));

		$r = $this->validateElements($data, $listModel);
		echo json_encode($r);
	}

	/**
	 * This method in case of bad request will save the data in #__action_logs table
	 * 
	 */
	public function onSaveLogs()
	{
        $db = Factory::getContainer()->get('DatabaseDriver');
		$app = Factory::getApplication();

		$input = $app->input;

		$query = $db->getQuery(true);
		$query->insert($db->qn("#__action_logs"))
			->columns(implode(",", $db->qn(["message_language_key", "message", "log_date", "extension", "user_id", "item_id"])))
			->values(implode(",", $db->q([
				Text::_("PLG_FABRIK_LIST_EASY_ADMIN_ERROR"),
				$input->getString('message'),
				Date::getInstance()->toSql(),
				Text::_("PLG_FABRIK_LIST_EASY_ADMIN"),
				$this->user->id,
				$input->getInt('Itemid')
			]))
		);
        $db->setQuery($query);
		$db->execute($query);
	}

	/**
	 * Getter method to request workflow variable
	 *
	 * @return  	Boolean
	 * 
	 * @since 		version 4.3
	 */
	public function getRequestWorkflow() 
	{
		return $this->requestWorkflow;
	}

	/**
	 * Getter method to request workflow variable
	 *
	 * @param		Boolean			$requestWorkflow		If the request from workflow plugin
	 * 
	 * @return  	Null
	 * 
	 * @since 		version 4.3
	 */
	public function setRequestWorkflow($requestWorkflow)
	{
		$this->requestWorkflow = $requestWorkflow;
	}

	/**
	 * Getter method to form data variable
	 *
	 * @return  	Array
	 * 
	 * @since 		version 4.3
	 */
	public function getFormData() 
	{
		return $this->formData;
	}

	/**
	 * Getter method to formData variable
	 *
	 * @param		Array			$formData			Data provided by workflow to update/add fields
	 * 
	 * @return  	Null
	 * 
	 * @since 		version 4.3
	 */
	public function setFormData($formData)
	{
		$this->formData = $formData;
	}

	/**
	 * Getter method to original request workflow variable
	 * 
	 * @return  	Boolean
	 * 
	 * @since 		version 4.3
	 */
	public function getRequestWorkflowOrig() 
	{
		return $this->requestWorkflowOrig;
	}

	/**
	 * Getter method to original request workflow variable
	 *
	 * @param		Boolean			$requestWorkflowOrig		If the request from workflow plugin to render original fields
	 * 
	 * @return  	Null
	 * 
	 * @since 		version 4.3
	 */
	public function setRequestWorkflowOrig($requestWorkflowOrig)
	{
		$this->requestWorkflowOrig = $requestWorkflowOrig;
	}

	/**
	 * Getter method to elements variable
	 *
	 * @return  	Array
	 * 
	 * @since 		version 4.0
	 */
	public function getElements() 
	{
		return $this->elements;
	}

	/**
	 * Getter method to elements list variable
	 *
	 * @return  	Array
	 * 
	 * @since 		version 4.0
	 */
	public function getElementsList() 
	{
		return $this->elementsList;
	}

	/**
	 * Setter method to list model variable
	 *
	 * @param		Object			$listModel			Model of this list
	 * 
	 * @return  	Null
	 * 
	 * @since 		version 4.0
	 */
	public function setListModel($listModel) 
	{
		$this->listModel = $listModel;
	}

	/**
	 * Getter method to list model variable
	 *
	 * @return  	Object
	 * 
	 * @since 		version 4.0
	 */
	public function getListModel()
	{
		return $this->listModel;
	}

	/**
	 * Setter method to list id variable
	 *
	 * @param		String			$listId			Id of the list
	 * 
	 * @return  	Null
	 * 
	 * @since 		version 4.0.2
	 */
	public function setListId($listId) 
	{
		$this->listId = (Int) $listId;
	}

	/**
	 * Getter method to list id variable
	 *
	 * @return  	Int
	 * 
	 * @since 		version 4.0.2
	 */
	public function getListId() 
	{
		return $this->listId;
	}

	/**
	 * Setter method to images variable
	 *
	 * @return  	Null
	 * 
	 * @since 		version 4.0
	 */
	public function setImages() 
	{
		$this->images['edit'] = FabrikHelperHTML::image('edit.png', 'list');
		$this->images['trash'] = FabrikHelperHTML::image('trash.png', 'list');
		$this->images['plus'] = FabrikHelperHTML::image('plus.png', 'list');
		$this->images['refresh'] = FabrikHelperHTML::image('refresh.png', 'list');
		$this->images['pencil'] = FabrikHelperHTML::image('pencil.png', 'list');
	}

	/**
	 * Getter method to images variable
	 *
	 * @return  	Object
	 * 
	 * @since 		version 4.0
	 */
	public function getImages() 
	{
		return $this->images;
	}

	/**
	 * Setter method to subject variable
	 *
	 * @param		Object			$subject			The object to observe
	 * 
	 * @return  	Null
	 * 
	 * @since 		version 4.0
	 */
	public function setSubject($subject) 
	{
		$this->subject = $subject;
	}

	/**
	 * Getter method to subject variable
	 *
	 * @return  	Object
	 * 
	 * @since 		version 4.0
	 */
	public function getSubject() 
	{
		return $this->subject;
	}

	/**
	 * Setter method to modal params variable
	 * 
	 * @return  	Null
	 * 
	 * @since 		version 4.2
	 */
	public function setModalParams() 
	{
		$db = Factory::getContainer()->get('DatabaseDriver');

		$query = $db->getQuery(true);
		$query->select($db->qn('params'))
			->from($db->qn($db->getPrefix() . $this->dbTableNameModal))
			->where($db->qn('id') . ' = 1');
		$db->setQuery($query);
		$modalParams = $db->loadResult();

		$this->modalParams = $modalParams;
	}

	/**
	 * Getter method to modalParams variable
	 *
	 * @return  	String
	 * 
	 * @since 		version 4.2
	 */
	public function getModalParams()
	{
		return $this->modalParams;
	}

	/**
	 * Setter method to define the URL element for the list
	 *
	 * @param		Array		$elements			Reference to all elements
	 * @param		String		$nameElement		Identity of the element to generate the URL input
	 *
	 * @return		Null
	 *
	 * @since		version 4.3.4
	 */
	private function setElementUrlList(&$elements, $nameElement) 
	{
		$listModel = $this->getListModel();
		$subject = $this->getSubject();

		$val = ltrim(Uri::getInstance()->getPath(), '/');

		$id = $this->prefixEl . '___' . $nameElement;
		$dEl = new stdClass;

		$dEl->attributes = Array(
			'type' => 'text',
			'id' => $id,
			'name' => $id,
			'size' => 0,
			'maxlength' => '255',
			'class' => 'form-control fabrikinput inputbox text input-list',
			'value' => $val
		);

		$classField = new PlgFabrik_ElementField($subject);
		$elements[$id]['objField'] = $classField->getLayout('form');
		$elements[$id]['objLabel'] = FabrikHelperHTML::getLayout('fabrik-element-label', [COM_FABRIK_BASE . 'components/com_fabrik/layouts/element']);

		$elements[$id]['dataLabel'] = $this->getDataLabel(
			$id, 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LIST_URL_LABEL'), 
			Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ELEMENT_LIST_URL_DESC'), 
		);
		$elements[$id]['dataField'] = $dEl;
	}

	/**
	 * This method updates the URL alias of the menu item related to a list
	 * It finds the menu item linked to the list and sets a new alias based on the given URL
	 * 
	 * @param		String 		$urlNew				The new URL alias to apply
	 * @param		Int			$listId				The ID of the list associated with the menu item
	 * 
	 * @return		Boolean								True if the update was successful, false otherwise
	 * 
	 * @since		v4.3.4
	 */
	private function updateUrlMenu($urlNew, $listId)
	{
		$app = Factory::getApplication();
		$menu = $app->getMenu();

		$url = "index.php?option=com_fabrik&view=list&listid={$listId}";
		$currentMenu = $menu->getItems('link', $url, true);

		if (!$currentMenu) {
			throw new RuntimeException(Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ERROR_MENU_ITEM_NOT_FOUND'));
		}

		$existingItems = $menu->getItems('alias', $urlNew);

		foreach ($existingItems as $item) {
			if ($item->id != $currentMenu->id) {
				throw new RuntimeException(Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ERROR_URL_ALREADY_USED'));
			}
		}

		$dataMenu = new stdClass();
		$dataMenu->id = $currentMenu->id;
		$dataMenu->alias = $urlNew;
		$dataMenu->menutype = $currentMenu->menutype;

		$menuModel = new ItemModel();
		if (!$menuModel->save((array) $dataMenu)) {
			throw new RuntimeException(Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ERROR_UPDATING_MENU_URL'));
		}

		return true;
	}

	/**
	 * This method check if the list id from the request is restrict
	 * 
	 * @return		Null
	 * 
	 * @since		v4.3.4
	 */
	public function onCheckRestrictList()
	{
		$app = Factory::getApplication();

		$input = $app->input;
		$tableName = $input->getString('tableName', 0);

		$response = new stdClass();
		$response->success = true;
		$response->message = Text::_('PLG_FABRIK_LIST_EASY_ADMIN_SUCESS_RESTRICT_LIST');

		try {
			$response->restrict = $this->checkRestrictList($tableName);
		} catch (\Throwable $th) {
			$response->success = false;
			$response->message = $th->getMessage();
		}

		echo json_encode($response);
	}

	/**
	 * This method checks if the list is restrict
	 * 
	 * @param		String			$tableName				Name of the list to check
	 * 
	 * @return		Boolean
	 * 
	 * @since		v4.3.4
	 */
	private function checkRestrictList($tableName)
	{
		$db = Factory::getContainer()->get('DatabaseDriver');

		$query = $db->getQuery(true);
		$query->select('id')->from('#__fabrik_lists')->where('db_table_name = ' . $db->q($tableName));
		$db->setQuery($query);
		$listId = (int) $db->loadResult();

		if ($listId <= 0) {
			throw new Exception(Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ERROR_LIST_ID_NOT_FOUND'));
		}

		$listModel = Factory::getApplication()->bootComponent('com_fabrik')->getMVCFactory()->createModel('List', 'FabrikFEModel');
		$listModel->setId($listId);

		if($listId != $listModel->getId()) {
			throw new Exception(Text::_('PLG_FABRIK_LIST_EASY_ADMIN_ERROR_LIST_ID_NOT_FOUND'));
		}

		$restrict = !(bool) $listModel->getFormModel()->getParams()->get('approve_for_own_records');
		return $restrict;
	}
}