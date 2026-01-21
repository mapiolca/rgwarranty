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
 *\file		core/modules/rgwarranty/doc/pdf_rgrequest.modules.php
 *\ingroup	rgwarranty
 *\brief		PDF model for RG request letter.
 */

// EN: Load core PDF base class and helpers (Dolibarr v21+)
// FR: Charger la classe de base PDF et helpers core (Dolibarr v21+)
dol_include_once('/core/modules/modules_pdf.php');
dol_include_once('/core/modules/rgwarranty/modules_rgwarranty.php');
dol_include_once('/core/lib/pdf.lib.php');
dol_include_once('/core/lib/company.lib.php');
dol_include_once('/core/lib/date.lib.php');
dol_include_once('/core/lib/functions2.lib.php');
dol_include_once('/core/lib/files.lib.php');
dol_include_once('/societe/class/societe.class.php');
dol_include_once('/projet/class/project.class.php');
dol_include_once('/compta/facture/class/facture.class.php');
require_once dol_buildpath('/rgwarranty/lib/rgwarranty.lib.php', 0);

// EN: Track if base PDF class is available
// FR: Suivre la disponibilité de la classe PDF de base
$rgwarrantyPdfBaseLoaded = class_exists('ModelePDFRgwarranty');

// EN: Fallback to avoid fatal if base class is missing
// FR: Fallback pour éviter un fatal si la classe de base manque
if (!$rgwarrantyPdfBaseLoaded) {
	class ModelePDFRgwarranty
	{
		public $error = '';

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
	}
}

/**
 * PDF model class
 */
