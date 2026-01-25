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
$search_status = GETPOST('search_status', 'alpha');
$search_date_reception_start = GETPOSTDATE('search_date_reception_start', 'getpost');
$search_date_reception_end = GETPOSTDATE('search_date_reception_end', 'getpostend');
$search_date_limit_start = GETPOSTDATE('search_date_limit_start', 'getpost');
$search_date_limit_end = GETPOSTDATE('search_date_limit_end', 'getpostend');

$sortfield = GETPOST('sortfield', 'aZ09');
$sortorder = GETPOST('sortorder', 'aZ09');
$page = GETPOSTINT('page');
$limit = GETPOSTINT('limit');
if (empty($limit)) {
	$limit = $conf->liste_limit;
}
if ($page < 0) {
	$page = 0;
}
$offset = $limit * $page;

if (GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	$page = 0;
}

if (GETPOST('button_removefilter', 'alpha')) {
	$search_ref = '';
	$search_socid = 0;
	$search_project = 0;
	$search_status = '';
	$search_date_reception_start = '';
	$search_date_reception_end = '';
	$search_date_limit_start = '';
	$search_date_limit_end = '';
}

$form = new Form($db);
$formcompany = new FormCompany($db);
$formproject = new FormProjets($db);

$title = $langs->trans('RGWCockpit');
llxHeader('', $title, $help_url, '', 0, 0, '', '', '', 'bodyforlist'.($contextpage == 'poslist' ? ' '.$contextpage : ''));

$sortfields = array(
	'ref' => 'c.ref',
	'socname' => 's.nom',
	'project_ref' => 'p.ref',
	'date_reception' => 'c.date_reception',
	'date_limit' => 'c.date_limit',
	'status' => 'c.status',
	'rg_total_ttc' => 'rg_total_ttc',
	'rg_remaining_ttc' => 'rg_remaining_ttc'
);

if (empty($sortfield) || !array_key_exists($sortfield, $sortfields)) {
	$sortfield = 'date_limit';
}
if (empty($sortorder) || !in_array($sortorder, array('ASC', 'DESC'), true)) {
	$sortorder = 'DESC';
}

$param = '';
if ($search_ref !== '') {
	$param .= '&search_ref='.urlencode($search_ref);
}
if ($search_socid > 0) {
	$param .= '&search_socid='.((int) $search_socid);
}
if ($search_project > 0) {
	$param .= '&search_project='.((int) $search_project);
}
if ($search_status !== '') {
	$param .= '&search_status='.urlencode($search_status);
}
if ($search_date_reception_start) {
	$param .= '&search_date_reception_startday='.GETPOSTINT('search_date_reception_startday');
	$param .= '&search_date_reception_startmonth='.GETPOSTINT('search_date_reception_startmonth');
	$param .= '&search_date_reception_startyear='.GETPOSTINT('search_date_reception_startyear');
}
if ($search_date_reception_end) {
	$param .= '&search_date_reception_endday='.GETPOSTINT('search_date_reception_endday');
	$param .= '&search_date_reception_endmonth='.GETPOSTINT('search_date_reception_endmonth');
	$param .= '&search_date_reception_endyear='.GETPOSTINT('search_date_reception_endyear');
}
if ($search_date_limit_start) {
	$param .= '&search_date_limit_startday='.GETPOSTINT('search_date_limit_startday');
	$param .= '&search_date_limit_startmonth='.GETPOSTINT('search_date_limit_startmonth');
	$param .= '&search_date_limit_startyear='.GETPOSTINT('search_date_limit_startyear');
}
if ($search_date_limit_end) {
	$param .= '&search_date_limit_endday='.GETPOSTINT('search_date_limit_endday');
	$param .= '&search_date_limit_endmonth='.GETPOSTINT('search_date_limit_endmonth');
	$param .= '&search_date_limit_endyear='.GETPOSTINT('search_date_limit_endyear');
}

$sqlfrom = " FROM ".$db->prefix()."rgw_cycle as c";
$sqlfrom .= " LEFT JOIN ".$db->prefix()."societe as s ON s.rowid = c.fk_soc";
$sqlfrom .= " LEFT JOIN ".$db->prefix()."projet as p ON p.rowid = c.fk_projet";
$sqlfrom .= " LEFT JOIN ".$db->prefix()."rgw_cycle_facture as cf ON cf.fk_cycle = c.rowid";

