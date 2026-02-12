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
 *\file		rgwarranty/rg/cycle_card.php
 *\ingroup	rgwarranty
 *\brief		RG cycle card.
 */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
	$res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'].'/main.inc.php';
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)).'/main.inc.php')) {
	$res = @include substr($tmp, 0, ($i + 1)).'/main.inc.php';
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))).'/main.inc.php')) {
	$res = @include dirname(substr($tmp, 0, ($i + 1))).'/main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
	$res = @include '../../../main.inc.php';
}
if (!$res) {
	die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formactions.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/invoice.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
dol_include_once('/comm/action/class/actioncomm.class.php');
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once __DIR__.'/../class/rg_cycle.class.php';
require_once __DIR__.'/../lib/rgwarranty.lib.php';

$langs->loadLangs(array('rgwarranty@rgwarranty', 'companies', 'projects', 'bills', 'other'));

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$mailcontext = GETPOST('mailcontext', 'alpha');

$permissiontoread = ($user->admin || $user->hasRight('rgwarranty', 'cycle', 'read'));
$permissiontowrite = ($user->admin || $user->hasRight('rgwarranty', 'cycle', 'write'));
$permissiontopay = ($user->admin || $user->hasRight('rgwarranty', 'cycle', 'pay'));

// EN: Map permissions for document generation/deletion
// FR: Mapper les permissions pour la génération/suppression de documents
$usercanread = $permissiontoread;
$usercancreate = $permissiontowrite;

if (!$permissiontoread) {
	accessforbidden();
}

// EN: Prevent actions without rights
// FR: Bloquer les actions sans droits
$actionswithwrite = array('reception', 'reception_save', 'request', 'reminder', 'presend');
if (in_array($action, $actionswithwrite, true) && !$permissiontowrite) {
	accessforbidden();
}

$object = new RGCycle($db);
if ($id > 0) {
	$object->fetch($id);
}

if (empty($object->id)) {
	accessforbidden();
}

$form = new Form($db);
$formcompany = new FormCompany($db);
$formfile = new FormFile($db);
$formactions = new FormActions($db);

// EN: Initialize hooks for cycle card
// FR: Initialiser les hooks pour la fiche cycle
$hookmanager = new HookManager($db);
$hookmanager->initHooks(array('rgwarrantycyclecard', 'globalcard'));

// EN: Load document driver for module
// FR: Charger le driver documents du module
//dol_include_once(dol_buildpath('/rgwarranty/core/modules/rgwarranty/modules_rgwarranty.php', 0));

$error = 0;

// EN: Allow hooks to process actions
// FR: Autoriser les hooks à traiter les actions
$parameters = array('id' => $object->id);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

// EN: Handle actions
// FR: Gérer les actions
if ($reshook == 0 && $action == 'reception_save' && $permissiontowrite) {
	$date_reception = dol_mktime(0, 0, 0, GETPOSTINT('receptionmonth'), GETPOSTINT('receptionday'), GETPOSTINT('receptionyear'));
	$date_limit = dol_mktime(0, 0, 0, GETPOSTINT('limitmonth'), GETPOSTINT('limitday'), GETPOSTINT('limityear'));
	if (empty($date_reception)) {
		setEventMessages($langs->trans('ErrorFieldRequired', $langs->trans('RGWReceptionDate')), null, 'errors');
		$error++;
	}
	if (!$error) {
		if (empty($date_limit)) {
			$date_limit = dol_time_plus_duree($date_reception, getDolGlobalInt('RGWARRANTY_DELAY_DAYS', 365), 'd');
		}
		$object->date_reception = $date_reception;
		$object->date_limit = $date_limit;
		$object->status = RGCycle::STATUS_IN_PROGRESS;
		$object->fk_user_modif = $user->id;
		$object->update($user);

		rgwarranty_log_event($db, $conf->entity, $object->id, 'RG_RECEPTION_SET', $langs->trans('RGWReceptionSet'), $user);
	}
	$action = '';
}

if ($reshook == 0 && in_array($action, array('request', 'reminder')) && $permissiontowrite) {
	if (empty($object->date_reception)) {
		setEventMessages($langs->trans('RGWReceptionRequired'), null, 'errors');
		$action = '';
	} else {
		$object->model_pdf = getDolGlobalString('RGWARRANTY_PDF_MODEL', 'rgrequest');
		$object->generateDocument($object->model_pdf, $langs, 0, 0, 0);
		if ($action == 'request') {
			rgwarranty_log_event($db, $conf->entity, $object->id, 'RG_REQUEST_PDF', $langs->trans('RGWRequestPdfGenerated'), $user);
			$object->status = RGCycle::STATUS_REQUESTED;
			$object->fk_user_modif = $user->id;
			$object->update($user);
		} else {
			rgwarranty_log_event($db, $conf->entity, $object->id, 'RG_REMINDER_PDF', $langs->trans('RGWReminderPdfGenerated'), $user);
			$mailcontext = 'reminder';
		}
		$action = 'presend';
	}
}

// EN: Generate document from selected model
// FR: Générer le document depuis le modèle sélectionné
if ($reshook == 0 && $action == 'builddoc' && $permissiontowrite) {
	$model = GETPOST('model', 'alpha');
	if (empty($model)) {
		$model = getDolGlobalString('RGWARRANTY_PDF_MODEL', 'rgrequest');
	}
	$object->model_pdf = $model;
	$result = $object->generateDocument($model, $langs, 0, 0, 0);
	if ($result <= 0) {
		if (!empty($object->error) || (!empty($object->errors) && is_array($object->errors))) {
			setEventMessages($object->error, $object->errors, 'errors');
		}
	}
	$action = '';
}

// EN: Sync cycle lines
// FR: Synchroniser les lignes du cycle
$invoices = rgwarranty_fetch_invoices_for_cycle($db, $conf->entity, $object->situation_cycle_ref);
rgwarranty_sync_cycle_lines($db, $object, $invoices);
$totals = rgwarranty_get_cycle_totals($db, $object->id);

llxHeader('', $langs->trans('RGWCycle'));

$head = rgwarranty_cycle_prepare_head($object);
print dol_get_fiche_head($head, 'card', $langs->trans('RGWCycle'), -1, 'invoicing');
// EN: Entity scope (multicompany)
// FR: Périmètre entity (multicompany)
$entity = (!empty($object->entity) ? (int) $object->entity : (int) $conf->entity);

// EN: Prepare Thirdparty / Project labels for banner
// FR: Préparer les libellés Tiers / Projet pour la bannière
$thirdpartyLabel = '<span class="opacitymedium">'.$langs->trans('None').'</span>';
$soc = new Societe($db);
if (!empty($object->fk_soc)) {
	$thirdparty = new Societe($db);
	$thirdparty->fetch($object->fk_soc);
	$thirdpartyLabel = $thirdparty->getNomUrl(1);
	$soc = $thirdparty;
}

$projectLabel = '<span class="opacitymedium">'.$langs->trans('None').'</span>';
if (!empty($object->fk_projet) && isModEnabled('project')) {
	$project = new Project($db);
	$project->fetch($object->fk_projet);
	$projectLabel = $project->getNomUrl(1);
}

// EN: Find last generated file (icon or thumbnail)
// FR: Trouver le dernier fichier généré (icône ou miniature)
$ref = dol_sanitizeFileName($object->ref);
$relativepath = $object->element.'/'.$ref; // ex: rgw_cycle/RGW-3
$cycleFileDir = $conf->rgwarranty->multidir_output[$entity].'/'.$relativepath;

$lastdochtml = '';
$files = dol_dir_list($cycleFileDir, 'files', 0, '', '\.meta$', 'date', SORT_DESC);
if (!empty($files[0]['name'])) {
	$lastfilename = $files[0]['name'];
	$urldoc = DOL_URL_ROOT.'/document.php?modulepart=rgwarranty&file='.urlencode($relativepath.'/'.$lastfilename).'&entity='.$entity;

	if (preg_match('/\.(png|jpe?g|gif|webp)$/i', $lastfilename)) {
		$thumb = '<img class="photo photoinline" style="max-height:40px; max-width:40px;" src="'.DOL_URL_ROOT.'/viewimage.php?modulepart=rgwarranty&file='.urlencode($relativepath.'/'.$lastfilename).'&entity='.$entity.'" alt="'.dol_escape_htmltag($lastfilename).'">';
		$lastdochtml = '<a class="valignmiddle" href="'.$urldoc.'">'.$thumb.'</a>';
	} else {
		$lastdochtml = '<a class="valignmiddle" href="'.$urldoc.'">'.img_mime($lastfilename, $langs->trans('Show')).'</a>';
	}
	$lastdochtml .= ' <a class="opacitymedium" href="'.$urldoc.'">'.dol_trunc($lastfilename, 32).'</a>';
}

// EN: morehtmlref like core cards
// FR: morehtmlref comme les fiches core
$morehtmlref = '<div class="refidno">';
$morehtmlref .= $langs->trans('ThirdParty').' : '.$thirdpartyLabel.'<br>';
$morehtmlref .= $langs->trans('Project').' : '.$projectLabel;
$morehtmlref .= '</div>';

$linkback = '<a href="'.dol_buildpath('/rgwarranty/rg/index.php', 1).'">'.$langs->trans('BackToList').'</a>';
$morehtmlstatus = '';

dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref, '', 0, '', $morehtmlstatus);
print '<div class="underbanner clearboth"></div>';

