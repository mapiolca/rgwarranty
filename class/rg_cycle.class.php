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
 *\file		class/rg_cycle.class.php
 *\ingroup	rgwarranty
 *\brief		Object class for RG cycle.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

/**
 * Class for RG Cycle
 */
class RGCycle extends CommonObject
{
	/**
	 * @var string ID of module.
	 */
	public $module = 'rgwarranty';

	/**
	 * @var string ID to identify managed object.
	 */
	public $element = 'rgw_cycle';

	/**
	 * @var string Name of table without prefix.
	 */
	public $table_element = 'rgw_cycle';

	/**
	 * @var int<0,1> Does object support extrafields ?
	 */
	public $isextrafieldmanaged = 0;

	/**
	 * @var int<0,1> Does object support multicompany module ?
	 */
	public $ismultientitymanaged = 1;

	const STATUS_DRAFT = 0;
	const STATUS_IN_PROGRESS = 1;
	const STATUS_TO_REQUEST = 2;
	const STATUS_REQUESTED = 3;
	const STATUS_PARTIAL = 4;
	const STATUS_REFUNDED = 5;

	/**
	 * @var array<string,array> Fields
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => '1', 'position' => 1, 'notnull' => 1, 'visible' => '0', 'noteditable' => '1', 'index' => '1'),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => '1', 'position' => 2, 'notnull' => 1, 'visible' => '0', 'index' => '1'),
		'ref' => array('type' => 'varchar(32)', 'label' => 'Ref', 'enabled' => '1', 'position' => 10, 'notnull' => 1, 'visible' => '1', 'index' => '1', 'searchall' => '1'),
		'situation_cycle_ref' => array('type' => 'integer', 'label' => 'SituationCycleRef', 'enabled' => '1', 'position' => 20, 'notnull' => -1, 'visible' => '1', 'index' => '1'),
		'fk_projet' => array('type' => 'integer:Project:projet/class/project.class.php:1', 'label' => 'Project', 'enabled' => 'isModEnabled("project")', 'position' => 30, 'notnull' => -1, 'visible' => '1', 'index' => '1'),
		'fk_soc' => array('type' => 'integer:Societe:societe/class/societe.class.php:1', 'label' => 'ThirdParty', 'enabled' => 'isModEnabled("societe")', 'position' => 40, 'notnull' => -1, 'visible' => '1', 'index' => '1'),
		'date_reception' => array('type' => 'date', 'label' => 'RGWReceptionDate', 'enabled' => '1', 'position' => 50, 'notnull' => -1, 'visible' => '1'),
		'date_limit' => array('type' => 'date', 'label' => 'RGWLimitDate', 'enabled' => '1', 'position' => 60, 'notnull' => -1, 'visible' => '1'),
		'status' => array('type' => 'integer', 'label' => 'Status', 'enabled' => '1', 'position' => 70, 'notnull' => 1, 'visible' => '1', 'index' => '1'),
		'note_private' => array('type' => 'text', 'label' => 'NotePrivate', 'enabled' => '1', 'position' => 80, 'notnull' => -1, 'visible' => '0'),
		'fk_user_author' => array('type' => 'integer', 'label' => 'UserAuthor', 'enabled' => '1', 'position' => 90, 'notnull' => -1, 'visible' => '0'),
		'fk_user_modif' => array('type' => 'integer', 'label' => 'UserModif', 'enabled' => '1', 'position' => 91, 'notnull' => -1, 'visible' => '0'),
		'datec' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => '1', 'position' => 92, 'notnull' => -1, 'visible' => '0'),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => '1', 'position' => 93, 'notnull' => -1, 'visible' => '0'),
	);

	public $rowid;
	public $entity;
	public $ref;
	public $situation_cycle_ref;
	public $fk_projet;
	public $fk_soc;
	public $date_reception;
	public $date_limit;
	public $status;
	public $note_private;
	public $fk_user_author;
	public $fk_user_modif;
	public $datec;
	public $tms;

	/**
	 * Constructor
	 *
	 * @param	DoliDB	$db	Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Fetch cycle.
	 *
	 * @param	int			$id		Object id
	 * @param	string|null	$ref	Object ref
	 * @return	int					>0 if ok
	 */
	public function fetch($id, $ref = null)
	{
		global $conf;

		// EN: Build SQL to fetch cycle by id or ref
		// FR: Construire la requête pour charger un cycle par id ou ref
		$sql = "SELECT rowid, entity, ref, situation_cycle_ref, fk_projet, fk_soc, date_reception, date_limit, status,";
		$sql .= " note_private, fk_user_author, fk_user_modif, datec, tms";
		$sql .= " FROM ".$this->db->prefix()."rgw_cycle";
		$sql .= " WHERE entity = ".((int) $conf->entity);
		if ($id > 0) {
			$sql .= " AND rowid = ".((int) $id);
		} elseif (!empty($ref)) {
			$sql .= " AND ref = '".$this->db->escape($ref)."'";
		} else {
			return 0;
		}

		$resql = $this->db->query($sql);
		if ($resql) {
			if ($obj = $this->db->fetch_object($resql)) {
				// EN: Populate object properties
				// FR: Renseigner les propriétés de l'objet
				$this->id = (int) $obj->rowid;
				$this->rowid = (int) $obj->rowid;
				$this->entity = (int) $obj->entity;
				$this->ref = $obj->ref;
				$this->situation_cycle_ref = (int) $obj->situation_cycle_ref;
				$this->fk_projet = (int) $obj->fk_projet;
				$this->fk_soc = (int) $obj->fk_soc;
				$this->date_reception = $this->db->jdate($obj->date_reception);
				$this->date_limit = $this->db->jdate($obj->date_limit);
				$this->status = (int) $obj->status;
				$this->note_private = $obj->note_private;
				$this->fk_user_author = (int) $obj->fk_user_author;
				$this->fk_user_modif = (int) $obj->fk_user_modif;
				$this->datec = $this->db->jdate($obj->datec);
				$this->tms = $this->db->jdate($obj->tms);

				return 1;
			}
			return 0;
		}

		$this->error = $this->db->lasterror();
		return -1;
	}

