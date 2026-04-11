<?php
/* Copyright (C) 2024 Patochiz
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    class/chargement.class.php
 * \ingroup planchargement
 * \brief   CRUD class for loading plans (llx_planchargement_chargement)
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class Chargement
 * Loading plan - central object of the module
 */
class Chargement extends CommonObject
{
	const STATUS_DRAFT = 0;
	const STATUS_VALID = 1;
	const STATUS_DEPARTED = 2;
	const STATUS_CANCELLED = 9;

	/** @var string Module name */
	public $module = 'planchargement';

	/** @var string Element identifier */
	public $element = 'planchargement_chargement';

	/** @var string Table element name */
	public $table_element = 'planchargement_chargement';

	/** @var string Picto */
	public $picto = 'generic';

	/**
	 * @var array Field definitions for CommonObject generic methods
	 */
	public $fields = array(
		'rowid' => array(
			'type' => 'integer',
			'label' => 'TechnicalID',
			'visible' => -2,
			'enabled' => 1,
			'notnull' => 1,
			'position' => 1,
			'index' => 1,
		),
		'ref' => array(
			'type' => 'varchar(32)',
			'label' => 'Ref',
			'visible' => 4,
			'enabled' => 1,
			'notnull' => 1,
			'default' => '(PROV)',
			'position' => 10,
			'index' => 1,
			'searchall' => 1,
			'showoncombobox' => 1,
			'noteditable' => 1,
			'csslist' => 'nowraponall',
		),
		'date_chargement' => array(
			'type' => 'date',
			'label' => 'PlanchargementDateChargement',
			'visible' => 1,
			'enabled' => 1,
			'notnull' => 0,
			'position' => 20,
		),
		'fk_camion_type' => array(
			'type' => 'integer:CamionType:planchargement/class/camiontype.class.php',
			'label' => 'PlanchargementCamionType',
			'visible' => 1,
			'enabled' => 1,
			'notnull' => 1,
			'position' => 30,
		),
		'poids_total' => array(
			'type' => 'real',
			'label' => 'PlanchargementPoidsTotal',
			'visible' => 1,
			'enabled' => 1,
			'notnull' => 0,
			'default' => '0',
			'position' => 40,
			'noteditable' => 1,
		),
		'note_public' => array(
			'type' => 'html',
			'label' => 'NotePublic',
			'visible' => -2,
			'enabled' => 1,
			'notnull' => -1,
			'position' => 161,
		),
		'note_private' => array(
			'type' => 'html',
			'label' => 'NotePrivate',
			'visible' => -2,
			'enabled' => 1,
			'notnull' => -1,
			'position' => 162,
		),
		'statut' => array(
			'type' => 'smallint',
			'label' => 'Status',
			'visible' => 2,
			'enabled' => 1,
			'notnull' => 1,
			'default' => '0',
			'position' => 500,
			'index' => 1,
			'arrayofkeyval' => array(
				0 => 'PlanchargementStatusDraft',
				1 => 'PlanchargementStatusValid',
				2 => 'PlanchargementStatusDeparted',
				9 => 'PlanchargementStatusCancelled',
			),
		),
		'fk_user_creat' => array(
			'type' => 'integer:User:user/class/user.class.php',
			'label' => 'UserCreation',
			'visible' => -2,
			'enabled' => 1,
			'notnull' => 1,
			'position' => 510,
			'foreignkey' => 'user.rowid',
		),
		'fk_user_modif' => array(
			'type' => 'integer:User:user/class/user.class.php',
			'label' => 'UserModif',
			'visible' => -2,
			'enabled' => 1,
			'notnull' => -1,
			'position' => 511,
		),
		'date_creation' => array(
			'type' => 'datetime',
			'label' => 'DateCreation',
			'visible' => -2,
			'enabled' => 1,
			'notnull' => 1,
			'position' => 300,
		),
		'tms' => array(
			'type' => 'timestamp',
			'label' => 'DateModification',
			'visible' => -2,
			'enabled' => 1,
			'notnull' => 1,
			'position' => 900,
		),
	);

