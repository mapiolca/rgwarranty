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
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once __DIR__.'/../lib/rgwarranty.lib.php';

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
$value = GETPOST('value', 'alpha');
$label = GETPOST('label', 'alpha');
$scandir = GETPOST('scan_dir', 'alpha');
$type = 'rgwarranty';

$form = new Form($db);

if (in_array($action, array('save', 'setmod', 'set', 'del', 'setdoc', 'specimen'), true)) {
	// EN: Enforce CSRF token validation for state-changing actions.
	// FR: Forcer la validation du jeton CSRF pour les actions modifiant l'état.
	if (GETPOST('token', 'alphanohtml') !== newToken()) {
		accessforbidden();
	}
}

/*
 * Actions
 */

if ($action == 'save') {
	// EN: Save module constants
	// FR: Enregistrer les constantes du module
	$delayDays = GETPOSTINT('RGWARRANTY_DELAY_DAYS');
	$remindDays = GETPOSTINT('RGWARRANTY_REMIND_BEFORE_DAYS');
	$autosend = GETPOSTINT('RGWARRANTY_AUTOSEND');
	$emailRequest = GETPOST('RGWARRANTY_EMAILTPL_REQUEST', 'alpha');
	$emailReminder = GETPOST('RGWARRANTY_EMAILTPL_REMINDER', 'alpha');

	dolibarr_set_const($db, 'RGWARRANTY_DELAY_DAYS', $delayDays, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'RGWARRANTY_REMIND_BEFORE_DAYS', $remindDays, 'chaine', 0, '', $conf->entity);
	if (!function_exists('ajax_constantonoff')) {
		dolibarr_set_const($db, 'RGWARRANTY_AUTOSEND', $autosend, 'chaine', 0, '', $conf->entity);
	}
	dolibarr_set_const($db, 'RGWARRANTY_EMAILTPL_REQUEST', $emailRequest, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'RGWARRANTY_EMAILTPL_REMINDER', $emailReminder, 'chaine', 0, '', $conf->entity);

	setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
} elseif ($action == 'setmod') {
	// EN: Handle numbering module activation
	// FR: Gérer l'activation du module de numérotation
	if (!empty($value)) {
		dolibarr_set_const($db, 'RGWARRANTY_ADDON', $value, 'chaine', 0, '', $conf->entity);
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	}
} elseif ($action == 'set') {
	// EN: Activate a document model
	// FR: Activer un modèle de document
	$res = addDocumentModel($value, $type, $label, $scandir);
	if ($res > 0) {
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	} else {
		setEventMessages($langs->trans('Error'), null, 'errors');
	}
} elseif ($action == 'del') {
	// EN: Disable a document model
	// FR: Désactiver un modèle de document
	$res = delDocumentModel($value, $type);
	if ($res > 0) {
		if (getDolGlobalString('RGWARRANTY_PDF_MODEL') == (string) $value) {
			dolibarr_del_const($db, 'RGWARRANTY_PDF_MODEL', $conf->entity);
		}
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	} else {
		setEventMessages($langs->trans('Error'), null, 'errors');
	}
} elseif ($action == 'setdoc') {
	// EN: Set default document model
	// FR: Définir le modèle de document par défaut
	if (dolibarr_set_const($db, 'RGWARRANTY_PDF_MODEL', $value, 'chaine', 0, '', $conf->entity)) {
		$conf->global->RGWARRANTY_PDF_MODEL = $value;
	}

	$res = delDocumentModel($value, $type);
	if ($res > 0) {
		$res = addDocumentModel($value, $type, $label, $scandir);
	}
} elseif ($action == 'specimen') {
	// EN: Specimen not available for this module
	// FR: Spécimen non disponible pour ce module
	setEventMessages($langs->trans('NotAvailable'), null, 'warnings');
}

llxHeader('', $langs->trans('RGWModuleSetup'), '', '', 0, 0, '', '', '', 'mod-admin page-rgwarranty');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans('BackToModuleList').'</a>';

print load_fiche_titre($langs->trans('RGWModuleSetup'), $linkback, 'title_setup');

$head = rgwarranty_admin_prepare_head();
print dol_get_fiche_head($head, 'setup', $langs->trans('RGWModuleSetup'), -1, 'setup');

// EN: Define model directories to scan
// FR: Définir les répertoires de modèles à analyser
$dirmodels = array_merge(array('/custom/'), (array) $conf->modules_parts['models'], array('/'));

// EN: Section Numbering
// FR: Section Numérotation
print load_fiche_titre($langs->trans('RGWAdminSectionNumbering'));
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Name').'</td>';
print '<td>'.$langs->trans('Description').'</td>';
print '<td>'.$langs->trans('Example').'</td>';
print '<td>'.$langs->trans('Status').'</td>';
print '<td class="center">'.$langs->trans('Action').'</td>';
print '</tr>';

