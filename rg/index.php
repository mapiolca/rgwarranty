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
 *\file		rgwarranty/rg/index.php
 *\ingroup	rgwarranty
 *\brief		RG cycles list (cockpit).
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
if (!$res && file_exists('../../main.inc.php')) {
	$res = @include '../../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
	$res = @include '../../../main.inc.php';
}
if (!$res) {
	die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';
require_once __DIR__.'/../lib/rgwarranty.lib.php';

$langs->loadLangs(array('rgwarranty@rgwarranty', 'companies', 'projects', 'bills'));

$permissiontoread = ($user->admin || $user->hasRight('rgwarranty', 'cycle', 'read'));
$permissiontowrite = ($user->admin || $user->hasRight('rgwarranty', 'cycle', 'write'));
$permissiontopay = ($user->admin || $user->hasRight('rgwarranty', 'cycle', 'pay'));

if (!$permissiontoread) {
	accessforbidden();
}

// EN: Sync missing cycles from situation invoices
// FR: Synchroniser les cycles manquants depuis les factures de situation
rgwarranty_sync_cycles_from_invoices($db, $conf->entity, $user);

$search_ref = GETPOST('search_ref', 'alpha');
$search_socid = GETPOSTINT('search_socid');
$search_project = GETPOSTINT('search_project');
$search_status = GETPOSTINT('search_status');
$search_date_reception_start = GETPOST('search_date_reception_start', 'alpha');
$search_date_reception_end = GETPOST('search_date_reception_end', 'alpha');
$search_date_limit_start = GETPOST('search_date_limit_start', 'alpha');
$search_date_limit_end = GETPOST('search_date_limit_end', 'alpha');

$form = new Form($db);
$formcompany = new FormCompany($db);
$formproject = new FormProjets($db);

llxHeader('', $langs->trans('RGWCockpit'));

print load_fiche_titre($langs->trans('RGWCockpit'), '', 'title_generic');

print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'">';
print '<div class="tabBar">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('RGWCycleRef').'</td>';
print '<td>'.$langs->trans('ThirdParty').'</td>';
print '<td>'.$langs->trans('Project').'</td>';
print '<td>'.$langs->trans('RGWReceptionDate').'</td>';
print '<td>'.$langs->trans('RGWLimitDate').'</td>';
print '<td>'.$langs->trans('RGWStatus').'</td>';
print '<td></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td><input type="text" class="maxwidth100" name="search_ref" value="'.dol_escape_htmltag($search_ref).'" /></td>';
print '<td>'.$formcompany->select_company($search_socid, 'search_socid', '', 1, 0, 0, array(), 0, 'minwidth150').'</td>';
print '<td>'.$formproject->select_projects($search_project, 'search_project', 0, 0, 0, 1, 0, 0, 0, 0, 'minwidth150').'</td>';
print '<td>';
print $form->selectDate($search_date_reception_start, 'search_date_reception_start', 0, 0, 1, '', 1, 0, 1);
print ' - ';
print $form->selectDate($search_date_reception_end, 'search_date_reception_end', 0, 0, 1, '', 1, 0, 1);
print '</td>';
print '<td>';
print $form->selectDate($search_date_limit_start, 'search_date_limit_start', 0, 0, 1, '', 1, 0, 1);
print ' - ';
print $form->selectDate($search_date_limit_end, 'search_date_limit_end', 0, 0, 1, '', 1, 0, 1);
print '</td>';
print '<td>';
print $form->selectarray('search_status', array('' => '', 0 => $langs->trans('RGWStatusDraft'), 1 => $langs->trans('RGWStatusInProgress'), 2 => $langs->trans('RGWStatusToRequest'), 3 => $langs->trans('RGWStatusRequested'), 4 => $langs->trans('RGWStatusPartial'), 5 => $langs->trans('RGWStatusRefunded')), $search_status, 1);
print '</td>';
print '<td class="right">'.$form->showFilterAndCheckAddButtons(0, 0, 1).'</td>';
print '</tr>';

print '</table>';
print '</div>';
print '</form>';

$sql = "SELECT c.rowid, c.ref, c.situation_cycle_ref, c.fk_soc, s.nom as socname, c.fk_projet, p.ref as project_ref, p.title as project_title,";
$sql .= " c.date_reception, c.date_limit, c.status, SUM(cf.rg_amount_ttc) as rg_total_ttc, SUM(cf.rg_paid_ttc) as rg_paid_ttc";
$sql .= " FROM ".$db->prefix()."rgw_cycle as c";
$sql .= " LEFT JOIN ".$db->prefix()."societe as s ON s.rowid = c.fk_soc";
$sql .= " LEFT JOIN ".$db->prefix()."projet as p ON p.rowid = c.fk_projet";
$sql .= " LEFT JOIN ".$db->prefix()."rgw_cycle_facture as cf ON cf.fk_cycle = c.rowid";
$sql .= " WHERE c.entity = ".((int) $conf->entity);

if (!empty($search_ref)) {
	$sql .= " AND (c.ref LIKE '%".$db->escape($search_ref)."%' OR c.situation_cycle_ref LIKE '%".$db->escape($search_ref)."%')";
}
if ($search_socid > 0) {
	$sql .= " AND c.fk_soc = ".((int) $search_socid);
}
if ($search_project > 0) {
	$sql .= " AND c.fk_projet = ".((int) $search_project);
}
if ($search_status !== '' && $search_status >= 0) {
	$sql .= " AND c.status = ".((int) $search_status);
}
if (!empty($search_date_reception_start)) {
	$sql .= " AND c.date_reception >= '".$db->idate(dol_stringtotime($search_date_reception_start))."'";
}
if (!empty($search_date_reception_end)) {
	$sql .= " AND c.date_reception <= '".$db->idate(dol_stringtotime($search_date_reception_end))."'";
}
if (!empty($search_date_limit_start)) {
	$sql .= " AND c.date_limit >= '".$db->idate(dol_stringtotime($search_date_limit_start))."'";
}
if (!empty($search_date_limit_end)) {
	$sql .= " AND c.date_limit <= '".$db->idate(dol_stringtotime($search_date_limit_end))."'";
}

$sql .= " GROUP BY c.rowid";
$sql .= " ORDER BY c.date_limit DESC, c.rowid DESC";

$resql = $db->query($sql);
if ($resql) {
	print '<div class="div-table-responsive">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<th>'.$langs->trans('RGWCycleRef').'</th>';
	print '<th>'.$langs->trans('ThirdParty').'</th>';
	print '<th>'.$langs->trans('Project').'</th>';
	print '<th>'.$langs->trans('RGWReceptionDate').'</th>';
	print '<th>'.$langs->trans('RGWLimitDate').'</th>';
	print '<th class="right">'.$langs->trans('RGWTotalRG').'</th>';
	print '<th class="right">'.$langs->trans('RGWRemainingRG').'</th>';
	print '<th>'.$langs->trans('Status').'</th>';
	print '<th class="center">'.$langs->trans('Actions').'</th>';
	print '</tr>';

	while ($obj = $db->fetch_object($resql)) {
		$total = price2num($obj->rg_total_ttc, 'MT');
		$paid = price2num($obj->rg_paid_ttc, 'MT');
		$remaining = price2num($total - $paid, 'MT');

		print '<tr class="oddeven">';
		print '<td><a href="'.dol_buildpath('/rgwarranty/rg/cycle_card.php', 1).'?id='.$obj->rowid.'">'.dol_escape_htmltag($obj->ref).'</a></td>';
		print '<td>';
		if (!empty($obj->fk_soc)) {
			print '<a href="'.DOL_URL_ROOT.'/societe/card.php?socid='.$obj->fk_soc.'">'.dol_escape_htmltag($obj->socname).'</a>';
		}
		print '</td>';
		print '<td>';
		if (!empty($obj->fk_projet)) {
			print '<a href="'.DOL_URL_ROOT.'/projet/card.php?id='.$obj->fk_projet.'">'.dol_escape_htmltag($obj->project_ref.' - '.$obj->project_title).'</a>';
		}
		print '</td>';
		print '<td>'.dol_print_date($db->jdate($obj->date_reception), 'day').'</td>';
		print '<td>'.dol_print_date($db->jdate($obj->date_limit), 'day').'</td>';
		print '<td class="right">'.price($total).'</td>';
		print '<td class="right">'.price($remaining).'</td>';
		print '<td>'.rgwarranty_get_cycle_status_badge($langs, (int) $obj->status).'</td>';
		print '<td class="center">';
		if ($permissiontowrite && empty($obj->date_reception)) {
			print '<a class="marginleftonly" title="'.$langs->trans('RGWSetReception').'" href="'.dol_buildpath('/rgwarranty/rg/cycle_card.php', 1).'?id='.$obj->rowid.'&action=reception#reception">'.img_picto('', 'calendar').'</a>';
		}
		if ($permissiontowrite && !empty($obj->date_reception)) {
			print '<a class="marginleftonly" title="'.$langs->trans('RGWRequest').'" href="'.dol_buildpath('/rgwarranty/rg/cycle_card.php', 1).'?id='.$obj->rowid.'&action=request">'.img_picto('', 'email').'</a>';
		}
		if ($permissiontopay && $remaining > 0) {
			print '<a class="marginleftonly" title="'.$langs->trans('RGWPayment').'" href="'.dol_buildpath('/rgwarranty/rg/cycle_payment.php', 1).'?id='.$obj->rowid.'">'.img_picto('', 'payment').'</a>';
		}
		print '<a class="marginleftonly" title="'.$langs->trans('Open'). '" href="'.dol_buildpath('/rgwarranty/rg/cycle_card.php', 1).'?id='.$obj->rowid.'">'.img_picto('', 'search').'</a>';
		print '</td>';
		print '</tr>';
	}

	print '</table>';
	print '</div>';
}

llxFooter();
$db->close();
