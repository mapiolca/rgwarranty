<?php
/* Copyright (C) 2026		Pierre Ardoin			<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *\file		core/modules/modRGWarranty.class.php
 *\ingroup	rgwarranty
 *\brief		Module descriptor for RG Warranty.
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Module class
 */
if (!class_exists('modRGWarranty')) {
	class modRGWarranty extends DolibarrModules
	{
	/**
	 * Constructor
	 *
	 * @param	DoliDB	$db	Database handler
	 */
	public function __construct($db)
	{
		global $conf, $langs;

		$this->db = $db;
		$this->numero = 450012;
		$this->rights_class = 'rgwarranty';
		$this->family = 'Les Métiers du Bâtiment';
		$this->module_position = '90';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = 'RGWModuleDescription';
		$this->descriptionlong = 'RGWModuleDescription';
		$this->editor_name = 'Les Métiers du Bâtiment';
		$this->editor_url = 'https://lesmetiersdubatiment.fr';
		$this->version = '1.0.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'invoicing';

		$this->module_parts = array(
			'models' => 1,
			'css' => array(),
			'js' => array(),
			'hooks' => array(),
		);

		$this->dirs = array('/rgwarranty/temp', '/rgwarranty/rgw_cycle');
		$this->config_page_url = array('setup.php@rgwarranty');
		$this->hidden = getDolGlobalInt('MODULE_RGWARRANTY_DISABLED');
		$this->depends = array();
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array('rgwarranty@rgwarranty');
		$this->phpmin = array(7, 2);
		$this->need_dolibarr_version = array(21, 0);
		$this->need_javascript_ajax = 0;

		if (!isModEnabled('rgwarranty')) {
			$conf->rgwarranty = new stdClass();
			$conf->rgwarranty->enabled = 0;
		}

		$this->const = array(
			1 => array('RGWARRANTY_DELAY_DAYS', 'chaine', '365', 'RGWDelayDays', 1, 'current', 1),
			2 => array('RGWARRANTY_REMIND_BEFORE_DAYS', 'chaine', '30', 'RGWRemindBeforeDays', 1, 'current', 1),
			3 => array('RGWARRANTY_AUTOSEND', 'chaine', '0', 'RGWAutoSend', 0, 'current', 1),
			4 => array('RGWARRANTY_PDF_MODEL', 'chaine', 'rgrequest', 'RGWPdfModel', 1, 'current', 1),
			5 => array('RGWARRANTY_EMAILTPL_REQUEST', 'chaine', 'rgwarranty_request', 'RGWEmailTemplateRequest', 1, 'current', 1),
			6 => array('RGWARRANTY_EMAILTPL_REMINDER', 'chaine', 'rgwarranty_reminder', 'RGWEmailTemplateReminder', 1, 'current', 1),
		);

		$this->cronjobs = array(
			0 => array(
				'label' => 'RGWCronUpdateStatus',
				'jobtype' => 'method',
				'class' => '/rgwarranty/class/rg_cycle.class.php',
				'objectname' => 'RGCycle',
				'method' => 'runCronStatusUpdate',
				'parameters' => '',
				'comment' => 'RGWCronUpdateStatusComment',
				'frequency' => 1,
				'unitfrequency' => 86400,
				'status' => 0,
				'test' => 'isModEnabled("rgwarranty")',
				'priority' => 50,
			),
		);

		$this->rights = array();
		$r = 0;
		$this->rights[$r][0] = $this->numero.sprintf('%02d', 1);
		$this->rights[$r][1] = 'Read RG cycles';
		$this->rights[$r][4] = 'cycle';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero.sprintf('%02d', 2);
		$this->rights[$r][1] = 'Write RG cycles';
		$this->rights[$r][4] = 'cycle';
		$this->rights[$r][5] = 'write';
		$r++;
		$this->rights[$r][0] = $this->numero.sprintf('%02d', 3);
		$this->rights[$r][1] = 'Pay RG cycles';
		$this->rights[$r][4] = 'cycle';
		$this->rights[$r][5] = 'pay';
		$r++;

		$this->menu = array();
		$r = 0;
		/*
		$this->menu[$r++] = array(
			'fk_menu' => '',
			'type' => 'top',
			'titre' => 'RGWMenuBTP',
			'prefix' => img_picto('', $this->picto, 'class="pictofixedwidth valignmiddle"'),
			'mainmenu' => 'btp',
			'leftmenu' => '',
			'url' => '',
			'langs' => 'rgwarranty@rgwarranty',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("rgwarranty")',
			'perms' => '1',
			'target' => '',
			'user' => 2,
		);
		*/
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=billing',
			'type' => 'left',
			'titre' => 'RGWMenuRetention',
			'prefix' => img_picto('', $this->picto, 'class="paddingright pictofixedwidth valignmiddle"'),
			'mainmenu' => 'billing',
			'leftmenu' => 'customers_bills',
			'url' => '/rgwarranty/rg/index.php',
			'langs' => 'rgwarranty@rgwarranty',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("rgwarranty")',
			'perms' => '$user->hasRight("rgwarranty", "cycle", "read")',
			'target' => '',
			'user' => 2,
			'object' => 'RGCycle',
		);
	}

	/**
	 * Init module
	 *
	 * @param	string	$options	Options
	 * @return	int						1 if ok
	 */
	public function init($options = '')
	{
		global $conf, $langs;

		$result = $this->_load_tables('/rgwarranty/sql/');
		if ($result < 0) {
			return -1;
		}

		$this->remove($options);

		$sql = array();

		$moduledir = dol_sanitizeFileName('rgwarranty');
		$object = 'rgw_cycle';
		$src = DOL_DOCUMENT_ROOT.'/install/doctemplates/'.$moduledir.'/template_rgrequest.odt';
		$dirodt = DOL_DATA_ROOT.($conf->entity > 1 ? '/'.$conf->entity : '').'/doctemplates/'.$moduledir;
		$dest = $dirodt.'/template_rgrequest.odt';

		if (file_exists($src) && !file_exists($dest)) {
			require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
			dol_mkdir($dirodt);
			$result = dol_copy($src, $dest, '0', 0);
			if ($result < 0) {
				$langs->load('errors');
				$this->error = $langs->trans('ErrorFailToCopyFile', $src, $dest);
				return 0;
			}
		}

		$sql[] = "DELETE FROM ".$this->db->prefix()."document_model WHERE nom = 'rgrequest' AND type = '".$this->db->escape($object)."' AND entity = ".((int) $conf->entity);
		$sql[] = "INSERT INTO ".$this->db->prefix()."document_model (nom, type, entity) VALUES('rgrequest', '".$this->db->escape($object)."', ".((int) $conf->entity).")";

		return $this->_init($sql, $options);
	}

	/**
	 * Remove module
	 *
	 * @param	string	$options	Options
	 * @return	int						1 if ok
	 */
	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
	}
}