$showactionsavailable = false;

// EN: Prepare labels for left column
// FR: Préparer les libellés pour la colonne gauche
$thirdpartyLabel = '<span class="opacitymedium">'.$langs->trans('None').'</span>';
$soc = new Societe($db);
if (!empty($object->fk_soc)) {
	$thirdparty = new Societe($db);
	$thirdparty->fetch($object->fk_soc);
	$thirdpartyLabel = $thirdparty->getNomUrl(1);
	$soc = $thirdparty;
}
$projectLabel = '<span class="opacitymedium">'.$langs->trans('None').'</span>';
if (!empty($object->fk_projet) && isModEnabled('project')) {
	$project = new Project($db);
	$project->fetch($object->fk_projet);
	$projectLabel = $project->getNomUrl(1);
}

print '<div class="fichecenter">';
print '<div class="fichehalfleft">';
print '<table class="border centpercent tableforfield">';
//print '<tr><td class="titlefield">'.$langs->trans('RGWCycleRef').'</td><td>'.dol_escape_htmltag($object->ref).'</td></tr>';
//print '<tr><td class="titlefield">'.$langs->trans('RGWSituationCycleRef').'</td><td>'.dol_escape_htmltag($object->situation_cycle_ref).'</td></tr>';
//print '<tr><td class="titlefield">'.$langs->trans('ThirdParty').'</td><td>'.$thirdpartyLabel.'</td></tr>';
//print '<tr><td class="titlefield">'.$langs->trans('Project').'</td><td>'.$projectLabel.'</td></tr>';
print '<tr><td class="titlefield">'.$langs->trans('RGWReceptionDate').'</td><td>'.dol_print_date($object->date_reception, 'day').'</td></tr>';
print '<tr><td class="titlefield">'.$langs->trans('RGWLimitDate').'</td><td>'.dol_print_date($object->date_limit, 'day').'</td></tr>';
print '</table>';
print '</div>';

