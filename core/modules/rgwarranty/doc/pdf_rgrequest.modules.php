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

// EN: Load core PDF helpers (Dolibarr v21+)
// FR: Charger les helpers PDF du core (Dolibarr v21+)
dol_include_once('/custom/rgwarranty/core/modules/rgwarranty/modules_rgwarranty.php');
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/translate.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/price.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once dol_buildpath('/rgwarranty/lib/rgwarranty.lib.php', 0);

if (!class_exists('ModelePDFRgwarranty')) {
	class ModelePDFRgwarranty
	{
		/**
		 * @var string
		 */
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
class pdf_rgrequest extends ModelePDFRgwarranty
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
	 * @var float Page width
	 */
	public $page_largeur;

	/**
	 * @var float Page height
	 */
	public $page_hauteur;

	/**
	 * @var int Left margin
	 */
	public $marge_gauche;

	/**
	 * @var int Right margin
	 */
	public $marge_droite;

	/**
	 * @var int Top margin
	 */
	public $marge_haute;

	/**
	 * @var int Bottom margin
	 */
	public $marge_basse;

	/**
	 * @var Societe Source company
	 */
	public $emetteur;

	/**
	 * @var int Radius for frames
	 */
	public $corner_radius = 0;

	/**
	 * @var string Watermark
	 */
	public $watermark = '';

	/**
	 * Constructor
	 *
	 * @param	DoliDB	$db	Database handler
	 */
	public function __construct($db)
	{
		global $langs, $mysoc;

		$langs->loadLangs(array('main', 'companies', 'projects', 'bills', 'rgwarranty@rgwarranty'));

		$this->db = $db;
		$this->name = 'rgrequest';
		$this->description = $langs->trans('RGWRequestPdfModel');

		// Page format (same base as sponge)
		$this->type = 'pdf';
		$formatarray = pdf_getFormat();
		$this->page_largeur = $formatarray['width'];
		$this->page_hauteur = $formatarray['height'];
		$this->format = array($this->page_largeur, $this->page_hauteur);

		$this->marge_gauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
		$this->marge_droite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
		$this->marge_haute = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
		$this->marge_basse = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);
		$this->corner_radius = getDolGlobalInt('MAIN_PDF_FRAME_CORNER_RADIUS', 0);

		// EN: Get source company
		// FR: Charger la société émettrice
		$this->emetteur = $mysoc;
		if (is_object($this->emetteur) && empty($this->emetteur->country_code)) {
			$this->emetteur->country_code = substr($langs->defaultlang, -2);
		}
	}

	/**
	 * Write file
	 *
	 * @param	object		$object				Object to generate
	 * @param	Translate	$outputlangs		Langs
	 * @param	string		$srctemplatepath	Template path
	 * @param	int			$hidedetails		Hide details
	 * @param	int			$hidedesc			Hide desc
	 * @param	int			$hideref			Hide ref
	 * @return	int								>0 if ok
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		global $conf, $mysoc;

		if (!is_object($outputlangs)) {
			$outputlangs = $GLOBALS['langs'];
		}

		// EN: Load translations for PDF
		// FR: Charger les traductions pour le PDF
		$outputlangs->loadLangs(array('main', 'companies', 'projects', 'bills', 'rgwarranty@rgwarranty'));

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		$entity = (!empty($object->entity) ? (int) $object->entity : (int) $conf->entity);

		// EN: Ensure thirdparty is available for header address block
		// FR: S'assurer que le tiers est disponible pour le bloc d'adresse du header
		if (empty($object->thirdparty) || !is_object($object->thirdparty)) {
			$object->thirdparty = new Societe($this->db);
			if (!empty($object->fk_soc)) {
				$object->thirdparty->fetch((int) $object->fk_soc);
			}
		}

		// EN: Output directory
		// FR: Dossier de sortie
		$baseoutputdir = '';
		if (!empty($conf->rgwarranty->multidir_output[$entity])) {
			$baseoutputdir = $conf->rgwarranty->multidir_output[$entity];
		} elseif (!empty($conf->rgwarranty->dir_output)) {
			$baseoutputdir = $conf->rgwarranty->dir_output;
		}

		$ref = dol_sanitizeFileName($object->ref);
		$dir = $baseoutputdir.'/'.$object->element.'/'.$ref;
		$file = $dir.'/'.$ref.'.pdf';

		if (!dol_mkdir($dir)) {
			$this->error = $outputlangs->trans('ErrorCantCreateDir', $dir);
			return 0;
		}

		// EN: Init PDF
		// FR: Initialiser le PDF
		$pdf = pdf_getInstance($this->format);
		if (class_exists('TCPDF')) {
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
		}

		$pdf->SetCreator('Dolibarr');
		$pdf->SetAuthor($outputlangs->transnoentities('RGWModuleTitle'));
		$pdf->SetTitle($outputlangs->transnoentities('RGWRequestLetterTitle'));
		$pdf->SetSubject($outputlangs->transnoentities('RGWRequestLetterTitle'));
		$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
		$pdf->SetAutoPageBreak(true, $this->marge_basse);
		$pdf->SetFont(pdf_getPDFFont($outputlangs), '', $default_font_size);

		// EN: Draft watermark (core standard)
		// FR: Filigrane brouillon (standard core)
		$this->watermark = '';
		if (!empty($conf->global->MAIN_PDF_DRAFT_WATERMARK)) {
			$is_draft = false;
			if (isset($object->status) && (int) $object->status === 0) {
				$is_draft = true;
			} elseif (isset($object->statut) && (int) $object->statut === 0) {
				$is_draft = true;
			}
			if ($is_draft) {
				$this->watermark = $conf->global->MAIN_PDF_DRAFT_WATERMARK;
			}
		}

		// EN: Optional second language (same behavior as sponge)
		// FR: Langue bis optionnelle (même comportement que sponge)
		$outputlangsbis = null;
		if (getDolGlobalString('PDF_USE_ALSO_LANGUAGE_CODE') && class_exists('Translate')) {
			if (!empty($object->thirdparty->default_lang)) {
				$outputlangsbis = new Translate('', $conf);
				$outputlangsbis->setDefaultLang($object->thirdparty->default_lang);
				$outputlangsbis->loadLangs(array('main', 'companies', 'projects', 'bills', 'rgwarranty@rgwarranty'));
			}
		}

		$pdf->Open();
		$pdf->AddPage();

		$pagehead = $this->_pagehead($pdf, $object, 1, $outputlangs, $outputlangsbis);
		$top_shift = (is_array($pagehead) && isset($pagehead['top_shift'])) ? (float) $pagehead['top_shift'] : 0;

		$pdf->SetTextColor(0, 0, 0);

		// EN: Start body below header blocks
		// FR: Début du corps sous les blocs d'entête
		$tab_top = 90 + $top_shift;
		$pdf->SetXY($this->marge_gauche, $tab_top);

		// EN: Letter subject line (body)
		// FR: Objet du courrier (corps)
		$pdf->SetFont(pdf_getPDFFont($outputlangs), 'B', $default_font_size + 1);
		$pdf->MultiCell(0, 6, $outputlangs->transnoentities('RGWRequestLetterTitle'), 0, 'L');
		$pdf->Ln(2);

		$pdf->SetFont(pdf_getPDFFont($outputlangs), '', $default_font_size);

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
			if ($project->fetch((int) $object->fk_projet) > 0) {
				$pdf->MultiCell(0, 5, $outputlangs->transnoentities('Project').': '.$outputlangs->convToOutputCharset($project->ref).' - '.$outputlangs->convToOutputCharset($project->title), 0, 'L');
				$pdf->Ln(2);
			}
		}

		// EN: Table of invoices
		// FR: Tableau des factures
		$pdf->SetFont(pdf_getPDFFont($outputlangs), 'B', $default_font_size - 1);
		$pdf->Cell(50, 6, $outputlangs->transnoentities('Invoice'), 1, 0, 'L');
		$pdf->Cell(40, 6, $outputlangs->transnoentities('Date'), 1, 0, 'L');
		$pdf->Cell(50, 6, $outputlangs->transnoentities('RGWAmount'), 1, 1, 'R');

		$pdf->SetFont(pdf_getPDFFont($outputlangs), '', $default_font_size - 1);

		$invoices = rgwarranty_fetch_invoices_for_cycle($this->db, $entity, $object->situation_cycle_ref);
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

				$pdf->Cell(50, 6, $outputlangs->convToOutputCharset($invoice->ref), 1, 0, 'L');
				$pdf->Cell(40, 6, $invoiceDateLabel, 1, 0, 'L');
				$pdf->Cell(50, 6, price($amount, 0, $outputlangs), 1, 1, 'R');
			}
		}

		$pdf->SetFont(pdf_getPDFFont($outputlangs), 'B', $default_font_size - 1);
		$pdf->Cell(90, 6, $outputlangs->transnoentities('Total'), 1, 0, 'R');
		$pdf->Cell(50, 6, price($total, 0, $outputlangs), 1, 1, 'R');
		$pdf->Ln(3);

		// EN: Bank info
		// FR: Coordonnées bancaires
		$pdf->SetFont(pdf_getPDFFont($outputlangs), '', $default_font_size - 1);
		if (!empty($mysoc->iban)) {
			$pdf->MultiCell(0, 5, $outputlangs->transnoentities('RGWRequestBankInfo', $mysoc->iban), 0, 'L');
		} else {
			$pdf->MultiCell(0, 5, $outputlangs->transnoentities('RGWRequestBankInfoPlaceholder'), 0, 'L');
		}

		$pdf->Ln(4);
		$pdf->MultiCell(0, 5, $outputlangs->transnoentities('RGWRequestLetterClosing'), 0, 'L');

		// Footer (same base as sponge)
		$this->_pagefoot($pdf, $object, $outputlangs);

		if (method_exists($pdf, 'AliasNbPages')) {
			$pdf->AliasNbPages();
		}
		$pdf->Close();
		$pdf->Output($file, 'F');
		dolChmod($file);

		return 1;
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 *  Show top header of page. This include the logo, ref and address blocks (same base as sponge).
	 *
	 *  @param	TCPDF			$pdf			Object PDF
	 *  @param	object			$object			Object to show
	 *  @param	int				$showaddress	0=no, 1=yes
	 *  @param	Translate		$outputlangs	Object lang for output
	 *  @param	?Translate		$outputlangsbis	Object lang for output bis
	 *  @return	array{top_shift:float,shipp_shift:float}
	 */
	protected function _pagehead(&$pdf, $object, $showaddress, $outputlangs, $outputlangsbis = null)
	{
		global $conf, $langs;

		$ltrdirection = 'L';
		if ($outputlangs->trans('DIRECTION') == 'rtl') {
			$ltrdirection = 'R';
		}

		$outputlangs->loadLangs(array('main', 'companies', 'projects', 'bills', 'rgwarranty@rgwarranty'));

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		pdf_pagehead($pdf, $outputlangs, $this->page_hauteur);

		$pdf->SetTextColor(0, 0, 60);
		$pdf->SetFont('', 'B', $default_font_size + 3);

		$w = 110;

		$posy = $this->marge_haute;
		$posx = $this->page_largeur - $this->marge_droite - $w;

		$pdf->SetXY($this->marge_gauche, $posy);

		// Logo (same behavior as sponge)
		if (!getDolGlobalInt('PDF_DISABLE_MYCOMPANY_LOGO')) {
			if (!empty($this->emetteur->logo)) {
				$logodir = $conf->mycompany->dir_output;
				if (!empty($conf->mycompany->multidir_output[$object->entity])) {
					$logodir = $conf->mycompany->multidir_output[$object->entity];
				}
				if (!getDolGlobalInt('MAIN_PDF_USE_LARGE_LOGO')) {
					$logo = $logodir.'/logos/thumbs/'.$this->emetteur->logo_small;
				} else {
					$logo = $logodir.'/logos/'.$this->emetteur->logo;
				}
				if (is_readable($logo)) {
					$height = pdf_getHeightForLogo($logo);
					$pdf->Image($logo, $this->marge_gauche, $posy, 0, $height);
				} else {
					$pdf->SetTextColor(200, 0, 0);
					$pdf->SetFont('', 'B', $default_font_size - 2);
					$pdf->MultiCell($w, 3, $outputlangs->transnoentities('ErrorLogoFileNotFound', $logo), 0, 'L');
					$pdf->MultiCell($w, 3, $outputlangs->transnoentities('ErrorGoToGlobalSetup'), 0, 'L');
				}
			} else {
				$text = $this->emetteur->name;
				$pdf->MultiCell($w, 4, $outputlangs->convToOutputCharset($text), 0, $ltrdirection);
			}
		}

		// Title (adapted for rgrequest)
		$pdf->SetFont('', 'B', $default_font_size + 3);
		$pdf->SetXY($posx, $posy);
		$pdf->SetTextColor(0, 0, 60);

		$title = $outputlangs->transnoentities('RGWRequestLetterTitle');
		if (getDolGlobalString('PDF_USE_ALSO_LANGUAGE_CODE') && is_object($outputlangsbis)) {
			$title .= ' - '.$outputlangsbis->transnoentities('RGWRequestLetterTitle');
		}
		$title .= ' '.$outputlangs->convToOutputCharset($object->ref);

		$pdf->MultiCell($w, 3, $title, '', 'R');

		$posy += 3;
		$pdf->SetFont('', '', $default_font_size - 2);

		// Project (optional, right side)
		if (!empty($object->fk_projet) && isModEnabled('project')) {
			$project = new Project($this->db);
			if ($project->fetch((int) $object->fk_projet) > 0) {
				$posy += 4;
				$pdf->SetXY($posx, $posy);
				$pdf->SetTextColor(0, 0, 60);
				$pdf->MultiCell($w, 3, $outputlangs->transnoentities('Project').' : '.$outputlangs->convToOutputCharset($project->ref), '', 'R');
			}
		}

		// Date (optional, right side)
		$docdate = null;
		if (!empty($object->datec)) {
			$docdate = $object->datec;
		} elseif (!empty($object->date)) {
			$docdate = $object->date;
		} elseif (!empty($object->date_request)) {
			$docdate = $object->date_request;
		}
		if (!empty($docdate)) {
			$posy += 4;
			$pdf->SetXY($posx, $posy);
			$pdf->SetTextColor(0, 0, 60);
			$titledate = $outputlangs->transnoentities('Date');
			if (getDolGlobalString('PDF_USE_ALSO_LANGUAGE_CODE') && is_object($outputlangsbis)) {
				$titledate .= ' - '.$outputlangsbis->transnoentities('Date');
			}
			$pdf->MultiCell($w, 3, $titledate.' : '.dol_print_date($docdate, 'day', false, $outputlangs, true), '', 'R');
		}

		if (!getDolGlobalString('MAIN_PDF_HIDE_CUSTOMER_CODE') && !empty($object->thirdparty) && is_object($object->thirdparty) && !empty($object->thirdparty->code_client)) {
			$posy += 3;
			$pdf->SetXY($posx, $posy);
			$pdf->SetTextColor(0, 0, 60);
			$pdf->MultiCell($w, 3, $outputlangs->transnoentities('CustomerCode').' : '.$outputlangs->transnoentities($object->thirdparty->code_client), '', 'R');
		}

		$posy += 1;

		$top_shift = 0;
		$shipp_shift = 0;

		// Linked objects (defensive: only if method exists)
		if (!getDolGlobalString('INVOICE_HIDE_LINKED_OBJECT') && method_exists($object, 'fetchObjectLinked')) {
			$current_y = $pdf->getY();
			$posy = pdf_writeLinkedObjects($pdf, $object, $outputlangs, $posx, $posy, $w, 3, 'R', $default_font_size);
			if ($current_y < $pdf->getY()) {
				$top_shift = $pdf->getY() - $current_y;
			}
		}

		if ($showaddress) {
			// Sender properties
			$carac_emetteur = '';

			// Add internal contact of object if defined (defensive)
			$arrayidcontact = array();
			if (method_exists($object, 'getIdContact')) {
				$arrayidcontact = $object->getIdContact('internal', 'BILLING');
			}
			if (!empty($arrayidcontact) && is_array($arrayidcontact) && count($arrayidcontact) > 0 && method_exists($object, 'fetch_user')) {
				$object->fetch_user($arrayidcontact[0]);
				$labelbeforecontactname = ($outputlangs->transnoentities('FromContactName') != 'FromContactName' ? $outputlangs->transnoentities('FromContactName') : $outputlangs->transnoentities('Name'));
				$carac_emetteur .= ($carac_emetteur ? "\n" : '').$labelbeforecontactname.' '.$outputlangs->convToOutputCharset($object->user->getFullName($outputlangs));
				$carac_emetteur .= "\n";
			}

			$carac_emetteur .= pdf_build_address($outputlangs, $this->emetteur, $object->thirdparty, '', 0, 'source', $object);

			// Show sender
			$posy = getDolGlobalString('MAIN_PDF_USE_ISO_LOCATION') ? 40 : 42;
			$posy += $top_shift;
			$posx = $this->marge_gauche;
			if (getDolGlobalString('MAIN_INVERT_SENDER_RECIPIENT')) {
				$posx = $this->page_largeur - $this->marge_droite - 80;
			}

			$hautcadre = getDolGlobalString('MAIN_PDF_USE_ISO_LOCATION') ? 38 : 40;
			$widthrecbox = getDolGlobalString('MAIN_PDF_USE_ISO_LOCATION') ? 92 : 82;

			// Sender frame
			if (!getDolGlobalString('MAIN_PDF_NO_SENDER_FRAME')) {
				$pdf->SetTextColor(0, 0, 0);
				$pdf->SetFont('', '', $default_font_size - 2);
				$pdf->SetXY($posx, $posy - 5);
				$pdf->MultiCell($widthrecbox, 5, $outputlangs->transnoentities('BillFrom'), 0, $ltrdirection);
				$pdf->SetXY($posx, $posy);
				$pdf->SetFillColor(230, 230, 230);
				$pdf->MultiCell($widthrecbox, $hautcadre, '', 0, 'R', 1);
				$pdf->SetTextColor(0, 0, 60);
			}

			// Sender name
			if (!getDolGlobalString('MAIN_PDF_HIDE_SENDER_NAME')) {
				$pdf->SetXY($posx + 2, $posy + 1);
				$pdf->SetFont('', 'B', $default_font_size);
				$pdf->MultiCell($widthrecbox - 2, 4, $outputlangs->convToOutputCharset($this->emetteur->name), 0, $ltrdirection);
				$posy = $pdf->getY();
			}

			// Sender info
			$pdf->SetXY($posx + 2, $posy);
			$pdf->SetFont('', '', $default_font_size - 1);
			$pdf->MultiCell($widthrecbox - 2, 4, $carac_emetteur, 0, $ltrdirection);

			// Recipient (use external BILLING contact if available)
			$usecontact = false;
			$arrayidcontact = array();
			if (method_exists($object, 'getIdContact')) {
				$arrayidcontact = $object->getIdContact('external', 'BILLING');
			}
			if (!empty($arrayidcontact) && is_array($arrayidcontact) && count($arrayidcontact) > 0 && method_exists($object, 'fetch_contact')) {
				$usecontact = true;
				$object->fetch_contact($arrayidcontact[0]);
			}

			// Recipient name
			if ($usecontact && !empty($object->contact) && is_object($object->contact) && !empty($object->thirdparty) && is_object($object->thirdparty) && ($object->contact->socid != $object->thirdparty->id) && (!isset($conf->global->MAIN_USE_COMPANY_NAME_OF_CONTACT) || getDolGlobalString('MAIN_USE_COMPANY_NAME_OF_CONTACT'))) {
				$thirdparty = $object->contact;
			} else {
				$thirdparty = $object->thirdparty;
			}

			$carac_client_name = pdfBuildThirdpartyName($thirdparty, $outputlangs);

			$mode = 'target';
			$carac_client = pdf_build_address($outputlangs, $this->emetteur, $object->thirdparty, ($usecontact ? $object->contact : ''), $usecontact, $mode, $object);

			$widthrecbox = getDolGlobalString('MAIN_PDF_USE_ISO_LOCATION') ? 92 : 100;
			if ($this->page_largeur < 210) {
				$widthrecbox = 84;
			}
			$posy = getDolGlobalString('MAIN_PDF_USE_ISO_LOCATION') ? 40 : 42;
			$posy += $top_shift;
			$posx = $this->page_largeur - $this->marge_droite - $widthrecbox;
			if (getDolGlobalString('MAIN_INVERT_SENDER_RECIPIENT')) {
				$posx = $this->marge_gauche;
			}

			// Recipient frame
			if (!getDolGlobalString('MAIN_PDF_NO_RECIPENT_FRAME')) {
				$pdf->SetTextColor(0, 0, 0);
				$pdf->SetFont('', '', $default_font_size - 2);
				$pdf->SetXY($posx + 2, $posy - 5);
				$pdf->MultiCell($widthrecbox, 5, $outputlangs->transnoentities('BillTo'), 0, $ltrdirection);
				$pdf->Rect($posx, $posy, $widthrecbox, $hautcadre);
			}

			// Recipient name
			$pdf->SetXY($posx + 2, $posy + 3);
			$pdf->SetFont('', 'B', $default_font_size);
			$pdf->MultiCell($widthrecbox, 2, $carac_client_name, 0, $ltrdirection);

			$posy = $pdf->getY();

			// Recipient information
			$pdf->SetFont('', '', $default_font_size - 1);
			$pdf->SetXY($posx + 2, $posy);
			$pdf->MultiCell($widthrecbox, 4, $carac_client, 0, $ltrdirection);
		}

		$pdf->SetTextColor(0, 0, 0);

		return array('top_shift' => $top_shift, 'shipp_shift' => $shipp_shift);
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 * Show footer of page. Need this->emetteur object
	 *
	 * @param	TCPDF		$pdf				PDF
	 * @param	object		$object				Object to show
	 * @param	Translate	$outputlangs		Object lang for output
	 * @param	int			$hidefreetext		1=Hide free text
	 * @return	int								Return height of bottom margin including footer text
	 */
	protected function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0)
	{
		$showdetails = getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS', 0);

		// EN: Keep same footer rendering as sponge (pdf_pagefoot), but use generic key already used previously here.
		// FR: Garder le rendu footer de sponge (pdf_pagefoot), en conservant la clé déjà utilisée ici.
		return pdf_pagefoot($pdf, $outputlangs, 'PROPOSAL_FREE_TEXT', $this->emetteur, $this->marge_basse+5, $this->marge_gauche, $this->page_hauteur, $object, $showdetails, $hidefreetext, $this->page_largeur, $this->watermark);
	}
}