	/**
	 * Update cycle.
	 *
	 * @param	User	$user	User making change
	 * @return	int				>0 if ok
	 */
	public function update($user)
	{
		global $conf;

		if (empty($this->id)) {
			return -1;
		}

		// EN: Update cycle data
		// FR: Mettre à jour les données du cycle
		$sql = "UPDATE ".$this->db->prefix()."rgw_cycle";
		$sql .= " SET date_reception = ".(empty($this->date_reception) ? "NULL" : "'".$this->db->idate($this->date_reception)."'");
		$sql .= ", date_limit = ".(empty($this->date_limit) ? "NULL" : "'".$this->db->idate($this->date_limit)."'");
		$sql .= ", status = ".((int) $this->status);
		$sql .= ", note_private = ".(empty($this->note_private) ? "NULL" : "'".$this->db->escape($this->note_private)."'");
		$sql .= ", fk_user_modif = ".((int) $user->id);
		$sql .= ", tms = '".$this->db->idate(dol_now())."'";
		$sql .= " WHERE rowid = ".((int) $this->id);
		$sql .= " AND entity = ".((int) $conf->entity);

		if ($this->db->query($sql)) {
			return 1;
		}

		$this->error = $this->db->lasterror();
		return -1;
	}

	/**
	 * Generate document from model.
	 *
	 * @param	string		$model		Model name
	 * @param	Translate	$outputlangs	Output langs
	 * @param	int			$hidedetails	Hide details
	 * @param	int			$hidedesc	Hide desc
	 * @param	int			$hideref		Hide ref
	 * @return	int						>0 if ok
	 */
	public function generateDocument($model, $outputlangs, $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		global $langs;

		if (empty($outputlangs)) {
			$outputlangs = $langs;
		}

		// EN: Secure model name and include model class
		// FR: Sécuriser le nom du modèle et inclure la classe du modèle
		$model = preg_replace('/[^a-z0-9_]/i', '', $model);
		if (empty($model)) {
			$model = 'rgrequest';
		}

		$modelpath = dol_buildpath('/custom/rgwarranty/core/modules/rgwarranty/doc/pdf_'.$model.'.modules.php', 0);
		if (!is_file($modelpath)) {
			$this->error = $outputlangs->trans('ErrorFileDoesNotExists', $modelpath);
			return -1;
		}

		require_once $modelpath;

		$classname = 'pdf_'.$model;
		if (!class_exists($classname)) {
			$this->error = $outputlangs->trans('ErrorFailedToLoadTemplate');
			return -1;
		}

		$docmodel = new $classname($this->db);
		if (!method_exists($docmodel, 'write_file')) {
			$this->error = $outputlangs->trans('ErrorFailedToLoadTemplate');
			return -1;
		}

		return $docmodel->write_file($this, $outputlangs, '', $hidedetails, $hidedesc, $hideref);
	}

