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
 *\file		rgwarranty/rg/cycle_payment.php
 *\ingroup	rgwarranty
 *\brief		RG payment entry.
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
require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/rgwarranty/class/rg_cycle.class.php';
require_once DOL_DOCUMENT_ROOT.'/rgwarranty/lib/rgwarranty.lib.php';

$langs->loadLangs(array('rgwarranty@rgwarranty', 'banks', 'bills'));

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');

$permissiontoread = $user->hasRight('rgwarranty', 'cycle', 'read');
$permissiontopay = $user->hasRight('rgwarranty', 'cycle', 'pay');

if (!$permissiontoread || !$permissiontopay) {
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

$invoices = rgwarranty_fetch_invoices_for_cycle($db, $conf->entity, $object->situation_cycle_ref);
rgwarranty_sync_cycle_lines($db, $object, $invoices);
$totals = rgwarranty_get_cycle_totals($db, $object->id);

$error = 0;

if ($action == 'addpayment') {
	$amounts = GETPOST('amounts', 'array');
	$paymentdate = dol_mktime(0, 0, 0, GETPOSTINT('paymentmonth'), GETPOSTINT('paymentday'), GETPOSTINT('paymentyear'));
	$paiementid = GETPOSTINT('paiementcode');
	$bank_account = GETPOSTINT('bank_account');
	$num_paiement = GETPOST('num_paiement', 'alpha');
	$amount_total = price2num(GETPOST('amount_total', 'alpha'), 'MT');

	if (empty($paymentdate)) {
		setEventMessages($langs->trans('ErrorFieldRequired', $langs->trans('DatePayment')), null, 'errors');
		$error++;
	}
	if (empty($paiementid)) {
		setEventMessages($langs->trans('ErrorFieldRequired', $langs->trans('PaymentMode')), null, 'errors');
		$error++;
	}
	if (empty($bank_account)) {
		setEventMessages($langs->trans('ErrorFieldRequired', $langs->trans('Account')), null, 'errors');
		$error++;
	}
	if ($amount_total <= 0) {
		setEventMessages($langs->trans('ErrorFieldRequired', $langs->trans('Amount')), null, 'errors');
		$error++;
	}
	if ($amount_total > $totals['rg_remaining_ttc']) {
		setEventMessages($langs->trans('RGWAmountTooHigh', $object->ref), null, 'errors');
		$error++;
	}

	$sum = 0;
	$currency = '';
	$validatedAmounts = array();
	foreach ($invoices as $invoice) {
		$amount = isset($amounts[$invoice->rowid]) ? price2num($amounts[$invoice->rowid], 'MT') : 0;
		if ($amount < 0) {
			$amount = 0;
		}
		$sum += $amount;
		if (empty($currency)) {
			$currency = $invoice->multicurrency_code;
		} elseif ($currency != $invoice->multicurrency_code) {
			setEventMessages($langs->trans('RGWCurrencyMismatch'), null, 'errors');
			$error++;
			break;
		}

		$sql = "SELECT rg_amount_ttc, rg_paid_ttc FROM ".$db->prefix()."rgw_cycle_facture";
		$sql .= " WHERE fk_cycle = ".((int) $object->id)." AND fk_facture = ".((int) $invoice->rowid);
		$resline = $db->query($sql);
		if ($resline && ($line = $db->fetch_object($resline))) {
			$remaining = price2num($line->rg_amount_ttc - $line->rg_paid_ttc, 'MT');
			if ($amount > $remaining) {
				setEventMessages($langs->trans('RGWAmountTooHigh', $invoice->ref), null, 'errors');
				$error++;
				break;
			}
		}

		if ($amount > 0) {
			$validatedAmounts[$invoice->rowid] = $amount;
		}
	}

	if (!$error && price2num($sum, 'MT') != price2num($amount_total, 'MT')) {
		setEventMessages($langs->trans('RGWAmountSumMismatch'), null, 'errors');
		$error++;
	}
	if (!$error && empty($validatedAmounts)) {
		setEventMessages($langs->trans('RGWAmountSumMismatch'), null, 'errors');
		$error++;
	}

	if (!$error) {
		$db->begin();
		$paiement = new Paiement($db);
		$paiement->datepaye = $paymentdate;
		$paiement->amounts = $validatedAmounts;
		$paiement->paiementid = $paiementid;
		$paiement->num_paiement = $num_paiement;

		$result = $paiement->create($user);
		if ($result > 0) {
			$label = $langs->trans('RGWPaymentLabel', $object->ref);
			$bank_line_id = $paiement->addPaymentToBank($user, 'payment', $label, $bank_account);
			if ($bank_line_id > 0) {
				foreach ($validatedAmounts as $facid => $amount) {
					$sqlupdate = "UPDATE ".$db->prefix()."rgw_cycle_facture";
					$sqlupdate .= " SET rg_paid_ttc = rg_paid_ttc + ".$db->escape($amount);
					$sqlupdate .= " WHERE fk_cycle = ".((int) $object->id)." AND fk_facture = ".((int) $facid);
					$db->query($sqlupdate);
				}

				rgwarranty_log_event($db, $conf->entity, $object->id, 'RG_PAYMENT', $langs->trans('RGWPaymentRegistered'), $user, array(), $paiement->id, $bank_line_id);

				$totals = rgwarranty_get_cycle_totals($db, $object->id);
				$object->fk_user_modif = $user->id;
				$object->updateStatusFromTotals($totals['rg_remaining_ttc']);

				$db->commit();
				header('Location: '.dol_buildpath('/rgwarranty/rg/cycle_card.php', 1).'?id='.$object->id);
				exit;
			} else {
				$db->rollback();
				setEventMessages($paiement->error, $paiement->errors, 'errors');
			}
		} else {
			$db->rollback();
			setEventMessages($paiement->error, $paiement->errors, 'errors');
		}
	}
}

llxHeader('', $langs->trans('RGWPayment'));

print load_fiche_titre($langs->trans('RGWPayment'), '', 'title_generic');

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="addpayment">';

print '<table class="border centpercent">';
print '<tr><td class="titlefield">'.$langs->trans('DatePayment').'</td><td>';
print $form->selectDate(dol_now(), 'payment', 0, 0, 1);
print '</td></tr>';
print '<tr><td>'.$langs->trans('PaymentMode').'</td><td>';
print $form->select_types_paiements('', 'paiementcode', '', 0, 1);
print '</td></tr>';
print '<tr><td>'.$langs->trans('Account').'</td><td>';
print $form->select_comptes('', 'bank_account', 0, '', 1);
print '</td></tr>';
print '<tr><td>'.$langs->trans('RefPayment').'</td><td><input type="text" class="maxwidth200" name="num_paiement" value="" /></td></tr>';
print '<tr><td>'.$langs->trans('Amount').'</td><td><input type="text" name="amount_total" value="'.price2num($totals['rg_remaining_ttc'], 'MT').'" /></td></tr>';
print '</table>';

print '<br>';

print '<div class="div-table-responsive">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans('Invoice').'</th>';
print '<th class="right">'.$langs->trans('RGWRemainingRG').'</th>';
print '<th class="right">'.$langs->trans('RGWAmountToPay').'</th>';
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
	$remaining = price2num($lineamount - $linepaid, 'MT');

	print '<tr class="oddeven">';
	print '<td>'.dol_escape_htmltag($invoice->ref).'</td>';
	print '<td class="right">'.price($remaining).'</td>';
	print '<td class="right"><input type="text" name="amounts['.$invoice->rowid.']" value="'.price2num($remaining, 'MT').'" class="maxwidth100"></td>';
	print '</tr>';
}
print '</table>';
print '</div>';

print '<div class="center">';
print '<input type="submit" class="button" value="'.$langs->trans('Save').'">';
print '</div>';
print '</form>';

llxFooter();
$db->close();