$activeNumbering = getDolGlobalString('RGWARRANTY_ADDON');
$arrayofmodules = array();

foreach ($dirmodels as $reldir) {
	$dir = dol_buildpath($reldir."rgwarranty/core/modules/rgwarranty/");
	if (is_dir($dir)) {
		$handle = opendir($dir);
		if (is_resource($handle)) {
			while (($file = readdir($handle)) !== false) {
				if (!is_dir($dir.$file) && preg_match('/^mod_.*\.php$/', $file)) {
					$classname = preg_replace('/\.php$/', '', $file);
					require_once $dir.$file;

					if (class_exists($classname)) {
						$module = new $classname($db);
						$arrayofmodules[] = $module;
					}
				}
			}
			closedir($handle);
		}
	}
}

$arrayofmodules = dol_sort_array($arrayofmodules, 'position');

if (!empty($arrayofmodules)) {
	foreach ($arrayofmodules as $module) {
		$modulelabel = $module->name;
		if (!empty($module->nom)) {
			$modulelabel = $module->nom;
		}
		$modulename = '';
		if (method_exists($module, 'getName')) {
			$modulename = $module->getName($langs);
		} elseif (!empty($module->name)) {
			$modulename = $module->name;
		} else {
			$modulename = get_class($module);
		}
		$example = '-';
		if (method_exists($module, 'getExample')) {
			$example = $module->getExample();
		}
		$enabled = ($activeNumbering === $modulename || $activeNumbering === get_class($module));

		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag($modulelabel).'</td>';
		print '<td>'.dol_escape_htmltag($module->description).'</td>';
		print '<td>'.dol_escape_htmltag($example).'</td>';
		print '<td>'.($enabled ? $langs->trans('Enabled') : $langs->trans('Disabled')).'</td>';
		print '<td class="center">';
		if (!$enabled) {
			print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?action=setmod&token='.newToken().'&value='.urlencode($modulename).'">'.$langs->trans('Activate').'</a>';
		}
		print '</td>';
		print '</tr>';
	}
} else {
	print '<tr class="oddeven"><td colspan="5"><span class="opacitymedium">'.$langs->trans('RGWNoNumberingModule').'</span></td></tr>';
}
print '</table>';
print '</div>';

// EN: Section Document models
// FR: Section Modèles de documents
print load_fiche_titre($langs->trans('RGWAdminSectionDocuments'));
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Name').'</td>';
print '<td>'.$langs->trans('Description').'</td>';
print '<td class="center">'.$langs->trans('Status').'</td>';
print '<td class="center">'.$langs->trans('Default').'</td>';
print '<td class="center">'.$langs->trans('Action').'</td>';
print '</tr>';

// EN: Load array of activated templates
// FR: Charger le tableau des modèles activés
$def = array();
$sql = "SELECT nom";
$sql .= " FROM ".$db->prefix()."document_model";
$sql .= " WHERE type = '".$db->escape($type)."'";
$sql .= " AND entity = ".((int) $conf->entity);
$resql = $db->query($sql);
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$def[] = $obj->nom;
	}
} else {
	dol_print_error($db);
}

$defaultmodel = getDolGlobalString('RGWARRANTY_PDF_MODEL', 'rgrequest');
$foundmodel = 0;