	/**
	 * Fetch cycle by situation ref.
	 *
	 * @param	int	$situationRef	Situation cycle ref
	 * @param	int	$entity			Entity id
	 * @return	int						>0 if ok
	 */
	public function fetchBySituationRef($situationRef, $entity)
	{
		// EN: Fetch by situation cycle ref and entity
		// FR: Charger par situation_cycle_ref et entity
		$sql = "SELECT rowid FROM ".$this->db->prefix()."rgw_cycle";
		$sql .= " WHERE entity = ".((int) $entity);
		$sql .= " AND situation_cycle_ref = ".((int) $situationRef);
		$resql = $this->db->query($sql);
		if ($resql) {
			if ($obj = $this->db->fetch_object($resql)) {
				return $this->fetch($obj->rowid);
			}
			return 0;
		}
		$this->error = $this->db->lasterror();
		return -1;
	}

	/**
	 * Get URL.
	 *
	 * @param	int		$withpicto	With picto
	 * @param	string	$option		Option
	 * @return	string					Link
	 */
	public function getNomUrl($withpicto = 0, $option = '')
	{
		global $langs;

		$label = $langs->trans("RGWCycle").': '.$this->ref;
		$url = dol_buildpath('/rgwarranty/rg/cycle_card.php', 1).'?id='.$this->id;
		$link = '<a href="'.$url.'" title="'.dol_escape_htmltag($label).'" class="classfortooltip">'.dol_escape_htmltag($this->ref).'</a>';
		if ($withpicto) {
			$picto = img_picto($label, 'invoicing', 'class="paddingright"');
			return $picto.$link;
		}
		return $link;
	}

	/**
	 * Update status based on totals.
	 *
	 * @param	float	$remaining	Remaining amount
	 * @return	int						>0 if ok
	 */
	public function updateStatusFromTotals($remaining)
	{
		global $user;

		// EN: Decide status from remaining amount
		// FR: Déterminer le statut à partir du reste à payer
		if ($remaining <= 0 && $this->status != self::STATUS_REFUNDED) {
			$this->status = self::STATUS_REFUNDED;
		} elseif ($remaining > 0 && $this->status == self::STATUS_REQUESTED) {
			$this->status = self::STATUS_PARTIAL;
		}
		$this->fk_user_modif = $user->id;
		return $this->update($user);
	}

	/**
	 * Cron to update status based on dates.
	 *
	 * @param	array	$parameters	Parameters
	 * @return	int					>0 if ok
	 */
	public function runCronStatusUpdate($parameters = array())
	{
		global $conf, $langs, $user;

		// EN: Update cycles close to limit date
		// FR: Mettre à jour les cycles proches de l'échéance
		$delay = getDolGlobalInt('RGWARRANTY_DELAY_DAYS', 365);
		$remind = getDolGlobalInt('RGWARRANTY_REMIND_BEFORE_DAYS', 30);
		$limitdate = dol_time_plus_duree(dol_now(), $remind, 'd');

		$sql = "SELECT rowid, date_reception, date_limit, status";
		$sql .= " FROM ".$this->db->prefix()."rgw_cycle";
		$sql .= " WHERE entity = ".((int) $conf->entity);
		$sql .= " AND date_reception IS NOT NULL";
		$sql .= " AND status IN (".self::STATUS_IN_PROGRESS.", ".self::STATUS_DRAFT.", ".self::STATUS_TO_REQUEST.")";

		$resql = $this->db->query($sql);
		if (!$resql) {
			return -1;
		}

		while ($obj = $this->db->fetch_object($resql)) {
			$date_limit = $obj->date_limit ? $this->db->jdate($obj->date_limit) : 0;
			if (empty($date_limit)) {
				$date_limit = dol_time_plus_duree($this->db->jdate($obj->date_reception), $delay, 'd');
				$sqlupdate = "UPDATE ".$this->db->prefix()."rgw_cycle";
				$sqlupdate .= " SET date_limit = '".$this->db->idate($date_limit)."'";
				$sqlupdate .= " WHERE rowid = ".((int) $obj->rowid);
				$this->db->query($sqlupdate);
			}
			if ($date_limit && $date_limit <= $limitdate && $obj->status != self::STATUS_TO_REQUEST) {
				$sqlupdate = "UPDATE ".$this->db->prefix()."rgw_cycle";
				$sqlupdate .= " SET status = ".self::STATUS_TO_REQUEST;
				$sqlupdate .= " WHERE rowid = ".((int) $obj->rowid);
				if ($this->db->query($sqlupdate)) {
					require_once __DIR__.'/../lib/rgwarranty.lib.php';
					rgwarranty_log_event($this->db, $conf->entity, $obj->rowid, 'RG_STATUS_AUTO', $langs->trans('RGWStatusToRequest'), $user);
				}
			}
		}

		return 1;
	}
}
