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
		$app = Factory::getApplication();
		$request = $this->requestCreateList();

		//$app->enqueueMessage($request->msg);
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
		$app = Factory::getApplication();
        $app->enqueueMessage('Install action executed.');


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
		$app = Factory::getApplication();
        $app->enqueueMessage('Update action executed.');
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
		$app = Factory::getApplication();
        $app->enqueueMessage('Uninstall action executed.');
    }

	/**
	 * Function that request to plugin core to create the list, form, group and elements needed
	 *
	 * @return      Json
	 */
	private function requestCreateList() 
	{
		$app = Factory::getApplication();
		$request = new stdClass();

		$url = COM_FABRIK_LIVESITE . 'index.php?option=com_fabrik&task=plugin.pluginAjax&g=list&plugin=easyadmin&method=requestInstall';

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
}
?>