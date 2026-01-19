<?php
/* Copyright (C) 2004-2017	Laurent Destailleur		<eldy@users.sourceforge.net>
 * Copyright (C) 2026		Pierre Ardoin			<developpeur@lesmetiersdubatiment.fr>
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
 *\file		rgwarranty/admin/setup.php
 *\ingroup	rgwarranty
 *\brief		Setup page for RG Warranty.
 */

// Load Dolibarr environment
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

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array('admin', 'rgwarranty@rgwarranty'));

if (!$user->admin) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');

$form = new Form($db);

if ($action == 'save') {
	// EN: Save module constants
	// FR: Enregistrer les constantes du module
	$delayDays = GETPOSTINT('RGWARRANTY_DELAY_DAYS');
	$remindDays = GETPOSTINT('RGWARRANTY_REMIND_BEFORE_DAYS');
	$autosend = GETPOSTINT('RGWARRANTY_AUTOSEND');
	$pdfModel = GETPOST('RGWARRANTY_PDF_MODEL', 'alpha');
	$emailRequest = GETPOST('RGWARRANTY_EMAILTPL_REQUEST', 'alpha');
	$emailReminder = GETPOST('RGWARRANTY_EMAILTPL_REMINDER', 'alpha');

	dolibarr_set_const($db, 'RGWARRANTY_DELAY_DAYS', $delayDays, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'RGWARRANTY_REMIND_BEFORE_DAYS', $remindDays, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'RGWARRANTY_AUTOSEND', $autosend, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'RGWARRANTY_PDF_MODEL', $pdfModel, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'RGWARRANTY_EMAILTPL_REQUEST', $emailRequest, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'RGWARRANTY_EMAILTPL_REMINDER', $emailReminder, 'chaine', 0, '', $conf->entity);

	setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
}

llxHeader('', $langs->trans('RGWModuleSetup'));

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans('BackToModuleList').'</a>';

print load_fiche_titre($langs->trans('RGWModuleSetup'), $linkback, 'title_setup');

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Parameter').'</td>';
print '<td>'.$langs->trans('Value').'</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans('RGWDelayDays').'</td>';
print '<td><input type="number" min="0" name="RGWARRANTY_DELAY_DAYS" value="'.(int) getDolGlobalInt('RGWARRANTY_DELAY_DAYS', 365).'"></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans('RGWRemindBeforeDays').'</td>';
print '<td><input type="number" min="0" name="RGWARRANTY_REMIND_BEFORE_DAYS" value="'.(int) getDolGlobalInt('RGWARRANTY_REMIND_BEFORE_DAYS', 30).'"></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans('RGWAutoSend').'</td>';
print '<td>'.$form->selectyesno('RGWARRANTY_AUTOSEND', getDolGlobalInt('RGWARRANTY_AUTOSEND', 0), 1).'</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans('RGWPdfModel').'</td>';
print '<td>'.$form->selectarray('RGWARRANTY_PDF_MODEL', array('rgrequest' => $langs->trans('RGWRequestPdfModel')), getDolGlobalString('RGWARRANTY_PDF_MODEL', 'rgrequest'), 0).'</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans('RGWEmailTemplateRequest').'</td>';
print '<td><input type="text" name="RGWARRANTY_EMAILTPL_REQUEST" value="'.dol_escape_htmltag(getDolGlobalString('RGWARRANTY_EMAILTPL_REQUEST', 'rgwarranty_request')).'"></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans('RGWEmailTemplateReminder').'</td>';
print '<td><input type="text" name="RGWARRANTY_EMAILTPL_REMINDER" value="'.dol_escape_htmltag(getDolGlobalString('RGWARRANTY_EMAILTPL_REMINDER', 'rgwarranty_reminder')).'"></td>';
print '</tr>';

print '</table>';

print '<div class="center">';
print '<input type="submit" class="button" value="'.$langs->trans('Save').'">';
print '</div>';

print '</form>';

llxFooter();
$db->close();