print '<div class="fichehalfright">';
print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefield">'.$langs->trans('RGWTotalRG').'</td><td>'.price($totals['rg_total_ttc']).'</td></tr>';
print '<tr><td class="titlefield">'.$langs->trans('RGWRemainingRG').'</td><td>'.price($totals['rg_remaining_ttc']).'</td></tr>';
print '<tr><td class="titlefield">'.$langs->trans('Status').'</td><td>'.rgwarranty_get_cycle_status_badge($langs, $object->status).'</td></tr>';
print '</table>';
print '</div>';
print '<div class="clearboth"></div>';

if ($action == 'reception' && $permissiontowrite) {
	print '<a name="reception"></a>';
	print load_fiche_titre($langs->trans('RGWSetReception'));
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="reception_save">';
	print '<table class="border centpercent tableforfield">';
	print '<tr><td class="titlefield">'.$langs->trans('RGWReceptionDate').'</td><td>';
	print $form->selectDate($object->date_reception ? $object->date_reception : dol_now(), 'reception', 0, 0, 1);
	print '</td></tr>';
	$defaultlimit = $object->date_limit;
	if (empty($defaultlimit)) {
		$defaultlimit = dol_time_plus_duree(dol_now(), getDolGlobalInt('RGWARRANTY_DELAY_DAYS', 365), 'd');
	}
	print '<tr><td class="titlefield">'.$langs->trans('RGWLimitDate').'</td><td>';
	print $form->selectDate($defaultlimit, 'limit', 0, 0, 1);
	print '</td></tr>';
	print '</table>';
	print '<div class="center">';
	print '<input type="submit" class="button" value="'.$langs->trans('Save').'">';
	print '</div>';
	print '</form>';
}

