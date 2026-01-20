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
 *\file		lib/rgwarranty.lib.php
 *\ingroup	rgwarranty
 *\brief		Library for retention warranty helpers.
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';

/**
 * Prepare cycle tabs.
 *
 * @param	RGCycle	$object		Cycle object
 * @return	array				Tabs array
 */
function rgwarranty_cycle_prepare_head($object)
{
	global $langs;

	// EN: Prepare tabs for cycle card
	// FR: Préparer les onglets pour la carte cycle
	$langs->load('rgwarranty@rgwarranty');

	$head = array();
	$head[] = array(
		dol_buildpath('/rgwarranty/rg/cycle_card.php', 1).'?id='.$object->id,
		$langs->trans('Card'),
		'card'
	);

	return $head;
}

/**
 * Get status label.
 *
 * @param	Translate	$langs		Langs
 * @param	int			$status		Status
 * @return	string					Label
 */
function rgwarranty_get_cycle_status_label($langs, $status)
{
	// EN: Map status codes to translation keys
	// FR: Associer les statuts aux clés de traduction
	$labels = array(
		0 => 'RGWStatusDraft',
		1 => 'RGWStatusInProgress',
		2 => 'RGWStatusToRequest',
		3 => 'RGWStatusRequested',
		4 => 'RGWStatusPartial',
		5 => 'RGWStatusRefunded',
	);
	return $langs->trans($labels[$status] ?? 'Unknown');
}

/**
 * Get status badge.
 *
 * @param	Translate	$langs		Langs
 * @param	int			$status		Status
 * @return	string					HTML
 */
function rgwarranty_get_cycle_status_badge($langs, $status)
{
	// EN: Use Dolibarr badge classes
	// FR: Utiliser les classes badge Dolibarr
	$classes = array(
		0 => 'status0',
		1 => 'status4',
		2 => 'status2',
		3 => 'status6',
		4 => 'status8',
		5 => 'status9',
	);
	$label = rgwarranty_get_cycle_status_label($langs, $status);
	$class = $classes[$status] ?? 'status0';
	return '<span class="badge badge-status '.$class.'">'.dol_escape_htmltag($label).'</span>';
}

/**
 * Calculate RG amount TTC from invoice.
 *
 * @param	object		$invoice	Invoice data
 * @return	float					Amount
 */
function rgwarranty_calc_rg_amount_ttc($invoice)
{
	// EN: retained_warranty is a percentage
	// FR: retained_warranty est un pourcentage
	$rate = (float) $invoice->retained_warranty;
	if ($rate <= 0) {
		return 0.0;
	}
	$base = (float) $invoice->total_ttc;
	return price2num($base * $rate / 100, 'MT');
}

/**
 * Load invoices for a cycle.
 *
 * @param	DoliDB	$db		Database handler
 * @param	int		$entity		Entity id
 * @param	int		$situationCycleRef	Situation cycle ref
 * @return	array						List of invoice objects
 */
function rgwarranty_fetch_invoices_for_cycle($db, $entity, $situationCycleRef)
{
	// EN: Fetch invoices for the same situation cycle
	// FR: Charger les factures liées au même cycle de situation
	$invoices = array();
	// EN: Use fk_statut field name for invoices
	// FR: Utiliser le champ fk_statut pour les factures
	$sql = "SELECT f.rowid, f.ref, f.datef, f.total_ttc, f.fk_statut as status, f.retained_warranty, f.multicurrency_total_ttc, f.multicurrency_code, f.fk_soc, f.fk_projet";
	$sql .= " FROM ".$db->prefix()."facture as f";
	$sql .= " WHERE f.entity = ".((int) $entity);
	$sql .= " AND f.situation_cycle_ref = ".((int) $situationCycleRef);
	$sql .= " ORDER BY f.datef, f.rowid";

	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			// EN: Build a Facture object without extra queries
			// FR: Construire un objet Facture sans requêtes supplémentaires
			$invoice = new Facture($db);
			$invoice->id = (int) $obj->rowid;
			$invoice->rowid = (int) $obj->rowid;
			$invoice->ref = $obj->ref;
			$invoice->datef = $db->jdate($obj->datef);
			$invoice->total_ttc = price2num($obj->total_ttc, 'MT');
			$invoice->status = (int) $obj->status;
			$invoice->statut = (int) $obj->status;
			$invoice->retained_warranty = price2num($obj->retained_warranty, 'MU');
			$invoice->multicurrency_total_ttc = price2num($obj->multicurrency_total_ttc, 'MT');
			$invoice->multicurrency_code = $obj->multicurrency_code;
			$invoice->fk_soc = (int) $obj->fk_soc;
			$invoice->fk_projet = (int) $obj->fk_projet;

			$invoices[] = $invoice;
		}
	}
	if (!is_array($invoices)) {
		$invoices = array();
	}
	return $invoices;
}

