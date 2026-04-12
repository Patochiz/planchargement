<?php
/* Copyright (C) 2024 Patochiz
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \defgroup planchargement Module Planchargement
 * \brief    Truck loading plan management module for Dolibarr
 * \file     core/modules/modPlanchargement.class.php
 * \ingroup  planchargement
 * \brief    Module descriptor for planchargement
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Class modPlanchargement
 * Module descriptor for Plan de chargement
 */
class modPlanchargement extends DolibarrModules
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $conf;

		$this->db = $db;

		// Module identifiers
		$this->numero = 500100;
		$this->rights_class = 'planchargement';
		$this->family = 'crm';
		$this->module_position = '90';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = 'PlanchargementDescription';
		$this->descriptionlong = 'PlanchargementDescriptionLong';
		$this->editor_name = 'Patochiz';
		$this->editor_url = 'https://github.com/Patochiz';
		$this->version = '0.1.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'generic';

		// Module parts - flat format only (Rule 7)
		$this->module_parts = array(
			'hooks' => array('ordercard', 'main')
		);

		// Directories
		$this->dirs = array();

		// Config page
		$this->config_page_url = array('setup.php@planchargement');

		// Dependencies
		$this->hidden = false;
		$this->depends = array();
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array('planchargement@planchargement');

		// PHP / Dolibarr requirements
		$this->phpmin = array(8, 2);
		$this->need_dolibarr_version = array(20, 0);

		// Constants
		$this->const = array(
			0 => array(
				'PLANCHARGEMENT_CHARGEMENT_ADDON',
				'chaine',
				'mod_chargement_standard',
				'Numbering module for loading plans',
				0,
				'current',
				1
			),
		);

		// Tabs
		$this->tabs = array();

		// Dictionaries
		$this->dictionaries = array();

		// Boxes/Widgets
		$this->boxes = array();

		// Cronjobs
		$this->cronjobs = array();

		// Permissions - using rights_class 'planchargement'
		$this->rights = array();
		$r = 0;

		$this->rights[$r][0] = 500101;
		$this->rights[$r][1] = 'PlanchargementPermRead';
		$this->rights[$r][2] = 'r';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'read';
		$this->rights[$r][5] = '';
		$r++;

		$this->rights[$r][0] = 500102;
		$this->rights[$r][1] = 'PlanchargementPermWrite';
		$this->rights[$r][2] = 'w';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'write';
		$this->rights[$r][5] = '';
		$r++;

		$this->rights[$r][0] = 500103;
		$this->rights[$r][1] = 'PlanchargementPermDelete';
		$this->rights[$r][2] = 'd';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'delete';
		$this->rights[$r][5] = '';
		$r++;

		$this->rights[$r][0] = 500104;
		$this->rights[$r][1] = 'PlanchargementPermAdmin';
		$this->rights[$r][2] = 'a';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'admin';
		$this->rights[$r][5] = '';
		$r++;

		// Menus
		$this->menu = array();
		$r = 0;

		// Top menu
		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=commercial',
			'type' => 'left',
			'titre' => 'PlanchargementList',
			'prefix' => img_picto('', $this->picto, 'class="paddingright pictofixedwidth valignmiddle"'),
			'mainmenu' => 'commercial',
			'leftmenu' => 'planchargement',
			'url' => '/planchargement/list.php',
			'langs' => 'planchargement@planchargement',
			'position' => 100,
			'enabled' => '$conf->planchargement->enabled',
			'perms' => '$user->hasRight("planchargement", "read")',
			'target' => '',
			'user' => 0,
		);
		$r++;

		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=commercial,fk_leftmenu=planchargement',
			'type' => 'left',
			'titre' => 'PlanchargementNew',
			'mainmenu' => 'commercial',
			'leftmenu' => 'planchargement_new',
			'url' => '/planchargement/card.php?action=create',
			'langs' => 'planchargement@planchargement',
			'position' => 101,
			'enabled' => '$conf->planchargement->enabled',
			'perms' => '$user->hasRight("planchargement", "write")',
			'target' => '',
			'user' => 0,
		);
		$r++;

		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=commercial,fk_leftmenu=planchargement',
			'type' => 'left',
			'titre' => 'PlanchargementList',
			'mainmenu' => 'commercial',
			'leftmenu' => 'planchargement_list',
			'url' => '/planchargement/list.php',
			'langs' => 'planchargement@planchargement',
			'position' => 102,
			'enabled' => '$conf->planchargement->enabled',
			'perms' => '$user->hasRight("planchargement", "read")',
			'target' => '',
			'user' => 0,
		);
		$r++;
	}

	/**
	 * Function called when module is enabled.
	 * The init function adds tabs, constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 * It also creates data directories.
	 *
	 * @param  string $options Options when enabling module ('', 'noboxes')
	 * @return int              1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		$result = $this->_load_tables('/planchargement/sql/');
		if ($result < 0) {
			return -1;
		}

		$sql = array();

		return $this->_init($sql, $options);
	}

	/**
	 * Function called when module is disabled.
	 *
	 * @param  string $options Options when disabling module ('', 'noboxes')
	 * @return int              1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();
		// Clean orphaned constants (guide section 8.3)
		$sql[] = "DELETE FROM ".MAIN_DB_PREFIX."const WHERE name LIKE 'PLANCHARGEMENT_%'";

		return $this->_remove($sql, $options);
	}
}