// EN: Invoices table
// FR: Tableau des factures
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans('Invoice').'</th>';
print '<th>'.$langs->trans('Date').'</th>';
print '<th>'.$langs->trans('Status').'</th>';
print '<th class="right">'.$langs->trans('TotalTTC').'</th>';
print '<th class="right">'.$langs->trans('RGWAmount').'</th>';
print '<th class="right">'.$langs->trans('RGWPaid').'</th>';
print '<th class="right">'.$langs->trans('RGWRemainingRG').'</th>';
print '<th></th>';
print '</tr>';

foreach ($invoices as $invoice) {
	// EN: Build invoice status label with fallback when helper function is missing
	// FR: Construire le libellé de statut avec repli si la fonction helper manque
	$invoicestatuslabel = '';
	// EN: Use fk_statut value from invoice object for correct status
	// FR: Utiliser la valeur fk_statut de la facture pour le bon statut
	$invoicestatus = isset($invoice->statut) ? $invoice->statut : $invoice->status;
	$invoicestatuslabel = $invoice->getLibStatut(5);

	// EN: Avoid invalid dates for display
	// FR: Éviter les dates invalides à l'affichage
	$invoiceDate = '';
	if (!empty($invoice->datef)) {
		$invoiceDate = dol_print_date($invoice->datef, 'day');
	}

	$sql = "SELECT rg_amount_ttc, rg_paid_ttc FROM ".$db->prefix()."rgw_cycle_facture";
	$sql .= " WHERE fk_cycle = ".((int) $object->id)." AND fk_facture = ".((int) $invoice->rowid);
	$resline = $db->query($sql);
	$lineamount = 0;
	$linepaid = 0;
	if ($resline && ($line = $db->fetch_object($resline))) {
		$lineamount = price2num($line->rg_amount_ttc, 'MT');
		$linepaid = price2num($line->rg_paid_ttc, 'MT');
	}
	$lineremaining = price2num($lineamount - $linepaid, 'MT');

	print '<tr class="oddeven">';
	print '<td>'.$invoice->getNomUrl(1).'</td>';
	print '<td>'.$invoiceDate.'</td>';
	print '<td>'.$invoicestatuslabel.'</td>';
	print '<td class="right">'.price($invoice->total_ttc).'</td>';
	print '<td class="right">'.price($lineamount).'</td>';
	print '<td class="right">'.price($linepaid).'</td>';
	print '<td class="right">'.price($lineremaining).'</td>';
	print '<td class="center"><a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.$invoice->id.'">'.img_picto('', 'search').'</a></td>';
	print '</tr>';
}
print '</table>';
print '</div>';

// EN: Hook for extra fields and custom content
// FR: Hook pour champs supplémentaires et contenu personnalisé
$reshook = $hookmanager->executeHooks('formObjectOptions', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
} elseif (!empty($reshook)) {
	print $hookmanager->resPrint;
}

print '</div>';
print dol_get_fiche_end();