foreach ($dirmodels as $reldir) {
	foreach (array('', '/doc') as $valdir) {
		$realpath = $reldir."rgwarranty/core/modules/rgwarranty".$valdir;
		$dir = dol_buildpath($realpath);

		if (is_dir($dir)) {
			$handle = opendir($dir);
			if (is_resource($handle)) {
				$filelist = array();
				while (($file = readdir($handle)) !== false) {
					$filelist[] = $file;
				}
				closedir($handle);
				arsort($filelist);

				foreach ($filelist as $file) {
					if (preg_match('/\.modules\.php$/i', $file) && preg_match('/^pdf_/', $file)) {
						if (file_exists($dir.'/'.$file)) {
							$foundmodel++;
							$name = substr($file, 4, dol_strlen($file) - 16);
							$classname = substr($file, 0, dol_strlen($file) - 12);

							require_once $dir.'/'.$file;
							$module = new $classname($db);

							$modulelabel = empty($module->name) ? $name : $module->name;
							$enabled = in_array($name, $def, true);
							$isdefault = ($name === $defaultmodel);

							print '<tr class="oddeven">';
							print '<td>'.dol_escape_htmltag($modulelabel).'</td>';
							print '<td>'.dol_escape_htmltag($module->description).'</td>';
							print '<td class="center">';
							if ($enabled) {
								print '<a class="reposition" href="'.$_SERVER['PHP_SELF'].'?action=del&token='.newToken().'&value='.urlencode($name).'">';
								print img_picto($langs->trans('Enabled'), 'switch_on');
								print '</a>';
							} else {
								print '<a class="reposition" href="'.$_SERVER['PHP_SELF'].'?action=set&token='.newToken().'&value='.urlencode($name).'&scan_dir='.urlencode($module->scandir).'&label='.urlencode($modulelabel).'">';
								print img_picto($langs->trans('Disabled'), 'switch_off');
								print '</a>';
							}
							print '</td>';
							print '<td class="center">';
							if ($isdefault) {
								print img_picto($langs->trans('Default'), 'on');
							} else {
								print '<a class="reposition" href="'.$_SERVER['PHP_SELF'].'?action=setdoc&token='.newToken().'&value='.urlencode($name).'&scan_dir='.urlencode($module->scandir).'&label='.urlencode($modulelabel).'">'.img_picto($langs->trans('SetAsDefault'), 'off').'</a>';
							}
							print '</td>';
							print '<td class="center">';
							print '<a class="reposition" href="'.$_SERVER['PHP_SELF'].'?action=specimen&token='.newToken().'&value='.urlencode($name).'">'.img_picto($langs->trans('Specimen'), 'pdf').'</a>';
							print '</td>';
							print '</tr>';
						}
					}
				}
			}
		}
	}
}

if (empty($foundmodel)) {
	print '<tr class="oddeven"><td colspan="5"><span class="opacitymedium">'.$langs->trans('RGWNoDocumentModel').'</span></td></tr>';
}
print '</table>';
print '</div>';

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';

// EN: Section Notifications
// FR: Section Notifications
print load_fiche_titre($langs->trans('RGWAdminSectionNotifications'));
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Parameter').'</td>';
print '<td>'.$langs->trans('Value').'</td>';
print '<td class="center">'.$langs->trans('Action').'</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$form->textwithpicto($langs->trans('RGWEmailTemplateRequest'), $langs->trans('RGWEmailTemplateRequestHelp')).'</td>';
print '<td colspan="2"><input type="text" class="minwidth200" name="RGWARRANTY_EMAILTPL_REQUEST" value="'.dol_escape_htmltag(getDolGlobalString('RGWARRANTY_EMAILTPL_REQUEST', 'rgwarranty_request')).'"></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$form->textwithpicto($langs->trans('RGWEmailTemplateReminder'), $langs->trans('RGWEmailTemplateReminderHelp')).'</td>';
print '<td colspan="2"><input type="text" class="minwidth200" name="RGWARRANTY_EMAILTPL_REMINDER" value="'.dol_escape_htmltag(getDolGlobalString('RGWARRANTY_EMAILTPL_REMINDER', 'rgwarranty_reminder')).'"></td>';
print '</tr>';

print '</table>';
print '</div>';

// EN: Section Automation
// FR: Section Automatisation
print load_fiche_titre($langs->trans('RGWAdminSectionAutomation'));
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Parameter').'</td>';
print '<td>'.$langs->trans('Value').'</td>';
print '<td class="center">'.$langs->trans('Action').'</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$form->textwithpicto($langs->trans('RGWDelayDays'), $langs->trans('RGWDelayDaysHelp')).'</td>';
print '<td colspan="2"><input type="number" min="0" class="maxwidth100" name="RGWARRANTY_DELAY_DAYS" value="'.(int) getDolGlobalInt('RGWARRANTY_DELAY_DAYS', 365).'"></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$form->textwithpicto($langs->trans('RGWRemindBeforeDays'), $langs->trans('RGWRemindBeforeDaysHelp')).'</td>';
print '<td colspan="2"><input type="number" min="0" class="maxwidth100" name="RGWARRANTY_REMIND_BEFORE_DAYS" value="'.(int) getDolGlobalInt('RGWARRANTY_REMIND_BEFORE_DAYS', 30).'"></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$form->textwithpicto($langs->trans('RGWAutoSend'), $langs->trans('RGWAutoSendHelp')).'</td>';
if (function_exists('ajax_constantonoff')) {
	print '<td>'.ajax_constantonoff('RGWARRANTY_AUTOSEND').'</td>';
	print '<td class="center"></td>';
} else {
	print '<td colspan="2">'.$form->selectyesno('RGWARRANTY_AUTOSEND', getDolGlobalInt('RGWARRANTY_AUTOSEND', 0), 1).'</td>';
}
print '</tr>';

print '</table>';
print '</div>';

print '<div class="center">';
print '<input type="submit" class="button" value="'.$langs->trans('Save').'">';
print '</div>';

print '</form>';

print dol_get_fiche_end();

llxFooter();
$db->close();
