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
dol_include_once('/custom/rgwarranty/core/modules/rgwarranty/modules_rgwarranty.php');
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once dol_buildpath('/rgwarranty/lib/rgwarranty.lib.php', 0);

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

		// EN: Load translations for PDF
		// FR: Charger les traductions pour le PDF
		$outputlangs->loadLangs(array('main', 'companies', 'projects', 'bills', 'rgwarranty@rgwarranty'));

		$entity = (!empty($object->entity) ? (int) $object->entity : (int) $conf->entity);

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
		$pdf->MultiCell(90, 4, pdf_build_address($outputlangs, $mysoc), 0, 'L');
		$pdf->Ln(6);

		$thirdparty = new Societe($this->db);
		if (!empty($object->fk_soc)) {
			$thirdparty->fetch($object->fk_soc);
		}

		$pdf->SetFont(pdf_getPDFFont($outputlangs), '', 10);
		$pdf->MultiCell(90, 4, pdf_build_address($outputlangs, $thirdparty), 0, 'L');
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
		dolChmod($file);

		return 1;
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 *  Show top header of page.
	 *
	 *  @param	TCPDF		$pdf     		Object PDF
	 *  @param  Propal		$object     	Object to show
	 *  @param  int	    	$showaddress    0=no, 1=yes
	 *  @param  Translate	$outputlangs	Object lang for output
	 *  @param  Translate	$outputlangsbis	Object lang for output bis
	 *  @return	float|int                   Return topshift value
	 */
	protected function _pagehead(&$pdf, $object, $showaddress, $outputlangs, $outputlangsbis = null)
	{
		global $conf, $langs;

		$ltrdirection = 'L';
		if ($outputlangs->trans("DIRECTION") == 'rtl') {
			$ltrdirection = 'R';
		}

		// Load traductions files required by page
		$outputlangs->loadLangs(array("main", "propal", "companies", "bills"));

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		pdf_pagehead($pdf, $outputlangs, $this->page_hauteur);

		$pdf->SetTextColor(0, 0, 60);
		$pdf->SetFont('', 'B', $default_font_size + 3);

		$w = 100;

		$posy = $this->marge_haute;
		$posx = $this->page_largeur - $this->marge_droite - $w;

		$pdf->SetXY($this->marge_gauche, $posy);

		// Logo
		if (!getDolGlobalInt('PDF_DISABLE_MYCOMPANY_LOGO')) {
			if ($this->emetteur->logo) {
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
					$pdf->Image($logo, $this->marge_gauche, $posy, 0, $height); // width=0 (auto)
				} else {
					$pdf->SetTextColor(200, 0, 0);
					$pdf->SetFont('', 'B', $default_font_size - 2);
					$pdf->MultiCell($w, 3, $outputlangs->transnoentities("ErrorLogoFileNotFound", $logo), 0, 'L');
					$pdf->MultiCell($w, 3, $outputlangs->transnoentities("ErrorGoToGlobalSetup"), 0, 'L');
				}
			} else {
				$text = $this->emetteur->name;
				$pdf->MultiCell($w, 4, $outputlangs->convToOutputCharset($text), 0, $ltrdirection);
			}
		}

		$pdf->SetFont('', 'B', $default_font_size + 3);
		$pdf->SetXY($posx, $posy);
		$pdf->SetTextColor(0, 0, 60);
		$title = $outputlangs->transnoentities("PdfCommercialProposalTitle");
		$title .= ' '.$outputlangs->convToOutputCharset($object->ref);
		if ($object->status == $object::STATUS_DRAFT) {
			$pdf->SetTextColor(128, 0, 0);
			$title .= ' - '.$outputlangs->transnoentities("NotValidated");
		}

		$pdf->MultiCell($w, 4, $title, '', 'R');

		$pdf->SetFont('', 'B', $default_font_size);

		/*
		$posy += 5;
		$pdf->SetXY($posx, $posy);
		$pdf->SetTextColor(0, 0, 60);
		$textref = $outputlangs->transnoentities("Ref")." : ".$outputlangs->convToOutputCharset($object->ref);
		if ($object->status == $object::STATUS_DRAFT) {
			$pdf->SetTextColor(128, 0, 0);
			$textref .= ' - '.$outputlangs->transnoentities("NotValidated");
		}
		$pdf->MultiCell($w, 4, $textref, '', 'R');
		*/

		$posy += 3;
		$pdf->SetFont('', '', $default_font_size - 2);

		$ref_customer = $object->ref_customer ?: $object->ref_client;
		if ($ref_customer) {
			$posy += 4;
			$pdf->SetXY($posx, $posy);
			$pdf->SetTextColor(0, 0, 60);
			$pdf->MultiCell($w, 3, $outputlangs->transnoentities("RefCustomer")." : ".dol_trunc($outputlangs->convToOutputCharset($ref_customer), 65), '', 'R');
		}

		if (getDolGlobalString('PDF_SHOW_PROJECT_TITLE')) {
			$object->fetch_projet();
			if (!empty($object->project->ref)) {
				$posy += 3;
				$pdf->SetXY($posx, $posy);
				$pdf->SetTextColor(0, 0, 60);
				$pdf->MultiCell($w, 3, $outputlangs->transnoentities("Project")." : ".(empty($object->project->title) ? '' : $object->project->title), '', 'R');
			}
		}

		if (getDolGlobalString('PDF_SHOW_PROJECT')) {
			$object->fetch_projet();
			if (!empty($object->project->ref)) {
				$outputlangs->load("projects");
				$posy += 3;
				$pdf->SetXY($posx, $posy);
				$pdf->SetTextColor(0, 0, 60);
				$pdf->MultiCell($w, 3, $outputlangs->transnoentities("RefProject")." : ".(empty($object->project->ref) ? '' : $object->project->ref), '', 'R');
			}
		}

		if (getDolGlobalString('MAIN_PDF_DATE_TEXT')) {
			$displaydate = "daytext";
		} else {
			$displaydate = "day";
		}

		//$posy += 4;
		$posy = $pdf->getY();
		$pdf->SetXY($posx, $posy);
		$pdf->SetTextColor(0, 0, 60);
		$pdf->MultiCell($w, 3, $outputlangs->transnoentities("Date")." : ".dol_print_date($object->date, $displaydate, false, $outputlangs, true), '', 'R');

		$posy += 4;
		$pdf->SetXY($posx, $posy);
		$pdf->SetTextColor(0, 0, 60);

		$title = $outputlangs->transnoentities("DateEndPropal");
		if (getDolGlobalString('PDF_USE_ALSO_LANGUAGE_CODE') && is_object($outputlangsbis)) {
			$title .= ' - '.$outputlangsbis->transnoentities("DateEndPropal");
		}
		$pdf->MultiCell($w, 3, $title." : ".dol_print_date($object->fin_validite, $displaydate, false, $outputlangs, true), '', 'R');

		if (!getDolGlobalString('MAIN_PDF_HIDE_CUSTOMER_CODE') && $object->thirdparty->code_client) {
			$posy += 4;
			$pdf->SetXY($posx, $posy);
			$pdf->SetTextColor(0, 0, 60);
			$pdf->MultiCell($w, 3, $outputlangs->transnoentities("CustomerCode")." : ".$outputlangs->transnoentities($object->thirdparty->code_client), '', 'R');
		}
    /**
		// Get contact
		if (getDolGlobalString('DOC_SHOW_FIRST_SALES_REP')) {
			$arrayidcontact = $object->getIdContact('internal', 'SALESREPFOLL');
			if (count($arrayidcontact) > 0) {
				$usertmp = new User($this->db);
				$usertmp->fetch($arrayidcontact[0]);
				$posy += 4;
				$pdf->SetXY($posx, $posy);
				$pdf->SetTextColor(0, 0, 60);
				$pdf->MultiCell($w, 3, $outputlangs->transnoentities("SalesRepresentative")." : ".$usertmp->getFullName($langs), '', 'R');
			}
		}
    **/
		$posy += 2;

		$top_shift = 0;
		// Show list of linked objects
		$current_y = $pdf->getY();
		$posy = pdf_writeLinkedObjects($pdf, $object, $outputlangs, $posx, $posy, $w, 3, 'R', $default_font_size);
		if ($current_y < $pdf->getY()) {
			$top_shift = $pdf->getY() - $current_y;
		}

		if ($showaddress) {
			// Sender properties
			$carac_emetteur = '';
			// Add internal contact of object if defined
			$arrayidcontact = $object->getIdContact('internal', 'SALESREPFOLL');
			if (count($arrayidcontact) > 0) {
				$object->fetch_user($arrayidcontact[0]);
				//$labelbeforecontactname = ($outputlangs->transnoentities("FromContactName") != 'FromContactName' ? $outputlangs->transnoentities("FromContactName") : $outputlangs->transnoentities("Name"));
				$carac_emetteur .= ($carac_emetteur ? "\n" : '').$labelbeforecontactname."".$outputlangs->convToOutputCharset($object->user->getFullName($outputlangs));
				//$carac_emetteur .= (getDolGlobalInt('PDF_SHOW_PHONE_AFTER_USER_CONTACT') || getDolGlobalInt('PDF_SHOW_EMAIL_AFTER_USER_CONTACT')) ? ' (' : '';
				$carac_emetteur .= (getDolGlobalInt('PDF_SHOW_PHONE_AFTER_USER_CONTACT') && !empty($object->user->user_mobile)) ? "\n".$outputlangs->transnoentities('MobileShort').": ".$object->user->user_mobile : '';
				$carac_emetteur .= (getDolGlobalInt('PDF_SHOW_PHONE_AFTER_USER_CONTACT') && getDolGlobalInt('PDF_SHOW_EMAIL_AFTER_USER_CONTACT')) ? ' | ' : '';
				$carac_emetteur .= (getDolGlobalInt('PDF_SHOW_EMAIL_AFTER_USER_CONTACT') && !empty($object->user->email)) ? $outputlangs->transnoentities("Mail")." : ".$object->user->email : '';
				//$carac_emetteur .= (getDolGlobalInt('PDF_SHOW_PHONE_AFTER_USER_CONTACT') || getDolGlobalInt('PDF_SHOW_EMAIL_AFTER_USER_CONTACT')) ? ')' : '';
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

			// Show sender frame
			if (!getDolGlobalString('MAIN_PDF_NO_SENDER_FRAME')) {
				$pdf->SetTextColor(0, 0, 0);
				$pdf->SetFont('', '', $default_font_size - 2);
				$pdf->SetXY($posx, $posy - 5);
				$pdf->MultiCell($widthrecbox, 5, $outputlangs->transnoentities("BillFrom"), 0, $ltrdirection);
				$pdf->SetXY($posx, $posy);
				$pdf->SetFillColor(230, 230, 230);
				$pdf->MultiCell($widthrecbox, $hautcadre, "", 0, 'R', 1);
				$pdf->SetTextColor(0, 0, 60);
			}

			// Show sender name
			if (!getDolGlobalString('MAIN_PDF_HIDE_SENDER_NAME')) {
				$pdf->SetXY($posx + 2, $posy + 1);
				$pdf->SetFont('', 'B', $default_font_size);
				$pdf->MultiCell($widthrecbox - 2, 4, $outputlangs->convToOutputCharset($this->emetteur->name), 0, $ltrdirection);
				$posy = $pdf->getY();
			}

			// Show sender information
			$pdf->SetXY($posx + 2, $posy);
			$pdf->SetFont('', '', $default_font_size - 1);
			$pdf->MultiCell($widthrecbox - 2, 4, $carac_emetteur, 0, $ltrdirection);


			// If CUSTOMER contact defined, we use it
			$usecontact = false;
			$arrayidcontact = $object->getIdContact('external', 'CUSTOMER');
			if (count($arrayidcontact) > 0) {
				$usecontact = true;
				$result = $object->fetch_contact($arrayidcontact[0]);
			}

			// Recipient name
			if ($usecontact && ($object->contact->socid != $object->thirdparty->id && (!isset($conf->global->MAIN_USE_COMPANY_NAME_OF_CONTACT) || getDolGlobalString('MAIN_USE_COMPANY_NAME_OF_CONTACT')))) {
				$thirdparty = $object->contact;
			} else {
				$thirdparty = $object->thirdparty;
			}

			 $thirdparty = $object->thirdparty;
            
            require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';

            $contact = new Contact($this->db);
            
            if ($contact->fetch((int) $object->contact) > 0) {
            	$carac_contact = $object->contact->getFullName($langs, 1).' ('.pdfBuildThirdpartyName($object->contact, $outputlangs).')'; // ex: "DUPONT Jean" selon conf/format
            	// ou si tu veux forcer un format :
            	// $fullname = $contact->getFullName($langs, 1); // selon version: paramètres possibles (see class)
            } else {
            	$carac_contact = '';
            }
            
			$carac_client_name = pdfBuildThirdpartyName($thirdparty, $outputlangs);

			$mode = 'target';
			//$carac_client = pdf_build_address($outputlangs, $this->emetteur, $object->thirdparty, ($usecontact ? $object->contact : ''), $usecontact, $mode, $object);
			//$carac_contact = pdf_build_address($outputlangs, $this->emetteur, $object->thirdparty, ($usecontact ? $object->contact : ''), $usecontact, $mode, $object);

			$carac_client = pdf_build_address($outputlangs, $this->emetteur, $object->thirdparty, ($usecontact ? $object->contact : ''), 0, $mode, $object);

			// Show recipient
			$widthrecbox = getDolGlobalString('MAIN_PDF_USE_ISO_LOCATION') ? 92 : 100;
			if ($this->page_largeur < 210) {
				$widthrecbox = 84; // To work with US executive format
			}
			$posy = getDolGlobalString('MAIN_PDF_USE_ISO_LOCATION') ? 40 : 42;
			$posy += $top_shift;
			$posx = $this->page_largeur - $this->marge_droite - $widthrecbox;
			if (getDolGlobalString('MAIN_INVERT_SENDER_RECIPIENT')) {
				$posx = $this->marge_gauche;
			}

			// Show recipient frame
			if (!getDolGlobalString('MAIN_PDF_NO_RECIPENT_FRAME')) {
				$pdf->SetTextColor(0, 0, 0);
				$pdf->SetFont('', '', $default_font_size - 2);
				$pdf->SetXY($posx + 2, $posy - 5);
				$pdf->MultiCell($widthrecbox, 5, $outputlangs->transnoentities("BillTo"), 0, $ltrdirection);
				$pdf->Rect($posx, $posy, $widthrecbox, $hautcadre);
			}

			// Show recipient name
			$pdf->SetXY($posx + 2, $posy + 3);
			$pdf->SetFont('', 'B', $default_font_size);
			// @phan-suppress-next-line PhanPluginSuspiciousParamOrder
			$pdf->MultiCell($widthrecbox, 2, $carac_client_name, 0, $ltrdirection);

			$posy = $pdf->getY();
            
			// Show Contact Name
			if ($object->contact) {
    			$pdf->SetFont('', '', $default_font_size - 1);
    			$pdf->SetXY($posx + 2, $posy);
    			// @phan-suppress-next-line PhanPluginSuspiciousParamOrder
    			$pdf->MultiCell($widthrecbox, 4, $carac_contact, 0, $ltrdirection);
    			
    			$posy = $pdf->getY();
			}
			// Show recipient information
			$pdf->SetFont('', '', $default_font_size - 1);
			$pdf->SetXY($posx + 2, $posy);
			// @phan-suppress-next-line PhanPluginSuspiciousParamOrder
			$pdf->MultiCell($widthrecbox, 4, $carac_client, 0, $ltrdirection);
		}

		$pdf->SetTextColor(0, 0, 0);

		return $top_shift;
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 *   	Show footer of page. Need this->emetteur object
	 *
	 *   	@param	TCPDF		$pdf     			PDF
	 * 		@param	Propal		$object				Object to show
	 *      @param	Translate	$outputlangs		Object lang for output
	 *      @param	int			$hidefreetext		1=Hide free text
	 *      @return	int								Return height of bottom margin including footer text
	 */
	protected function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0)
	{
		$showdetails = getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS', 0);
		return pdf_pagefoot($pdf, $outputlangs, 'PROPOSAL_FREE_TEXT', $this->emetteur, $this->marge_basse, $this->marge_gauche, $this->page_hauteur, $object, $showdetails, $hidefreetext, $this->page_largeur, $this->watermark);
	}


	
}