class pdf_rgrequest extends ModelePDFRgw_cycle //Rgwarranty
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string Model description
	 */
	public $description;

	/**
	 * @var string Name of model
	 */
	public $name;

	/**
	 * Constructor
	 *
	 * @param	DoliDB	$db	Database handler
	 */
	public function __construct($db)
	{
		global $langs;

		$this->db = $db;
		$this->name = 'rgrequest';
		$this->description = $langs->trans('RGWRequestPdfModel');
		$this->type = 'pdf';
		$this->format = array('210', '297');
		$this->marge_left = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
		$this->marge_right = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
		$this->marge_top = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
		$this->marge_bottom = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);
	}

	/**
	 * Write file
	 *
	 * @param	object		$object			Object to generate
	 * @param	Translate	$outputlangs	Langs
	 * @param	string		$srctemplatepath	Template path
	 * @param	int			$hidedetails	Hide details
	 * @param	int			$hidedesc	Hide desc
	 * @param	int			$hideref	Hide ref
	 * @return	int							>0 if ok
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		global $conf, $user, $mysoc;

		// EN: Stop if base PDF class is unavailable
		// FR: Stopper si la classe PDF de base est indisponible
		global $rgwarrantyPdfBaseLoaded;
		if (empty($rgwarrantyPdfBaseLoaded)) {
			$this->error = $outputlangs->trans('RGWMissingPdfBaseClass');
			return 0;
		}

		// EN: Load translations for PDF
		// FR: Charger les traductions pour le PDF
		$outputlangs->loadLangs(array('main', 'companies', 'projects', 'bills', 'rgwarranty@rgwarranty'));

		$ref = dol_sanitizeFileName($object->ref);
		$dir = $conf->rgwarranty->dir_output.'/'.$object->element.'/'.$ref;
		$file = $dir.'/'.$ref.'.pdf';

		if (!dol_mkdir($dir)) {
			$this->error = $outputlangs->trans('ErrorCantCreateDir', $dir);
			return 0;
		}

		$pdf = pdf_getInstance($this->format);
		$pdf->SetCreator('Dolibarr');
		$pdf->SetAuthor($outputlangs->transnoentities('RGWModuleTitle'));
		$pdf->SetTitle($outputlangs->transnoentities('RGWRequestLetterTitle'));
		$pdf->SetSubject($outputlangs->transnoentities('RGWRequestLetterTitle'));
		$pdf->SetMargins($this->marge_left, $this->marge_top, $this->marge_right);
		$pdf->SetAutoPageBreak(true, $this->marge_bottom);
		$pdf->AddPage();

		// EN: Company header
		// FR: En-tête société
		$pdf->SetFont(pdf_getPDFFont($outputlangs), '', 10);
		$pdf->MultiCell(90, 4, pdfBuildAddress($outputlangs, $mysoc), 0, 'L');
		$pdf->Ln(6);

		$thirdparty = new Societe($this->db);
		if (!empty($object->fk_soc)) {
			$thirdparty->fetch($object->fk_soc);
		}

		$pdf->SetFont(pdf_getPDFFont($outputlangs), '', 10);
		$pdf->MultiCell(90, 4, pdfBuildAddress($outputlangs, $thirdparty), 0, 'L');
		$pdf->Ln(6);

		$pdf->SetFont(pdf_getPDFFont($outputlangs), 'B', 12);
		$pdf->Cell(0, 6, $outputlangs->transnoentities('RGWRequestLetterTitle'), 0, 1, 'L');

		$pdf->SetFont(pdf_getPDFFont($outputlangs), '', 10);
		// EN: Handle missing reception date
		// FR: Gérer l'absence de date de réception
		$receptionDateLabel = $outputlangs->trans('RGWNoReceptionDate');
		if (!empty($object->date_reception)) {
			$receptionDateLabel = dol_print_date($object->date_reception, 'day', $outputlangs);
		}
		$pdf->MultiCell(0, 5, $outputlangs->transnoentities('RGWRequestLetterIntro', $receptionDateLabel), 0, 'L');
		$pdf->Ln(3);

		// EN: Project block
		// FR: Bloc projet
		if (!empty($object->fk_projet) && isModEnabled('project')) {
			$project = new Project($this->db);
			$project->fetch($object->fk_projet);
			$pdf->MultiCell(0, 5, $outputlangs->transnoentities('Project').': '.$project->ref.' - '.$project->title, 0, 'L');
			$pdf->Ln(2);
		}

		// EN: Table of invoices
		// FR: Tableau des factures
		$pdf->SetFont(pdf_getPDFFont($outputlangs), 'B', 9);
		$pdf->Cell(50, 6, $outputlangs->transnoentities('Invoice'), 1, 0, 'L');
		$pdf->Cell(40, 6, $outputlangs->transnoentities('Date'), 1, 0, 'L');
		$pdf->Cell(50, 6, $outputlangs->transnoentities('RGWAmount'), 1, 1, 'R');

		$pdf->SetFont(pdf_getPDFFont($outputlangs), '', 9);
		$invoices = rgwarranty_fetch_invoices_for_cycle($this->db, $object->entity, $object->situation_cycle_ref);
		if (!is_array($invoices)) {
			$invoices = array();
		}
		$total = 0;
		if (empty($invoices)) {
			$pdf->Cell(140, 6, $outputlangs->transnoentities('RGWNoInvoiceForCycle'), 1, 1, 'L');
		} else {
			foreach ($invoices as $invoice) {
				$amount = rgwarranty_calc_rg_amount_ttc($invoice);
				$total += $amount;
				$invoiceDateLabel = '';
				if (!empty($invoice->datef)) {
					$invoiceDateLabel = dol_print_date($invoice->datef, 'day', $outputlangs);
				}
				$pdf->Cell(50, 6, $invoice->ref, 1, 0, 'L');
				$pdf->Cell(40, 6, $invoiceDateLabel, 1, 0, 'L');
				$pdf->Cell(50, 6, price($amount, 0, $outputlangs), 1, 1, 'R');
			}
		}

		$pdf->SetFont(pdf_getPDFFont($outputlangs), 'B', 9);
		$pdf->Cell(90, 6, $outputlangs->transnoentities('Total'), 1, 0, 'R');
		$pdf->Cell(50, 6, price($total, 0, $outputlangs), 1, 1, 'R');
		$pdf->Ln(3);

		// EN: Bank info
		// FR: Coordonnées bancaires
		$pdf->SetFont(pdf_getPDFFont($outputlangs), '', 9);
		if (!empty($mysoc->iban)) {
			$pdf->MultiCell(0, 5, $outputlangs->transnoentities('RGWRequestBankInfo', $mysoc->iban), 0, 'L');
		} else {
			$pdf->MultiCell(0, 5, $outputlangs->transnoentities('RGWRequestBankInfoPlaceholder'), 0, 'L');
		}

		$pdf->Ln(4);
		$pdf->MultiCell(0, 5, $outputlangs->transnoentities('RGWRequestLetterClosing'), 0, 'L');

		$pdf->Output($file, 'F');

		return 1;
	}
}
