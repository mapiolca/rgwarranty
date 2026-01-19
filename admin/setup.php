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
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formsetup.class.php';
dol_include_once('/gestionnairerg/lib/rgwarranty.lib.php');

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
$backtopage = GETPOST('backtopage', 'alpha');
$modulepart = GETPOST('modulepart', 'aZ09');

$formSetup = new FormSetup($db);

// EN: RG delay in days
// FR: Délai RG en jours
$item = $formSetup->newItem('RGWARRANTY_DELAY_DAYS');
$item->nameText = $langs->trans('RGWDelayDays');
$item->fieldAttr['type'] = 'number';
$item->fieldAttr['min'] = '0';

// EN: Reminder delay before limit
// FR: Délai de relance avant échéance
$item = $formSetup->newItem('RGWARRANTY_REMIND_BEFORE_DAYS');
$item->nameText = $langs->trans('RGWRemindBeforeDays');
$item->fieldAttr['type'] = 'number';
$item->fieldAttr['min'] = '0';

// EN: Autosend toggle
// FR: Activation des relances automatiques
$formSetup->newItem('RGWARRANTY_AUTOSEND')->setAsYesNo();

// EN: Default PDF model
// FR: Modèle PDF par défaut
$item = $formSetup->newItem('RGWARRANTY_PDF_MODEL');
$item->nameText = $langs->trans('RGWPdfModel');
$item->setAsSelect(array('rgrequest' => $langs->trans('RGWRequestPdfModel')));

// EN: Email template selection
// FR: Sélection des modèles d'email
$formSetup->newItem('RGWARRANTY_EMAILTPL_REQUEST')->setAsEmailTemplate('rgw_cycle');
$formSetup->newItem('RGWARRANTY_EMAILTPL_REMINDER')->setAsEmailTemplate('rgw_cycle');

llxHeader('', $langs->trans('RGWModuleSetup'));

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans('BackToModuleList').'</a>';

print load_fiche_titre($langs->trans('RGWModuleSetup'), $linkback, 'title_setup');

print '<div class="fichecenter">';
print '<div class="fichethirdleft">';

print $formSetup->printForm();

print '</div>';
print '</div>';

llxFooter();
$db->close();
