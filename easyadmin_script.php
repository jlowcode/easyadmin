<?php
/**
 * Fabrik List Plugin
 *
 * @package     Joomla.Plugin
 * @subpackage  Fabrik.list.js
 * @copyright   Copyright (C) 2005-2020  Media A-Team, Inc. - All rights reserved.
 * @license     GNU/GPL http://www.gnu.org/copyleft/gpl.html
 * 
 * @since 		Version 4.2
 */

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Language\Transliterate;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;

// Requires
// Change to namespaces on F5
require_once JPATH_ADMINISTRATOR . '/components/com_fabrik/models/element.php';
require_once JPATH_ADMINISTRATOR . '/components/com_fabrik/models/list.php';
require_once JPATH_ADMINISTRATOR . '/components/com_fabrik/models/form.php';
require_once JPATH_ADMINISTRATOR . '/components/com_fabrik/models/group.php';

class PlgFabrik_ListEasyAdminInstallerScript
{

	private $dbTableNameModal = 'fabrik_easyadmin_modal';

    /**
	 * Run before installation or upgrade run
	 *
	 * @param       String      $type       Discover_install (Install unregistered extensions that have been discovered.)
	 *                                      or install (standard install)
	 *                                      or update (update)
	 * @param       Object      $parent     Installer object
	 *
	 * @return      Void
	 */
    public function preflight($type, $parent)
    {
        return true;
    }

    /**
	 * Run after installation or upgrade run
	 *
	 * @param       String      $type       Discover_install (Install unregistered extensions that have been discovered.)
	 *                                      or install (standard install)
	 *                                      or update (update)
	 * @param       Object      $parent     Installer object
	 *
	 * @return      Void
	 */
    public function postflight($type, $parent)
    {
		$db = Factory::getContainer()->get('DatabaseDriver');
		$app = Factory::getApplication();

		//Enabled this plugin before request
		$query = $db->getQuery(true);
		$query->update($db->qn('#__extensions'))
			->set($db->qn('enabled') . ' = ' . $db->q('1'))
			->where($db->qn('element') . ' = ' . $db->q('easyadmin'))
			->where($db->qn('type') . ' = ' . $db->q('plugin'))
			->where($db->qn('folder') . ' = ' . $db->q('fabrik_list'));
		$db->setQuery($query);
		$db->execute();

		$url = COM_FABRIK_LIVESITE . 'index.php?option=com_fabrik&task=plugin.pluginAjax&g=list&plugin=easyadmin&format=raw&method=requestInstall';
		$response = $this->request($url);
    }

    /**
	 * Run when the component is installed
	 *
	 * @param       Object      $parent     Installer object
	 *
	 * @return      Bool
	 */
    public function install($parent)
    {
		//$app = Factory::getApplication();
        //$app->enqueueMessage('Install action executed.');
    }

    /**
	 * Run when the component is updated
	 *
	 * @param       Object      $parent     Installer object
	 *
	 * @return      Bool
	 */
    public function update($parent)
    {
		//$app = Factory::getApplication();
        //$app->enqueueMessage('Update action executed.');
    }

    /**
	 * Run when the component is uninstalled.
	 *
	 * @param       Object      $parent     Installer object
	 *
	 * @return      Void
	 */
    public function uninstall($parent)
    {
		$db = Factory::getContainer()->get('DatabaseDriver');
		$app = Factory::getApplication();

		$modelAdmin = $app->bootComponent('com_fabrik')->getMVCFactory()->createModel('List', 'FabrikAdminModel');
		$input = $app->input;

		$dbTableName = $db->getPrefix() . $this->dbTableNameModal;
		$exist = self::verifyTableExist($dbTableName);

		if(!$exist) {
			return;
		}

		try {
			$query = $db->getQuery(true);
			$query->select('params')
				->from($db->qn($dbTableName))
				->where($db->qn('id') . ' = ' . $db->q('1'));
			$db->setQuery($query);
			$params = json_decode($db->loadResult());

			$groupId = $params->groupId;
			$listId = $params->list;

			//Delete formGroups
			$query = $db->getQuery(true);
			$query->delete($db->qn('#__fabrik_formgroup'))->where($db->qn('group_id') . ' = ' . $db->q($groupId));
			$db->setQuery($query);
			$db->execute();

			//Delete groups
			$query = $db->getQuery(true);
			$query->delete($db->qn('#__fabrik_groups'))->where($db->qn('id') . ' = ' . $db->q($groupId));
			$db->setQuery($query);
			$db->execute();

			//Delete elements
			$query = $db->getQuery(true);
			$query->delete($db->qn('#__fabrik_elements'))->where($db->qn('group_id') . ' = ' . $db->q($groupId));
			$db->setQuery($query);
			$db->execute();

			//Drop table
			$db->setQuery('DROP TABLE ' . $db->quoteName('#__' . $this->dbTableNameModal) . ';')->execute();

			$input->set('jform', ['recordsDeleteDepth'=>"1", 'dropTablesFromDB'=>"1"]);
			$modelAdmin->delete($listId);
        }
        catch (RuntimeException $e) {
        	return false;
        }

		return true;
    }

	/**
	 * Function that request to plugin core
	 *
	 * @param		String			$url			The uri used on request
	 * 
	 * @return      Json
	 */
	private function request($url)
	{
		$app = Factory::getApplication();
		$request = new stdClass();

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);

		if (curl_errno($ch)) {
			$request->msg = curl_error($ch);
			$request->success = false;
		} else {
			$request->msg = $response;
			$request->success = true;
		}

		curl_close($ch);

		return $request;
	}

	/**
	 * Method that verify if we need create the list, form and elements or not
	 * 
	 * @param		String 		$dbTableName		The name of the table that will be create
	 * 
	 * @return  	Boolean
	 * 
	 * @since 		version 4.2
	 */
	public static function verifyTableExist($dbTableName) 
	{
		$db = Factory::getContainer()->get('DatabaseDriver');

		$query = "
			SELECT COUNT(*)
			FROM information_schema.tables
			WHERE table_schema = (SELECT DATABASE()) AND table_name = '$dbTableName';
		";

		$db->setQuery($query);
        $exist = (bool) $db->loadResult();

		return $exist;
	}
}
?>