	/** @var int */
	public $rowid;
	/** @var string */
	public $ref;
	/** @var string */
	public $date_chargement;
	/** @var int FK to camion_type */
	public $fk_camion_type;
	/** @var float Total weight in kg */
	public $poids_total;
	/** @var string */
	public $note_public;
	/** @var string */
	public $note_private;
	/** @var int Status (0=draft, 1=valid, 2=departed, 9=cancelled) */
	public $statut;
	/** @var int FK to user creator */
	public $fk_user_creat;
	/** @var int FK to user modifier */
	public $fk_user_modif;
	/** @var string */
	public $date_creation;
	/** @var string */
	public $tms;

	/** @var array Linked commande IDs */
	public $commandes = array();

	/** @var array ChargementUm objects */
	public $lines = array();

	/**
	 * Status labels for display
	 * @var array
	 */
	public $labelStatus = array();

	/**
	 * Status short labels
	 * @var array
	 */
	public $labelStatusShort = array();

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		global $langs;

		$this->db = $db;

		if (is_object($langs)) {
			foreach ($this->fields as $key => $val) {
				if (!empty($val['arrayofkeyval']) && is_array($val['arrayofkeyval'])) {
					foreach ($val['arrayofkeyval'] as $key2 => $val2) {
						$this->fields[$key]['arrayofkeyval'][$key2] = $langs->trans($val2);
					}
				}
			}
		}

		$this->labelStatus[self::STATUS_DRAFT] = 'PlanchargementStatusDraft';
		$this->labelStatus[self::STATUS_VALID] = 'PlanchargementStatusValid';
		$this->labelStatus[self::STATUS_DEPARTED] = 'PlanchargementStatusDeparted';
		$this->labelStatus[self::STATUS_CANCELLED] = 'PlanchargementStatusCancelled';

