<?php
/* Copyright (C) 2026		Pierre Ardoin			<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *\file		class/rg_cycle_facture.class.php
 *\ingroup	rgwarranty
 *\brief		Object class for RG cycle invoices.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class for RG Cycle Invoice line
 */
class RGCycleFacture extends CommonObject
{
	/**
	 * @var string ID of module.
	 */
	public $module = 'rgwarranty';

	/**
	 * @var string ID to identify managed object.
	 */
	public $element = 'rgw_cycle_facture';

	/**
	 * @var string Name of table without prefix.
	 */
	public $table_element = 'rgw_cycle_facture';

	/**
	 * @var int<0,1> Does object support multicompany module ?
	 */
	public $ismultientitymanaged = 1;

	/**
	 * @var array<string,array> Fields
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => '1', 'position' => 1, 'notnull' => 1, 'visible' => '0', 'noteditable' => '1', 'index' => '1'),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => '1', 'position' => 2, 'notnull' => 1, 'visible' => '0', 'index' => '1'),
		'fk_cycle' => array('type' => 'integer', 'label' => 'RGWCycle', 'enabled' => '1', 'position' => 10, 'notnull' => 1, 'visible' => '0', 'index' => '1'),
		'fk_facture' => array('type' => 'integer:Facture:compta/facture/class/facture.class.php:1', 'label' => 'Invoice', 'enabled' => '1', 'position' => 20, 'notnull' => 1, 'visible' => '0', 'index' => '1'),
		'rg_amount_ttc' => array('type' => 'double(24,8)', 'label' => 'RGWAmount', 'enabled' => '1', 'position' => 30, 'notnull' => 1, 'visible' => '0'),
		'rg_paid_ttc' => array('type' => 'double(24,8)', 'label' => 'RGWPaid', 'enabled' => '1', 'position' => 40, 'notnull' => 1, 'visible' => '0'),
		'multicurrency_rg_amount_ttc' => array('type' => 'double(24,8)', 'label' => 'RGWAmount', 'enabled' => '1', 'position' => 50, 'notnull' => 1, 'visible' => '0'),
		'multicurrency_rg_paid_ttc' => array('type' => 'double(24,8)', 'label' => 'RGWPaid', 'enabled' => '1', 'position' => 60, 'notnull' => 1, 'visible' => '0'),
		'datec' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => '1', 'position' => 90, 'notnull' => -1, 'visible' => '0'),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => '1', 'position' => 91, 'notnull' => -1, 'visible' => '0'),
	);

	public $rowid;
	public $entity;
	public $fk_cycle;
	public $fk_facture;
	public $rg_amount_ttc;
	public $rg_paid_ttc;
	public $multicurrency_rg_amount_ttc;
	public $multicurrency_rg_paid_ttc;
	public $datec;
	public $tms;

	/**
	 * Constructor
	 *
	 * @param	DoliDB	$db	Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}
}
