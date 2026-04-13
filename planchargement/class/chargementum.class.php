<?php
/* Copyright (C) 2024 Patochiz
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    class/chargementum.class.php
 * \ingroup planchargement
 * \brief   CRUD class for handling unit instances (llx_planchargement_um)
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class ChargementUm
 * Handling unit instance within a loading plan
 */
class ChargementUm extends CommonObject
{
	/** @var string Module name */
	public $module = 'planchargement';

	/** @var string Element identifier */
	public $element = 'planchargement_um';

	/** @var string Table element name */
	public $table_element = 'planchargement_um';

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
		'fk_chargement' => array(
			'type' => 'integer:Chargement:planchargement/class/chargement.class.php',
			'label' => 'PlanchargementChargement',
			'visible' => 1,
			'enabled' => 1,
			'notnull' => 1,
			'position' => 10,
		),
		'fk_um_type' => array(
			'type' => 'integer:UmType:planchargement/class/umtype.class.php',
			'label' => 'PlanchargementUmType',
			'visible' => 1,
			'enabled' => 1,
			'notnull' => 1,
			'position' => 20,
		),
		'ref_um' => array(
			'type' => 'varchar(32)',
			'label' => 'Ref',
			'visible' => 1,
			'enabled' => 1,
			'notnull' => 0,
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
		),
		'pos_x' => array(
			'type' => 'integer',
			'label' => 'PlanchargementPosX',
			'visible' => -1,
			'enabled' => 1,
			'notnull' => 0,
			'position' => 50,
		),
		'pos_y' => array(
			'type' => 'integer',
			'label' => 'PlanchargementPosY',
			'visible' => -1,
			'enabled' => 1,
			'notnull' => 0,
			'position' => 60,
		),
		'rotation' => array(
			'type' => 'smallint',
			'label' => 'PlanchargementRotation',
			'visible' => -1,
			'enabled' => 1,
			'notnull' => 1,
			'default' => '0',
			'position' => 70,
		),
		'fk_um_parent' => array(
			'type' => 'integer:ChargementUm:planchargement/class/chargementum.class.php',
			'label' => 'PlanchargementUmParent',
			'visible' => -1,
			'enabled' => 1,
			'notnull' => 0,
			'position' => 80,
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
	/** @var int FK to chargement */
	public $fk_chargement;
	/** @var int FK to um_type */
	public $fk_um_type;
	/** @var string Reference within the loading plan */
	public $ref_um;
	/** @var float Total weight in kg */
	public $poids_total;
	/** @var int|null X position in mm from tablier */
	public $pos_x;
	/** @var int|null Y position in mm from left edge */
	public $pos_y;
	/** @var int Rotation 0 or 90 */
	public $rotation;
	/** @var int|null FK to parent UM for stacking */
	public $fk_um_parent;
	/** @var string */
	public $tms;

	/** @var array Assigned colis data */
	public $colis = array();

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		$this->db = $db;
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
		return $this->createCommon($user, $notrigger);
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
		return $this->fetchCommon($id, $ref);
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
		return $this->updateCommon($user, $notrigger);
	}

	/**
	 * Find another placed UM in the same chargement that overlaps the given
	 * axis-aligned rectangle (mm). Returns the conflicting rowid or 0 if no
	 * conflict. UMs stacked via fk_um_parent share the same footprint as
	 * their parent and are excluded from the check.
	 *
	 * @param  DoliDB $db             Database handler
	 * @param  int    $fk_chargement  Loading plan id
	 * @param  int    $exclude_fk_um  UM id to exclude (the one being moved)
	 * @param  int    $pos_x          New X (mm)
	 * @param  int    $pos_y          New Y (mm)
	 * @param  int    $um_len         UM length on the X axis (mm), already
	 *                                rotation-adjusted by the caller
	 * @param  int    $um_wid         UM width on the Y axis (mm), already
	 *                                rotation-adjusted by the caller
	 * @return int                    rowid of the conflicting UM, or 0
	 */
	public static function findOverlap(DoliDB $db, $fk_chargement, $exclude_fk_um, $pos_x, $pos_y, $um_len, $um_wid)
	{
		$sql  = "SELECT u.rowid, u.pos_x, u.pos_y, u.rotation, u.fk_um_parent,";
		$sql .= " t.longueur, t.largeur";
		$sql .= " FROM ".MAIN_DB_PREFIX."planchargement_um u";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."planchargement_um_type t ON t.rowid = u.fk_um_type";
		$sql .= " WHERE u.fk_chargement = ".((int) $fk_chargement);
		$sql .= " AND u.rowid <> ".((int) $exclude_fk_um);
		$sql .= " AND u.pos_x IS NOT NULL AND u.pos_y IS NOT NULL";
		$sql .= " AND (u.fk_um_parent IS NULL OR u.fk_um_parent = 0)";

		$resql = $db->query($sql);
		if (!$resql) {
			return 0;
		}

		while ($obj = $db->fetch_object($resql)) {
			$rot = (int) $obj->rotation;
			$ox  = (int) $obj->pos_x;
			$oy  = (int) $obj->pos_y;
			$ow  = ($rot === 90) ? (int) $obj->largeur  : (int) $obj->longueur;
			$oh  = ($rot === 90) ? (int) $obj->longueur : (int) $obj->largeur;

			// Axis-aligned rectangle intersection
			if ($pos_x < $ox + $ow
				&& $pos_x + $um_len > $ox
				&& $pos_y < $oy + $oh
				&& $pos_y + $um_wid > $oy
			) {
				$db->free($resql);
				return (int) $obj->rowid;
			}
		}
		$db->free($resql);
		return 0;
	}

