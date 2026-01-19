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

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/invoice.lib.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/lib/facture.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/rgwarranty/class/rg_cycle.class.php';
require_once DOL_DOCUMENT_ROOT.'/rgwarranty/lib/rgwarranty.lib.php';

$langs->loadLangs(array('rgwarranty@rgwarranty', 'companies', 'projects', 'bills', 'other'));

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$mailcontext = GETPOST('mailcontext', 'alpha');

$permissiontoread = $user->hasRight('rgwarranty', 'cycle', 'read');
$permissiontowrite = $user->hasRight('rgwarranty', 'cycle', 'write');
$permissiontopay = $user->hasRight('rgwarranty', 'cycle', 'pay');

if (!$permissiontoread) {
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

$error = 0;

// EN: Handle actions
// FR: Gérer les actions
if ($action == 'reception_save' && $permissiontowrite) {
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

if (in_array($action, array('request', 'reminder')) && $permissiontowrite) {
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

// EN: Sync cycle lines
// FR: Synchroniser les lignes du cycle
$invoices = rgwarranty_fetch_invoices_for_cycle($db, $conf->entity, $object->situation_cycle_ref);
rgwarranty_sync_cycle_lines($db, $object, $invoices);
$totals = rgwarranty_get_cycle_totals($db, $object->id);

llxHeader('', $langs->trans('RGWCycle'));

$head = array();
print dol_get_fiche_head($head, 'card', $langs->trans('RGWCycle'), -1, 'invoicing');

$linkback = '<a href="'.dol_buildpath('/rgwarranty/rg/index.php', 1).'">'.$langs->trans('BackToList').'</a>';
dol_banner_tab($object, 'ref', $linkback, 1, 'ref');

print '<div class="fichecenter">';
print '<div class="fichehalfleft">';
print '<table class="border centpercent">';
print '<tr><td class="titlefield">'.$langs->trans('RGWCycleRef').'</td><td>'.dol_escape_htmltag($object->ref).'</td></tr>';
print '<tr><td>'.$langs->trans('RGWSituationCycleRef').'</td><td>'.dol_escape_htmltag($object->situation_cycle_ref).'</td></tr>';
print '<tr><td>'.$langs->trans('ThirdParty').'</td><td>';
if (!empty($object->fk_soc)) {
	$thirdparty = new Societe($db);
	$thirdparty->fetch($object->fk_soc);
	print $thirdparty->getNomUrl(1);
}
print '</td></tr>';
print '<tr><td>'.$langs->trans('Project').'</td><td>';
if (!empty($object->fk_projet) && isModEnabled('project')) {
	$project = new Project($db);
	$project->fetch($object->fk_projet);
	print $project->getNomUrl(1);
}
print '</td></tr>';
print '<tr><td>'.$langs->trans('RGWReceptionDate').'</td><td>'.dol_print_date($object->date_reception, 'day').'</td></tr>';
print '<tr><td>'.$langs->trans('RGWLimitDate').'</td><td>'.dol_print_date($object->date_limit, 'day').'</td></tr>';
print '<tr><td>'.$langs->trans('RGWTotalRG').'</td><td>'.price($totals['rg_total_ttc']).'</td></tr>';
print '<tr><td>'.$langs->trans('RGWRemainingRG').'</td><td>'.price($totals['rg_remaining_ttc']).'</td></tr>';
print '<tr><td>'.$langs->trans('Status').'</td><td>'.rgwarranty_get_cycle_status_badge($langs, $object->status).'</td></tr>';
print '</table>';
print '</div>';

print '<div class="fichehalfright">';
print '</div>';
print '</div>';

if ($action != 'presend') {
	print '<div class="tabsAction">';
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
	print '</div>';
}

if ($action == 'reception' && $permissiontowrite) {
	print '<a name="reception"></a>';
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="reception_save">';
	print '<table class="border centpercent">';
	print '<tr><td class="titlefield">'.$langs->trans('RGWReceptionDate').'</td><td>';
	print $form->selectDate($object->date_reception ? $object->date_reception : dol_now(), 'reception', 0, 0, 1);
	print '</td></tr>';
	$defaultlimit = $object->date_limit;
	if (empty($defaultlimit)) {
		$defaultlimit = dol_time_plus_duree(dol_now(), getDolGlobalInt('RGWARRANTY_DELAY_DAYS', 365), 'd');
	}
	print '<tr><td>'.$langs->trans('RGWLimitDate').'</td><td>';
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
print '<div class="div-table-responsive">';
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
	print '<td><a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.$invoice->rowid.'">'.dol_escape_htmltag($invoice->ref).'</a></td>';
	print '<td>'.dol_print_date($db->jdate($invoice->datef), 'day').'</td>';
	print '<td>'.dol_print_invoice_status($invoice->status, 1).'</td>';
	print '<td class="right">'.price($invoice->total_ttc).'</td>';
	print '<td class="right">'.price($lineamount).'</td>';
	print '<td class="right">'.price($linepaid).'</td>';
	print '<td class="right">'.price($lineremaining).'</td>';
	print '<td class="center"><a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.$invoice->rowid.'">'.img_picto('', 'search').'</a></td>';
	print '</tr>';
}
print '</table>';
print '</div>';

// EN: Timeline events
// FR: Timeline des événements
print '<h3>'.$langs->trans('RGWTimeline').'</h3>';
print '<div class="div-table-responsive">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans('Date').'</th>';
print '<th>'.$langs->trans('Type').'</th>';
print '<th>'.$langs->trans('Label').'</th>';
print '<th>'.$langs->trans('User').'</th>';
print '</tr>';

$sql = "SELECT e.rowid, e.date_event, e.event_type, e.label, u.login";
$sql .= " FROM ".$db->prefix()."rgw_event as e";
$sql .= " LEFT JOIN ".$db->prefix()."user as u ON u.rowid = e.fk_user";
$sql .= " WHERE e.fk_cycle = ".((int) $object->id);
$sql .= " AND e.entity = ".((int) $conf->entity);
$sql .= " ORDER BY e.date_event DESC";
$resql = $db->query($sql);
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		print '<tr class="oddeven">';
		print '<td>'.dol_print_date($obj->date_event, 'dayhour').'</td>';
		print '<td>'.dol_escape_htmltag($obj->event_type).'</td>';
		print '<td>'.dol_escape_htmltag($obj->label).'</td>';
		print '<td>'.dol_escape_htmltag($obj->login).'</td>';
		print '</tr>';
	}
}
print '</table>';
print '</div>';

// EN: Presend mail form
// FR: Formulaire d'envoi email
if ($action == 'presend') {
	$modelmail = getDolGlobalString('RGWARRANTY_EMAILTPL_REQUEST', 'rgwarranty_request');
	if ($mailcontext == 'reminder') {
		$modelmail = getDolGlobalString('RGWARRANTY_EMAILTPL_REMINDER', 'rgwarranty_reminder');
	}
	$defaulttopic = 'RGWRequestLetterTitle';
	$diroutput = $conf->rgwarranty->dir_output;
	$trackid = 'rgwarranty'.$object->id;
	include DOL_DOCUMENT_ROOT.'/core/tpl/card_presend.tpl.php';
}

print dol_get_fiche_end();

llxFooter();
$db->close();