		$this->labelStatusShort[self::STATUS_DRAFT] = 'PlanchargementStatusDraft';
		$this->labelStatusShort[self::STATUS_VALID] = 'PlanchargementStatusValid';
		$this->labelStatusShort[self::STATUS_DEPARTED] = 'PlanchargementStatusDeparted';
		$this->labelStatusShort[self::STATUS_CANCELLED] = 'PlanchargementStatusCancelled';
	}

	/**
	 * Create object in database
	 *
	 * @param  User $user      User that creates
	 * @param  int  $notrigger 0=launch triggers, 1=disable triggers
	 * @return int             >0 if OK, <0 if KO
	 */
	public function create(User $user, $notrigger = 0)
	{
		$this->ref = '(PROV)';
		$this->statut = self::STATUS_DRAFT;
		$this->date_creation = dol_now();
		$this->fk_user_creat = $user->id;

		$result = $this->createCommon($user, $notrigger);

		if ($result > 0) {
			// Update ref with (PROV<id>) pattern
			$sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
			$sql .= " SET ref = '(PROV".$this->id.")'";
			$sql .= " WHERE rowid = ".((int) $this->id);
			$this->db->query($sql);
			$this->ref = '(PROV'.$this->id.')';
		}

		return $result;
	}

	/**
	 * Load object in memory from the database
	 *
	 * @param  int    $id  Id object
	 * @param  string $ref Ref
	 * @return int         >0 if OK, 0 if not found, <0 if KO
	 */
	public function fetch($id, $ref = null)
	{
		$result = $this->fetchCommon($id, $ref);
		if ($result > 0) {
			$this->fetchCommandes();
		}
		return $result;
	}

	/**
	 * Load list of objects in memory from the database
	 *
	 * @param  string      $sortorder  Sort order
	 * @param  string      $sortfield  Sort field
	 * @param  int         $limit      Limit
	 * @param  int         $offset     Offset
	 * @param  string      $filter     Filter as a SQL string
	 * @param  string      $filtermode AND or OR
	 * @return array|int               Array of objects or <0 if error
	 */
	public function fetchAll($sortorder = '', $sortfield = '', $limit = 0, $offset = 0, $filter = '', $filtermode = 'AND')
	{
		$records = array();

		$sql = "SELECT rowid";
		$sql .= " FROM ".MAIN_DB_PREFIX.$this->table_element;
		if ($filter) {
			$sql .= " WHERE ".$filter;
		}
		if ($sortfield) {
			$sql .= $this->db->order($sortfield, $sortorder);
		}
		if ($limit) {
			$sql .= $this->db->plimit($limit, $offset);
		}

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->errors[] = $this->db->lasterror();
			return -1;
		}

		$num = $this->db->num_rows($resql);
		for ($i = 0; $i < $num; $i++) {
			$obj = $this->db->fetch_object($resql);
			$record = new self($this->db);
			$record->fetch($obj->rowid);
			$records[$obj->rowid] = $record;
		}
		$this->db->free($resql);

		return $records;
	}

	/**
	 * Update object in database
	 *
	 * @param  User $user      User that modifies
	 * @param  int  $notrigger 0=launch triggers, 1=disable triggers
	 * @return int             >0 if OK, <0 if KO
	 */
	public function update(User $user, $notrigger = 0)
	{
		$this->fk_user_modif = $user->id;
		return $this->updateCommon($user, $notrigger);
	}

	/**
	 * Delete object in database with cascade
	 *
	 * @param  User $user      User that deletes
	 * @param  int  $notrigger 0=launch triggers, 1=disable triggers
	 * @return int             >0 if OK, <0 if KO
	 */
	public function delete(User $user, $notrigger = 0)
	{
		if ($this->statut != self::STATUS_DRAFT) {
			$this->error = 'PlanchargementErrorCannotDeleteValid';
			return -1;
		}

		$this->db->begin();

		$error = 0;

		// Delete um_colis for all UMs of this chargement
		$sql = "DELETE FROM ".MAIN_DB_PREFIX."planchargement_um_colis";
		$sql .= " WHERE fk_um IN (";
		$sql .= "   SELECT rowid FROM ".MAIN_DB_PREFIX."planchargement_um";
		$sql .= "   WHERE fk_chargement = ".((int) $this->id);
		$sql .= " )";
		if (!$this->db->query($sql)) {
			$error++;
			$this->errors[] = $this->db->lasterror();
		}

		// Delete UMs
		if (!$error) {
			$sql = "DELETE FROM ".MAIN_DB_PREFIX."planchargement_um";
			$sql .= " WHERE fk_chargement = ".((int) $this->id);
			if (!$this->db->query($sql)) {
				$error++;
				$this->errors[] = $this->db->lasterror();
			}
		}

		// Delete commande links
		if (!$error) {
			$sql = "DELETE FROM ".MAIN_DB_PREFIX."planchargement_commande";
			$sql .= " WHERE fk_chargement = ".((int) $this->id);
			if (!$this->db->query($sql)) {
				$error++;
				$this->errors[] = $this->db->lasterror();
			}
		}

		// Delete the chargement itself
		if (!$error) {
			$result = $this->deleteCommon($user, $notrigger);
			if ($result < 0) {
				$error++;
			}
		}

		if ($error) {
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();
		return 1;
	}

	// ===================================================================
	// Business methods
	// ===================================================================

	/**
	 * Link a commande to this loading plan
	 *
	 * @param  int $fk_commande Commande rowid
	 * @return int              >0 if OK, <0 if KO
	 */
	public function addCommande($fk_commande)
	{
		if ($this->statut != self::STATUS_DRAFT) {
			$this->error = 'PlanchargementErrorAlreadyValid';
			return -1;
		}

		$fk_commande = (int) $fk_commande;
		if ($fk_commande <= 0) {
			$this->error = 'ErrorBadParameter';
			return -1;
		}

		// Check if already linked
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."planchargement_commande";
		$sql .= " WHERE fk_chargement = ".((int) $this->id);
		$sql .= " AND fk_commande = ".$fk_commande;
		$resql = $this->db->query($sql);
		if ($resql && $this->db->num_rows($resql) > 0) {
			$this->error = 'PlanchargementErrorCommandeAlreadyLinked';
			return -1;
		}

		$sql = "INSERT INTO ".MAIN_DB_PREFIX."planchargement_commande";
		$sql .= " (fk_chargement, fk_commande)";
		$sql .= " VALUES (".((int) $this->id).", ".$fk_commande.")";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$this->fetchCommandes();
		return 1;
	}

	/**
	 * Unlink a commande from this loading plan
	 *
	 * @param  int $fk_commande Commande rowid
	 * @return int              >0 if OK, <0 if KO
	 */
	public function removeCommande($fk_commande)
	{
		if ($this->statut != self::STATUS_DRAFT) {
			$this->error = 'PlanchargementErrorAlreadyValid';
			return -1;
		}

		$fk_commande = (int) $fk_commande;

		// Check if colis from this commande are assigned to UMs in this chargement
		$sql = "SELECT COUNT(umc.rowid) as nb";
		$sql .= " FROM ".MAIN_DB_PREFIX."planchargement_um_colis umc";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."colisage_packages p ON p.rowid = umc.fk_package";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."planchargement_um um ON um.rowid = umc.fk_um";
		$sql .= " WHERE um.fk_chargement = ".((int) $this->id);
		$sql .= " AND p.fk_commande = ".$fk_commande;
		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			if ($obj->nb > 0) {
				$this->error = 'PlanchargementErrorColisStillAssigned';
				return -1;
			}
		}

		$sql = "DELETE FROM ".MAIN_DB_PREFIX."planchargement_commande";
		$sql .= " WHERE fk_chargement = ".((int) $this->id);
		$sql .= " AND fk_commande = ".$fk_commande;

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$this->fetchCommandes();
		return 1;
	}

	/**
	 * Create a new UM in this loading plan
	 *
	 * @param  int    $fk_um_type UM type rowid
	 * @param  string $ref_um     Optional ref for the UM
	 * @return int                New UM rowid or <0 if KO
	 */
	public function addUm($fk_um_type, $ref_um = '')
	{
		global $user;

		if ($this->statut != self::STATUS_DRAFT) {
			$this->error = 'PlanchargementErrorAlreadyValid';
			return -1;
		}

		dol_include_once('/planchargement/class/chargementum.class.php');

		// Auto-generate ref_um if empty
		if (empty($ref_um)) {
			$sql = "SELECT COUNT(rowid) as nb FROM ".MAIN_DB_PREFIX."planchargement_um";
			$sql .= " WHERE fk_chargement = ".((int) $this->id);
			$resql = $this->db->query($sql);
			$obj = $this->db->fetch_object($resql);
			$num = ((int) $obj->nb) + 1;
			$ref_um = 'UM-'.sprintf('%03d', $num);
		}

		$um = new ChargementUm($this->db);
		$um->fk_chargement = $this->id;
		$um->fk_um_type = (int) $fk_um_type;
		$um->ref_um = $ref_um;
		$um->poids_total = 0;
		$um->rotation = 0;

		$result = $um->create($user);
		if ($result < 0) {
			$this->error = $um->error;
			$this->errors = $um->errors;
			return -1;
		}

		return $um->id;
	}

	/**
	 * Fetch linked commande IDs
	 *
	 * @return int >0 if OK, <0 if KO
	 */
	public function fetchCommandes()
	{
		$this->commandes = array();

		$sql = "SELECT fk_commande FROM ".MAIN_DB_PREFIX."planchargement_commande";
		$sql .= " WHERE fk_chargement = ".((int) $this->id);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$num = $this->db->num_rows($resql);
		for ($i = 0; $i < $num; $i++) {
			$obj = $this->db->fetch_object($resql);
			$this->commandes[] = (int) $obj->fk_commande;
		}
		$this->db->free($resql);

		return 1;
	}

	/**
	 * Fetch all UMs for this loading plan
	 *
	 * @return int >0 if OK, <0 if KO
	 */
	public function fetchUms()
	{
		dol_include_once('/planchargement/class/chargementum.class.php');

		$this->lines = array();

		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."planchargement_um";
		$sql .= " WHERE fk_chargement = ".((int) $this->id);
		$sql .= " ORDER BY rowid ASC";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$num = $this->db->num_rows($resql);
		for ($i = 0; $i < $num; $i++) {
			$obj = $this->db->fetch_object($resql);
			$um = new ChargementUm($this->db);
			$um->fetch($obj->rowid);
			$this->lines[] = $um;
		}
		$this->db->free($resql);

		return 1;
	}

	/**
	 * Get unassigned packages from linked orders
	 * Uses the SQL query from guide section 4.3
	 *
	 * @return array|int Array of package info objects or <0 if error
	 */
	public function getColisNonAffectes()
	{
		$result = array();

		$sql = "SELECT p.rowid AS fk_package,";
		$sql .= " p.fk_commande,";
		$sql .= " p.multiplier,";
		$sql .= " p.total_weight,";
		$sql .= " p.total_surface,";
		$sql .= " COALESCE(SUM(umc.quantity), 0) AS qty_affectee,";
		$sql .= " (p.multiplier - COALESCE(SUM(umc.quantity), 0)) AS qty_restante";
		$sql .= " FROM ".MAIN_DB_PREFIX."colisage_packages p";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."planchargement_commande pc ON pc.fk_commande = p.fk_commande";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."planchargement_um_colis umc";
		$sql .= "   ON umc.fk_package = p.rowid";
		$sql .= "   AND umc.fk_um IN (";
		$sql .= "     SELECT rowid FROM ".MAIN_DB_PREFIX."planchargement_um";
		$sql .= "     WHERE fk_chargement = ".((int) $this->id);
		$sql .= "   )";
		$sql .= " WHERE pc.fk_chargement = ".((int) $this->id);
		$sql .= " GROUP BY p.rowid, p.fk_commande, p.multiplier, p.total_weight, p.total_surface";
		$sql .= " HAVING qty_restante > 0";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$num = $this->db->num_rows($resql);
		for ($i = 0; $i < $num; $i++) {
			$result[] = $this->db->fetch_object($resql);
		}
		$this->db->free($resql);

		return $result;
	}

	/**
	 * Recalculate total weight from all UMs
	 *
	 * @return int >0 if OK, <0 if KO
	 */
	public function calculateTotals()
	{
		$sql = "SELECT COALESCE(SUM(poids_total), 0) as total";
		$sql .= " FROM ".MAIN_DB_PREFIX."planchargement_um";
		$sql .= " WHERE fk_chargement = ".((int) $this->id);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$obj = $this->db->fetch_object($resql);
		$this->poids_total = (float) $obj->total;

		// Persist to database
		$sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
		$sql .= " SET poids_total = ".((float) $this->poids_total);
		$sql .= " WHERE rowid = ".((int) $this->id);

		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		return 1;
	}

	/**
	 * Validate the loading plan (DRAFT -> VALID)
	 *
	 * @param  User $user      User validating
	 * @param  int  $notrigger 0=launch triggers, 1=disable triggers
	 * @return int             >0 if OK, <0 if KO
	 */
	public function valid(User $user, $notrigger = 0)
	{
		if ($this->statut != self::STATUS_DRAFT) {
			$this->error = 'PlanchargementErrorAlreadyValid';
			return -1;
		}

		$this->db->begin();

		$error = 0;

		// Generate definitive ref
		$num = $this->getNextNumRef();
		if (empty($num)) {
			$error++;
			$this->errors[] = 'ErrorGettingNextRef';
		}

		if (!$error) {
			$sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
			$sql .= " SET ref = '".$this->db->escape($num)."',";
			$sql .= " statut = ".self::STATUS_VALID.",";
			$sql .= " fk_user_modif = ".((int) $user->id);
			$sql .= " WHERE rowid = ".((int) $this->id);

			if (!$this->db->query($sql)) {
				$error++;
				$this->errors[] = $this->db->lasterror();
			}
		}

		if (!$error) {
			$this->ref = $num;
			$this->statut = self::STATUS_VALID;
		}

		if ($error) {
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();
		return 1;
	}

	/**
	 * Change status of loading plan
	 * Note: named changeStatus() to avoid conflict with CommonObject::setStatut() signature
	 *
	 * @param  int  $statut    New status
	 * @param  User $user      User making the change
	 * @param  int  $notrigger 0=launch triggers, 1=disable triggers
	 * @return int             >0 if OK, <0 if KO
	 */
	public function changeStatus($statut, User $user, $notrigger = 0)
	{
		// For DRAFT -> VALID, use valid() instead
		if ($this->statut == self::STATUS_DRAFT && $statut == self::STATUS_VALID) {
			return $this->valid($user, $notrigger);
		}

		$sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
		$sql .= " SET statut = ".((int) $statut).",";
		$sql .= " fk_user_modif = ".((int) $user->id);
		$sql .= " WHERE rowid = ".((int) $this->id);

		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$this->statut = (int) $statut;
		return 1;
	}

	/**
	 * Get next reference number
	 *
	 * @return string Next ref or '' if error
	 */
	public function getNextNumRef()
	{
		global $conf;

		$addon = getDolGlobalString('PLANCHARGEMENT_CHARGEMENT_ADDON', 'mod_chargement_standard');

		$classPath = '/planchargement/core/modules/planchargement/'.$addon.'.php';
		if (!dol_include_once($classPath)) {
			$this->error = 'ErrorFailedToLoadNumModule '.$classPath;
			return '';
		}

		$obj = new $addon();
		$numref = $obj->getNextValue($this);

		if (empty($numref)) {
			$this->error = $obj->error;
			return '';
		}

		return $numref;
	}

	/**
	 * Return the label of a given status
	 *
	 * @param  int $mode 0=long label, 1=short label, 2=Picto+short, 3=Picto, 4=Picto+long, 5=Short+Picto, 6=Long+Picto
	 * @return string    Label of status
	 */
	public function getLibStatut($mode = 0)
	{
		return self::LibStatut($this->statut, $mode);
	}

	/**
	 * Return the label for a given status (static)
	 *
	 * @param  int $status Status id
	 * @param  int $mode   0=long label, 1=short label, 2=Picto+short, 3=Picto, 4=Picto+long, 5=Short+Picto, 6=Long+Picto
	 * @return string      Label of status
	 */
	public static function LibStatut($status, $mode = 0)
	{
		global $langs;

		$langs->load('planchargement@planchargement');

		$labelStatus = array();
		$labelStatus[self::STATUS_DRAFT] = $langs->transnoentities('PlanchargementStatusDraft');
		$labelStatus[self::STATUS_VALID] = $langs->transnoentities('PlanchargementStatusValid');
		$labelStatus[self::STATUS_DEPARTED] = $langs->transnoentities('PlanchargementStatusDeparted');
		$labelStatus[self::STATUS_CANCELLED] = $langs->transnoentities('PlanchargementStatusCancelled');

		$labelStatusShort = $labelStatus;

		$statusType = array();
		$statusType[self::STATUS_DRAFT] = 'status0';
		$statusType[self::STATUS_VALID] = 'status4';
		$statusType[self::STATUS_DEPARTED] = 'status6';
		$statusType[self::STATUS_CANCELLED] = 'status9';

		return dolGetStatus($labelStatus[$status], $labelStatusShort[$status], '', $statusType[$status], $mode);
	}

	/**
	 * Return a link to the object card (with optionally the picto)
	 *
	 * @param  int    $withpicto             Include picto in link
	 * @param  string $option                Link variant
	 * @param  int    $notooltip             1=Disable tooltip
	 * @param  string $morecss               Additional CSS
	 * @param  int    $save_lastsearch_value -1=Auto
	 * @return string                        HTML link
	 */
	public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $morecss = '', $save_lastsearch_value = -1)
	{
		global $langs;

		$result = '';
		$label = img_picto('', $this->picto).' <u>'.$langs->trans("PlanchargementChargement").'</u><br>';
		$label .= '<b>'.$langs->trans('Ref').':</b> '.$this->ref;

		$url = dol_buildpath('/planchargement/card.php', 1).'?id='.$this->id;

		$linkclose = '';
		if (!$notooltip) {
			$linkclose .= ' title="'.dol_escape_htmltag($label, 1, 1).'"';
			$linkclose .= ' class="classfortooltip'.($morecss ? ' '.$morecss : '').'"';
		} else {
			$linkclose .= ($morecss ? ' class="'.$morecss.'"' : '');
		}

		$linkstart = '<a href="'.$url.'"'.$linkclose.'>';
		$linkend = '</a>';

		$result .= $linkstart;
		if ($withpicto) {
			$result .= img_object($label, $this->picto, 'class="paddingright"');
		}
		$result .= $this->ref;
		$result .= $linkend;

		return $result;
	}
}