/**
 * Sync cycle lines with invoices.
 *
 * @param	DoliDB	$db		Database handler
 * @param	object	$cycle		Cycle object
 * @param	array		$invoices	Invoices list
 * @return	int						>0 if ok, <0 if error
 */
function rgwarranty_sync_cycle_lines($db, $cycle, $invoices)
{
	// EN: Ensure each invoice has a line in rgw_cycle_facture
	// FR: S'assurer que chaque facture a une ligne dans rgw_cycle_facture
	$error = 0;
	foreach ($invoices as $invoice) {
		$amount = rgwarranty_calc_rg_amount_ttc($invoice);
		$multicurrencyAmount = 0.0;
		if (!empty($invoice->multicurrency_total_ttc)) {
			$multicurrencyAmount = price2num($invoice->multicurrency_total_ttc * (float) $invoice->retained_warranty / 100, 'MT');
		}

		$sql = "SELECT rowid, rg_amount_ttc, rg_paid_ttc FROM ".$db->prefix()."rgw_cycle_facture";
		$sql .= " WHERE entity = ".((int) $cycle->entity);
		$sql .= " AND fk_cycle = ".((int) $cycle->id);
		$sql .= " AND fk_facture = ".((int) $invoice->rowid);
		$resql = $db->query($sql);
		if (!$resql) {
			$error++;
			continue;
		}
		if ($db->num_rows($resql)) {
			$obj = $db->fetch_object($resql);
			if (price2num($obj->rg_amount_ttc) != $amount && price2num($obj->rg_paid_ttc) <= 0) {
				$sqlupdate = "UPDATE ".$db->prefix()."rgw_cycle_facture";
				$sqlupdate .= " SET rg_amount_ttc = ".$db->escape($amount).", multicurrency_rg_amount_ttc = ".$db->escape($multicurrencyAmount);
				$sqlupdate .= " WHERE rowid = ".((int) $obj->rowid);
				if (!$db->query($sqlupdate)) {
					$error++;
				}
			}
		} else {
			$sqlinsert = "INSERT INTO ".$db->prefix()."rgw_cycle_facture";
			$sqlinsert .= "(entity, fk_cycle, fk_facture, rg_amount_ttc, rg_paid_ttc, multicurrency_rg_amount_ttc, multicurrency_rg_paid_ttc, datec)";
			$sqlinsert .= " VALUES (".((int) $cycle->entity).", ".((int) $cycle->id).", ".((int) $invoice->rowid).", ".$db->escape($amount).", 0, ".$db->escape($multicurrencyAmount).", 0, '".$db->idate(dol_now())."')";
			if (!$db->query($sqlinsert)) {
				$error++;
			}
		}
	}
	return ($error ? -1 : 1);
}

/**
 * Get totals for a cycle.
 *
 * @param	DoliDB	$db		Database handler
 * @param	int		$cycleId	Cycle id
 * @return	array						Totals array
 */