if ($action != 'presend') {
	print '<div class="tabsAction">';
	// EN: Hook to add action buttons
	// FR: Hook pour ajouter des boutons d'action
	$hookmanager->resPrint = '';
	$reshook = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $object, $action);
	if ($reshook < 0) {
		setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
	}
	if (empty($hookmanager->resPrint)) {
		if ($permissiontowrite && empty($object->date_reception)) {
			print dolGetButtonAction('', $langs->trans('RGWSetReception'), 'default', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=reception#reception', '', 1);
		}
		if ($permissiontowrite && !empty($object->date_reception)) {
			print dolGetButtonAction('', $langs->trans('RGWRequest'), 'default', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=request', '', 1);
			print dolGetButtonAction('', $langs->trans('RGWReminder'), 'default', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=reminder&mailcontext=reminder', '', 1);
		}
		if ($permissiontopay && $totals['rg_remaining_ttc'] > 0) {
			print dolGetButtonAction('', $langs->trans('RGWPayment'), 'default', dol_buildpath('/rgwarranty/rg/cycle_payment.php', 1).'?id='.$object->id, '', 1);
		}
	} else {
		print $hookmanager->resPrint;
	}
	print '</div>';
}

if ($action != 'prerelance' && $action != 'presend') {
	print '<div class="fichecenter"><div class="fichehalfleft">';
	print '<a name="builddoc"></a>'; // ancre

	// Generated documents
	$entity = (!empty($object->entity) ? (int) $object->entity : (int) $conf->entity);

	$model = GETPOST('model', 'alpha');
	if (empty($model)) $model = getDolGlobalString('RGWARRANTY_PDF_MODEL'); // ou le nom de constante que tu utilises

	// fichier card nomdumodule_card.php

	// Documents
	$includedocgeneration = 1;

	if ($includedocgeneration) {
		$objref = dol_sanitizeFileName($object->ref);
		$relativepath = $objref.'/'.$objref.'.pdf';
		$filedir = getMultidirOutput($object).'/'.$object->element.'/'.$objref;
		$urlsource = $_SERVER["PHP_SELF"]."?id=".$object->id;
		$genallowed = $permissiontoread; // If you can read, you can build the PDF to read content
		$delallowed = $permissiontowrite; // If you can create/edit, you can remove a file on card
		// ICI dans le 2e argument
		print $formfile->showdocuments('rgwarranty:Rgwarranty', $object->element.'/'.$objref, $filedir, $urlsource, $genallowed, $delallowed, $object->model_pdf, 1, 0, 0, 28, 0, '', '', '', $langs->defaultlang, '', $object);
	}

	$somethingshown = $formfile->numoffiles;
	print '</div>';
	print '<div class="fichehalfright">';

	// EN: Limit timeline to last events like core invoice card
	// FR: Limiter l'historique aux derniers événements comme la fiche facture
	$MAXEVENT = 5;

	// EN: Provide shortcuts to full conversation and list
	// FR: Fournir des raccourcis vers la conversation et la liste complètes
	$morehtmlcenter = '<div class="nowraponall">';
	//$morehtmlcenter .= dolGetButtonTitle($langs->trans('FullConversation'), '', 'fa fa-comments imgforviewmode', DOL_URL_ROOT.'/compta/facture/messaging.php?id='.$object->id);
	//$morehtmlcenter .= dolGetButtonTitle($langs->trans('FullList'), '', 'fa fa-bars imgforviewmode', DOL_URL_ROOT.'/compta/facture/agenda.php?id='.$object->id);
	$morehtmlcenter .= '</div>';

	// EN: Use native helper to show actions timeline
	// FR: Utiliser le helper natif pour afficher l'historique des actions
	$somethingshown = $formactions->showactions($object, 'rgw_cycle', '', 1, '', $MAXEVENT, '', $morehtmlcenter);
	
	print '</div></div>';
}

// EN: Presend mail form
// FR: Formulaire d'envoi email
if ($action == 'presend') {
	$modelmail = getDolGlobalString('RGWARRANTY_EMAILTPL_REQUEST', 'rgwarranty_request');
	if ($mailcontext == 'reminder') {
		$modelmail = getDolGlobalString('RGWARRANTY_EMAILTPL_REMINDER', 'rgwarranty_reminder');
	}
	$defaulttopic = 'RGWRequestLetterTitle';
	$diroutput = getMultidirOutput($object).'/'.$object->element.'/'.$objref;
	$trackid = 'rgwarranty'.$object->id;
	include DOL_DOCUMENT_ROOT.'/core/tpl/card_presend.tpl.php';
}

llxFooter();
$db->close();