	/**
	 * Delete object in database
	 *
	 * @param  User $user      User that deletes
	 * @param  int  $notrigger 0=launch triggers, 1=disable triggers
	 * @return int             >0 if OK, <0 if KO
	 */
	public function delete(User $user, $notrigger = 0)
	{
		$this->db->begin();

		$error = 0;

		// Delete colis assignments
		$sql = "DELETE FROM ".MAIN_DB_PREFIX."planchargement_um_colis";
		$sql .= " WHERE fk_um = ".((int) $this->id);
		if (!$this->db->query($sql)) {
			$error++;
			$this->errors[] = $this->db->lasterror();
		}

		// Nullify fk_um_parent on stacked UMs
		if (!$error) {
			$sql = "UPDATE ".MAIN_DB_PREFIX."planchargement_um";
			$sql .= " SET fk_um_parent = NULL";
			$sql .= " WHERE fk_um_parent = ".((int) $this->id);
			if (!$this->db->query($sql)) {
				$error++;
				$this->errors[] = $this->db->lasterror();
			}
		}

		// Delete the UM itself
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
	 * Assign a package (colis) to this UM
	 *
	 * @param  int $fk_package Package rowid from colisage module
	 * @param  int $quantity   Number of physical packages to assign
	 * @return int             >0 if OK, <0 if KO
	 */
	public function assignColis($fk_package, $quantity = 1)
	{
		$fk_package = (int) $fk_package;
		$quantity = (int) $quantity;

		if ($fk_package <= 0 || $quantity <= 0) {
			$this->error = 'ErrorBadParameter';
			return -1;
		}

		// Verify that the package belongs to a commande linked to this chargement
		$sql = "SELECT p.rowid, p.multiplier";
		$sql .= " FROM ".MAIN_DB_PREFIX."colisage_packages p";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."planchargement_commande pc ON pc.fk_commande = p.fk_commande";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."planchargement_um um ON um.fk_chargement = pc.fk_chargement";
		$sql .= " WHERE um.rowid = ".((int) $this->id);
		$sql .= " AND p.rowid = ".$fk_package;

		$resql = $this->db->query($sql);
		if (!$resql || $this->db->num_rows($resql) == 0) {
			$this->error = 'ErrorBadParameter';
			return -1;
		}
		$pkg = $this->db->fetch_object($resql);
		$multiplier = (int) $pkg->multiplier;

		// Check total assigned quantity across all UMs in this chargement does not exceed multiplier
		$sql = "SELECT COALESCE(SUM(umc.quantity), 0) as total_assigned";
		$sql .= " FROM ".MAIN_DB_PREFIX."planchargement_um_colis umc";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."planchargement_um um ON um.rowid = umc.fk_um";
		$sql .= " WHERE um.fk_chargement = ".((int) $this->fk_chargement);
		$sql .= " AND umc.fk_package = ".$fk_package;
		$sql .= " AND umc.fk_um <> ".((int) $this->id);

		$resql = $this->db->query($sql);
		$obj = $this->db->fetch_object($resql);
		$already_assigned = (int) $obj->total_assigned;

		// Also check if there's already an assignment on this UM for this package
		$sql_existing = "SELECT rowid, quantity FROM ".MAIN_DB_PREFIX."planchargement_um_colis";
		$sql_existing .= " WHERE fk_um = ".((int) $this->id);
		$sql_existing .= " AND fk_package = ".$fk_package;
		$resql_existing = $this->db->query($sql_existing);
		$existing_qty = 0;
		$existing_rowid = 0;
		if ($resql_existing && $this->db->num_rows($resql_existing) > 0) {
			$obj_existing = $this->db->fetch_object($resql_existing);
			$existing_qty = (int) $obj_existing->quantity;
			$existing_rowid = (int) $obj_existing->rowid;
		}

		// Total assigned across the whole chargement after this operation.
		// $already_assigned counts OTHER UMs only (see SQL above), so we must
		// also include the qty already on THIS UM ($existing_qty).
		$new_total = $already_assigned + $existing_qty + $quantity;
		if ($new_total > $multiplier) {
			$this->error = 'PlanchargementErrorQuantityExceedsMultiplier';
			return -1;
		}

		if ($existing_rowid > 0) {
			// Increment the existing assignment (additive: dragging the same
			// package twice with qty=1 must give qty=2, not qty=1).
			$new_qty = $existing_qty + $quantity;
			$sql = "UPDATE ".MAIN_DB_PREFIX."planchargement_um_colis";
			$sql .= " SET quantity = ".((int) $new_qty);
			$sql .= " WHERE rowid = ".$existing_rowid;
		} else {
			// Insert new assignment
			$sql = "INSERT INTO ".MAIN_DB_PREFIX."planchargement_um_colis";
			$sql .= " (fk_um, fk_package, quantity)";
			$sql .= " VALUES (".((int) $this->id).", ".$fk_package.", ".$quantity.")";
		}

		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		// Recalculate weight
		$this->calculatePoids();

		return 1;
	}

	/**
	 * Remove a package assignment from this UM
	 *
	 * @param  int $fk_package Package rowid
	 * @return int             >0 if OK, <0 if KO
	 */
	public function removeColis($fk_package)
	{
		$fk_package = (int) $fk_package;

		$sql = "DELETE FROM ".MAIN_DB_PREFIX."planchargement_um_colis";
		$sql .= " WHERE fk_um = ".((int) $this->id);
		$sql .= " AND fk_package = ".$fk_package;

		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		// Recalculate weight
		$this->calculatePoids();

		return 1;
	}

	/**
	 * Calculate total weight of this UM from assigned packages
	 * Weight per unit = total_weight / multiplier, then * quantity assigned
	 *
	 * @return int >0 if OK, <0 if KO
	 */
	public function calculatePoids()
	{
		$sql = "SELECT COALESCE(SUM(p.total_weight * umc.quantity / p.multiplier), 0) as poids";
		$sql .= " FROM ".MAIN_DB_PREFIX."planchargement_um_colis umc";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."colisage_packages p ON p.rowid = umc.fk_package";
		$sql .= " WHERE umc.fk_um = ".((int) $this->id);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$obj = $this->db->fetch_object($resql);
		$this->poids_total = (float) $obj->poids;

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
	 * Fetch all assigned colis for this UM
	 *
	 * @return int >0 if OK, <0 if KO
	 */
	public function fetchColis()
	{
		$this->colis = array();

		$sql = "SELECT umc.rowid, umc.fk_package, umc.quantity,";
		$sql .= " p.fk_commande, p.multiplier, p.total_weight, p.total_surface";
		$sql .= " FROM ".MAIN_DB_PREFIX."planchargement_um_colis umc";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."colisage_packages p ON p.rowid = umc.fk_package";
		$sql .= " WHERE umc.fk_um = ".((int) $this->id);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$num = $this->db->num_rows($resql);
		for ($i = 0; $i < $num; $i++) {
			$this->colis[] = $this->db->fetch_object($resql);
		}
		$this->db->free($resql);

		return 1;
	}

	/**
	 * Fetch all UMs for a given chargement
	 *
	 * @param  int      $fk_chargement Chargement rowid
	 * @return array|int               Array of ChargementUm objects or <0 if error
	 */
	public function fetchByChargement($fk_chargement)
	{
		return $this->fetchAll('', 'rowid', 0, 0, 'fk_chargement = '.((int) $fk_chargement));
	}
}