$sqlwhere = " WHERE c.entity = ".((int) $conf->entity);
if ($search_ref !== '') {
	$sqlwhere .= " AND (c.ref LIKE '%".$db->escape($search_ref)."%' OR c.situation_cycle_ref LIKE '%".$db->escape($search_ref)."%')";
}
if ($search_socid > 0) {
	$sqlwhere .= " AND c.fk_soc = ".((int) $search_socid);
}
if ($search_project > 0) {
	$sqlwhere .= " AND c.fk_projet = ".((int) $search_project);
}
if ($search_status !== '') {
	$sqlwhere .= " AND c.status = ".((int) $search_status);
}
if (!empty($search_date_reception_start)) {
	$sqlwhere .= " AND c.date_reception >= '".$db->idate($search_date_reception_start)."'";
}
if (!empty($search_date_reception_end)) {
	$sqlwhere .= " AND c.date_reception <= '".$db->idate($search_date_reception_end)."'";
}
if (!empty($search_date_limit_start)) {
	$sqlwhere .= " AND c.date_limit >= '".$db->idate($search_date_limit_start)."'";
}
if (!empty($search_date_limit_end)) {
	$sqlwhere .= " AND c.date_limit <= '".$db->idate($search_date_limit_end)."'";
}

$sqlcount = "SELECT COUNT(DISTINCT c.rowid) as nb";
$sqlcount .= $sqlfrom.$sqlwhere;
$rescount = $db->query($sqlcount);
$nbtotalofrecords = 0;
if ($rescount) {
	$objcount = $db->fetch_object($rescount);
	$nbtotalofrecords = (int) $objcount->nb;
}

$sql = "SELECT c.rowid, c.ref, c.situation_cycle_ref, c.fk_soc, s.nom as socname, c.fk_projet, p.ref as project_ref, p.title as project_title,";
$sql .= " c.date_reception, c.date_limit, c.status,";
$sql .= " SUM(cf.rg_amount_ttc) as rg_total_ttc, SUM(cf.rg_paid_ttc) as rg_paid_ttc,";
$sql .= " (SUM(cf.rg_amount_ttc) - SUM(cf.rg_paid_ttc)) as rg_remaining_ttc";
$sql .= $sqlfrom.$sqlwhere;
$sql .= " GROUP BY c.rowid, c.ref, c.situation_cycle_ref, c.fk_soc, s.nom, c.fk_projet, p.ref, p.title, c.date_reception, c.date_limit, c.status";
$sql .= " ORDER BY ".$sortfields[$sortfield]." ".$sortorder.", c.rowid DESC";
$sql .= $db->plimit($limit, $offset);

$resql = $db->query($sql);
$num = 0;
if ($resql) {
	$num = $db->num_rows($resql);
}

//print_barre_liste($langs->trans('RGWCockpit'), $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, 'invoicing', $num, $nbtotalofrecords, 'title_generic');
print_barre_liste($title, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, 'invoicing', 0, $newcardbutton, '', $limit, 0, 0, 1);
//print load_fiche_titre($title, '', 'invoicing');


