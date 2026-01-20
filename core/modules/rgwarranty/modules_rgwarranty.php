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
 *\file		core/modules/rgwarranty/modules_rgwarranty.php
 *\ingroup	rgwarranty
 *\brief		RG Warranty documents module descriptor.
 */

// EN: Load core PDF base module
// FR: Charger le module PDF de base du core
dol_include_once('/core/modules/modules_pdf.php');

/**
 * PDF model handler for RG Warranty.
 */
class ModelePDFRgwarranty extends ModelePDF
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string Model name
	 */
	public $name;

	/**
	 * @var string Model description
	 */
	public $description;

	/**
	 * @var string Document type
	 */
	public $type;

	/**
	 * @var string Subdir for model lookup
	 */
	public $scandir;

	/**
	 * Constructor
	 *
	 * @param	DoliDB	$db	Database handler
	 */
	public function __construct($db)
	{
		global $langs;

		// EN: Initialize model metadata
		// FR: Initialiser les métadonnées du modèle
		$this->db = $db;
		$langs->loadLangs(array('main', 'rgwarranty@rgwarranty'));
		$this->name = 'rgwarranty';
		$this->description = $langs->trans('RGWDocuments');
		$this->type = 'pdf';
		$this->scandir = 'rgwarranty';
	}
}
