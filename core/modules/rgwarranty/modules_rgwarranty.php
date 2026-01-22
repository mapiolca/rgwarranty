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
dol_include_once('/core/lib/pdf.lib.php');
dol_include_once('/core/lib/files.lib.php');
require_once DOL_DOCUMENT_ROOT.'/core/class/commondocgenerator.class.php';

if (!class_exists('ModelePDFRgwarranty', false)) {
	/**
	 * PDF model handler for RG Warranty.
	 */
	class ModelePDFRgwarranty extends CommonDocGenerator
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
		 * Return list of available document models.
		 *
		 * @param	DoliDB	$db		Database handler
		 * @param	int		$max	Maximum number of models
		 * @return	array|int			List of models or <0 on error
		 */
		public static function liste_modeles($db, $max = 0)
		{
			// EN: Return list of models for modulepart rgwarranty
			// FR: Retourner la liste des modèles pour le modulepart rgwarranty
			return getListOfModels($db, 'rgwarranty', $max);
		}

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
			$this->name = 'rgw_cycle';
			$this->description = $langs->trans('RGWDocuments');
			$this->type = 'pdf';
			$this->scandir = 'rgwarranty';
		}
		
	}
}