print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'">';
print '<input type="hidden" name="sortorder" value="'.dol_escape_htmltag($sortorder).'">';
print '<div class="div-table-responsive">';
print '<table class="tagtable liste">';
print '<tr class="liste_titre">';
print_liste_field_titre($langs->trans('RGWCycleRef'), $_SERVER['PHP_SELF'], 'ref', $param, '', '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('ThirdParty'), $_SERVER['PHP_SELF'], 'socname', $param, '', '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('Project'), $_SERVER['PHP_SELF'], 'project_ref', $param, '', '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('RGWReceptionDate'), $_SERVER['PHP_SELF'], 'date_reception', $param, '', '', $sortfield, $sortorder, 'center');
print_liste_field_titre($langs->trans('RGWLimitDate'), $_SERVER['PHP_SELF'], 'date_limit', $param, '', '', $sortfield, $sortorder, 'center');
print_liste_field_titre($langs->trans('RGWTotalRG'), $_SERVER['PHP_SELF'], 'rg_total_ttc', $param, '', '', $sortfield, $sortorder, 'right');
print_liste_field_titre($langs->trans('RGWRemainingRG'), $_SERVER['PHP_SELF'], 'rg_remaining_ttc', $param, '', '', $sortfield, $sortorder, 'right');
print_liste_field_titre($langs->trans('Status'), $_SERVER['PHP_SELF'], 'status', $param, '', '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('Actions'), $_SERVER['PHP_SELF'], '', $param, '', '', $sortfield, $sortorder, 'center');
print '</tr>';

print '<tr class="liste_titre_filter">';
print '<td class="liste_titre_filter"><input type="text" class="flat maxwidth100" name="search_ref" value="'.dol_escape_htmltag($search_ref).'"></td>';
//print '<td class="liste_titre_filter"><input type="text" class="flat maxwidth150" name="search_socid" value="'.dol_escape_htmltag($search_socid).'"></td>';
print '<td class="liste_titre_filter">'.$formcompany->select_company($search_socid, 'search_socid', '', 1, 0, 0, array(), 0, 'maxwidth200').'</td>';
print '<td class="liste_titre_filter">'.$formproject->select_projects($search_project, 'search_project', 0, 0, 0, 1, 0, 0, 0, 0, 'maxwidth200').'</td>';
print '<td class="liste_titre_filter">';
print '<div class="nowrapfordate">'.$langs->trans('From').' '.$form->selectDate($search_date_reception_start, 'search_date_reception_start', 0, 0, 1, '', 1, 0);.'</div>';
print '<div class="nowrapfordate">'.$langs->trans('to').' '.$form->selectDate($search_date_reception_end,   'search_date_reception_end',   0, 0, 1, '', 1, 0);.'</div>';
print '</td>';
print '<td class="liste_titre_filter">';
print '<div class="nowrapfordate">'.$langs->trans('From').' '.$form->selectDate($search_date_limit_start,     'search_date_limit_start',     0, 0, 1, '', 1, 0);.'</div>';
print '<div class="nowrapfordate">'.$langs->trans('to').' '.$form->selectDate($search_date_limit_end,       'search_date_limit_end',       0, 0, 1, '', 1, 0);.'</div>';
print '</td>';
print '<td class="liste_titre_filter"></td>';
print '<td class="liste_titre_filter"></td>';
print '<td class="liste_titre_filter">';
print $form->selectarray('search_status', array('' => '', 0 => $langs->trans('RGWStatusDraft'), 1 => $langs->trans('RGWStatusInProgress'), 2 => $langs->trans('RGWStatusToRequest'), 3 => $langs->trans('RGWStatusRequested'), 4 => $langs->trans('RGWStatusPartial'), 5 => $langs->trans('RGWStatusRefunded')), $search_status, 1, 0, 0, '', 0, 0, 0, '', 'flat');
print '</td>';
print '<td class="liste_titre_filter center">'.$form->showFilterAndCheckAddButtons(0, 0, 1).'</td>';
print '</tr>';

if ($resql) {
	if ($num) {
		while ($obj = $db->fetch_object($resql)) {
			$total = price2num($obj->rg_total_ttc, 'MT');
			$remaining = price2num($obj->rg_remaining_ttc, 'MT');

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
			print '<td class="center">'.dol_print_date($db->jdate($obj->date_reception), 'day').'</td>';
			print '<td class="center">'.dol_print_date($db->jdate($obj->date_limit), 'day').'</td>';
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
			print '<a class="marginleftonly" title="'.$langs->trans('Open').'" href="'.dol_buildpath('/rgwarranty/rg/cycle_card.php', 1).'?id='.$obj->rowid.'">'.img_picto('', 'search').'</a>';
			print '</td>';
			print '</tr>';
		}
	} else {
		print '<tr class="oddeven">';
		print '<td colspan="9"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td>';
		print '</tr>';
	}
}

print '</table>';
print '</div>';
print '</form>';

llxFooter();
$db->close();
