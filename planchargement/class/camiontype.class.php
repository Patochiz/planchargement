<?php
/* Copyright (C) 2024 Patochiz
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    class/camiontype.class.php
 * \ingroup planchargement
 * \brief   CRUD class for truck type catalog (llx_planchargement_camion_type)
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class CamionType
 * Truck type catalog entry
 */
class CamionType extends CommonObject
{
	/** @var string Module name */
	public $module = 'planchargement';

	/** @var string Element identifier */
	public $element = 'planchargement_camion_type';

	/** @var string Table element name */
	public $table_element = 'planchargement_camion_type';

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
		'label' => array(
			'type' => 'varchar(255)',
			'label' => 'Label',
			'visible' => 1,
			'enabled' => 1,
			'notnull' => 1,
			'position' => 10,
			'searchall' => 1,
			'showoncombobox' => 1,
		),
		'longueur_utile' => array(
			'type' => 'integer',
			'label' => 'PlanchargementLongueurUtile',
			'visible' => 1,
			'enabled' => 1,
			'notnull' => 1,
			'position' => 20,
			'help' => 'mm',
		),
		'largeur_utile' => array(
			'type' => 'integer',
			'label' => 'PlanchargementLargeurUtile',
			'visible' => 1,
			'enabled' => 1,
			'notnull' => 1,
			'position' => 30,
			'help' => 'mm',
		),
		'hauteur_utile' => array(
			'type' => 'integer',
			'label' => 'PlanchargementHauteurUtile',
			'visible' => 1,
			'enabled' => 1,
			'notnull' => 1,
			'position' => 40,
			'help' => 'mm',
		),
		'charge_utile' => array(
			'type' => 'real',
			'label' => 'PlanchargementChargeUtile',
			'visible' => 1,
			'enabled' => 1,
			'notnull' => 1,
			'position' => 50,
			'help' => 'kg',
		),
		'active' => array(
			'type' => 'smallint',
			'label' => 'Status',
			'visible' => 1,
			'enabled' => 1,
			'notnull' => 1,
			'default' => '1',
			'position' => 500,
			'arrayofkeyval' => array(
				0 => 'Disabled',
				1 => 'Enabled',
			),
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
	public $label;
	/** @var int Usable length in mm */
	public $longueur_utile;
	/** @var int Usable width in mm */
	public $largeur_utile;
	/** @var int Usable height in mm */
	public $hauteur_utile;
	/** @var float Payload capacity in kg */
	public $charge_utile;
	/** @var int 0=disabled, 1=enabled */
	public $active;
	/** @var string */
	public $tms;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		global $langs;

		$this->db = $db;

		// Translate arrayofkeyval entries
		if (is_object($langs)) {
			foreach ($this->fields as $key => $val) {
				if (!empty($val['arrayofkeyval']) && is_array($val['arrayofkeyval'])) {
					foreach ($val['arrayofkeyval'] as $key2 => $val2) {
						$this->fields[$key]['arrayofkeyval'][$key2] = $langs->trans($val2);
					}
				}
			}
		}
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
	 * Delete object in database
	 *
	 * @param  User $user      User that deletes
	 * @param  int  $notrigger 0=launch triggers, 1=disable triggers
	 * @return int             >0 if OK, <0 if KO
	 */
	public function delete(User $user, $notrigger = 0)
	{
		// Check if this type is used by any chargement
		$sql = "SELECT COUNT(rowid) as nb FROM ".MAIN_DB_PREFIX."planchargement_chargement";
		$sql .= " WHERE fk_camion_type = ".((int) $this->id);
		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			if ($obj->nb > 0) {
				$this->error = 'PlanchargementErrorTypeInUse';
				return -1;
			}
		}

		return $this->deleteCommon($user, $notrigger);
	}

	/**
	 * Return a link to the object card (with optionally the picto)
	 *
	 * @param  int    $withpicto             Include picto in link (0=No, 1=Include, 2=Only picto)
	 * @param  string $option                Link variant
	 * @param  int    $notooltip             1=Disable tooltip
	 * @param  string $morecss               Additional CSS
	 * @param  int    $save_lastsearch_value -1=Auto, 0=No, 1=Yes
	 * @return string                        HTML link
	 */
	public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $morecss = '', $save_lastsearch_value = -1)
	{
		global $langs;

		$result = '';
		$label = img_picto('', $this->picto).' <u>'.$langs->trans("PlanchargementCamionType").'</u><br>';
		$label .= '<b>'.$langs->trans('Label').':</b> '.$this->label;

		$url = dol_buildpath('/planchargement/admin/camiontype_card.php', 1).'?id='.$this->id;

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
		$result .= $this->label;
		$result .= $linkend;

		return $result;
	}
}