function rgwarranty_get_cycle_totals($db, $cycleId)
{
	// EN: Sum RG amounts for the cycle
	// FR: Calculer les totaux RG du cycle
	$totals = array('rg_total_ttc' => 0, 'rg_paid_ttc' => 0, 'rg_remaining_ttc' => 0);
	$sql = "SELECT SUM(rg_amount_ttc) as total_amount, SUM(rg_paid_ttc) as total_paid";
	$sql .= " FROM ".$db->prefix()."rgw_cycle_facture";
	$sql .= " WHERE fk_cycle = ".((int) $cycleId);
	$resql = $db->query($sql);
	if ($resql) {
		$obj = $db->fetch_object($resql);
		$totals['rg_total_ttc'] = price2num($obj->total_amount, 'MT');
		$totals['rg_paid_ttc'] = price2num($obj->total_paid, 'MT');
		$totals['rg_remaining_ttc'] = price2num($totals['rg_total_ttc'] - $totals['rg_paid_ttc'], 'MT');
	}
	return $totals;
}

/**
 * Log an event for a cycle.
 *
 * @param	DoliDB	$db		Database handler
 * @param	int		$entity		Entity id
 * @param	int		$cycleId	Cycle id
 * @param	string	$type		Event type
 * @param	string	$label		Label
 * @param	User	$user		User
 * @param	array		$extra		Extra params
 * @param	int		$fkAction	Actioncomm id
 * @param	int		$fkPaiement	Payment id
 * @param	int		$fkBank		Bank id
 * @return	int						>0 if ok
 */
function rgwarranty_log_event($db, $entity, $cycleId, $type, $label, $user, $extra = array(), $fkAction = 0, $fkPaiement = 0, $fkBank = 0)
{
	// EN: Store event into rgw_event
	// FR: Enregistrer l'événement dans rgw_event
	$userId = 0;
	if (is_object($user)) {
		$userId = (int) $user->id;
	}
	$sql = "INSERT INTO ".$db->prefix()."rgw_event";
	$sql .= "(entity, fk_cycle, date_event, event_type, label, fk_user, fk_actioncomm, fk_paiement, fk_bank, extraparams, datec)";
	$sql .= " VALUES (".((int) $entity).", ".((int) $cycleId).", '".$db->idate(dol_now())."', '".$db->escape($type)."', '".$db->escape($label)."', ".$userId.", ".((int) $fkAction).", ".((int) $fkPaiement).", ".((int) $fkBank).", '".$db->escape(json_encode($extra))."', '".$db->idate(dol_now())."')";
	return $db->query($sql) ? 1 : -1;
}

/**
 * Sync cycles from invoices.
 *
 * @param	DoliDB	$db		Database handler
 * @param	int		$entity		Entity id
 * @param	User	$user		User
 * @return	int						>0 if ok
 */
function rgwarranty_sync_cycles_from_invoices($db, $entity, $user)
{
	// EN: Create missing cycles from situation invoices
	// FR: Créer les cycles manquants depuis les factures de situation
	$sql = "SELECT f.situation_cycle_ref, MAX(f.fk_soc) as fk_soc, MAX(f.fk_projet) as fk_projet";
	$sql .= " FROM ".$db->prefix()."facture as f";
	$sql .= " LEFT JOIN ".$db->prefix()."rgw_cycle as c ON c.situation_cycle_ref = f.situation_cycle_ref AND c.entity = f.entity";
	$sql .= " WHERE f.entity = ".((int) $entity);
	$sql .= " AND f.situation_cycle_ref IS NOT NULL";
	$sql .= " AND f.situation_cycle_ref > 0";
	$sql .= " AND f.retained_warranty > 0";
	$sql .= " AND c.rowid IS NULL";
	$sql .= " GROUP BY f.situation_cycle_ref";

	$resql = $db->query($sql);
	if (!$resql) {
		return -1;
	}
	while ($obj = $db->fetch_object($resql)) {
		$ref = 'RGW-'.$obj->situation_cycle_ref;
		$sqlinsert = "INSERT INTO ".$db->prefix()."rgw_cycle";
		$sqlinsert .= "(entity, ref, situation_cycle_ref, fk_soc, fk_projet, status, fk_user_author, datec)";
		$sqlinsert .= " VALUES (".((int) $entity).", '".$db->escape($ref)."', ".((int) $obj->situation_cycle_ref).", ".((int) $obj->fk_soc).", ".((int) $obj->fk_projet).", 0, ".((int) $user->id).", '".$db->idate(dol_now())."')";
		$db->query($sqlinsert);
	}
	return 1;
}
