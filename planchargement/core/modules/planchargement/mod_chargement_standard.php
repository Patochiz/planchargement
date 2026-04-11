<?php
/* Copyright (C) 2024 Patochiz
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    core/modules/planchargement/mod_chargement_standard.php
 * \ingroup planchargement
 * \brief   Standard numbering module for Chargement
 *          Generates refs like CH2604-0001 (CH + YYMM + dash + 4-digit counter)
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/CommonNumRefGenerator.class.php';

/**
 * Class mod_chargement_standard
 * Standard numbering for loading plans
 */
class mod_chargement_standard extends CommonNumRefGenerator
{
	/**
	 * Dolibarr version of the loaded document
	 * @var string
	 */
	public $version = 'dolibarr';

	/**
	 * Prefix
	 * @var string
	 */
	public $prefix = 'CH';

	/**
	 * @var string Name
	 */
	public $name = 'standard';

	/**
	 * @var string Error message
	 */
	public $error = '';

	/**
	 * Return description of numbering module
	 *
	 * @param  Translate $langs Lang object to use for output
	 * @return string           Descriptive text
	 */
	public function info($langs)
	{
		$langs->load('planchargement@planchargement');
		return $langs->trans('PlanchargementNumberingModule').' : '.$this->prefix.'YYMM-NNNN';
	}

	/**
	 * Return an example of numbering
	 *
	 * @return string Example
	 */
	public function getExample()
	{
		return $this->prefix.date('ym').'-0001';
	}

	/**
	 * Checks if the numbers already in the database do not
	 * cause conflicts that would prevent this numbering working.
	 *
	 * @param  CommonObject $object Object we need next value for
	 * @return bool                 false if KO (there is a conflict), true if OK
	 */
	public function canBeActivated($object)
	{
		global $db;

		$pref = $this->prefix;

		$sql = "SELECT MAX(CAST(SUBSTRING(ref, 7) AS SIGNED)) as max_num";
		$sql .= " FROM ".MAIN_DB_PREFIX."planchargement_chargement";
		$sql .= " WHERE ref LIKE '".$db->escape($pref)."%'";

		$resql = $db->query($sql);
		if ($resql) {
			return true;
		}

		return false;
	}

	/**
	 * Return next free value
	 *
	 * @param  CommonObject $object Object we need next value for
	 * @return string               Next value or '' if error
	 */
	public function getNextValue($object)
	{
		global $db;

		$pref = $this->prefix;
		$mask = $pref.date('ym');

		// Find max counter for current year-month
		$sql = "SELECT MAX(CAST(SUBSTRING(ref, 8) AS SIGNED)) as max_num";
		$sql .= " FROM ".MAIN_DB_PREFIX."planchargement_chargement";
		$sql .= " WHERE ref LIKE '".$db->escape($mask)."-%'";
		$sql .= " AND ref <> '(PROV)'";

		$resql = $db->query($sql);
		if (!$resql) {
			$this->error = $db->lasterror();
			return '';
		}

		$obj = $db->fetch_object($resql);
		$max = (int) $obj->max_num;

		$num = $max + 1;

		return $mask.'-'.sprintf('%04d', $num);
	}
}
