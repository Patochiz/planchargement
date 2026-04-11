<?php
/* Copyright (C) 2025 Patrice GOURMELEN <pgourmelen@diamant-industrie.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

/**
 * \file       colisage/class/colisagepackage.class.php
 * \ingroup    colisage
 * \brief      Classe pour gérer les colis de colisage
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Classe pour gérer les colis de colisage
 */
class ColisagePackage extends CommonObject
{
    /**
     * @var string Module name
     */
    public $module = 'colisage';

    /**
     * @var string Element name (nom de l'objet)
     */
    public $element = 'colisagepackage';

    /**
     * @var string Table name
     */
    public $table_element = 'colisage_packages';

    /**
     * @var int ID
     */
    public $id;

    /**
     * @var int ID de la commande
     */
    public $fk_commande;

    /**
     * @var int Multiplicateur (nombre de colis identiques)
     */
    public $multiplier = 1;

    /**
     * @var int Colis libre (0 = normal, 1 = libre)
     */
    public $is_free = 0;

    /**
     * @var float Poids total du colis (par unité)
     */
    public $total_weight = 0.0;

    /**
     * @var float Surface totale du colis (par unité)
     */
    public $total_surface = 0.0;

    /**
     * @var int Numéro de livraison (1, 2, 3...)
     */
    public $livraison_num = 1;

    /**
     * @var string Date de création
     */
    public $date_creation;

    /**
     * @var string Date de modification
     */
    public $date_modification;

    /**
     * @var int ID utilisateur créateur
     */
    public $fk_user_creat;

    /**
     * @var int ID utilisateur modificateur
     */
    public $fk_user_modif;

    /**
     * @var array Articles du colis
     */
    public $items = array();

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Create package in database
     *
     * @param User $user User that creates
     * @param int $notrigger 0=launch triggers after, 1=disable triggers
     * @return int <0 if KO, Id of created object if OK
     */
    public function create($user, $notrigger = 0)
    {
        global $conf;

        $error = 0;

        // Clean parameters
        $this->fk_commande = (int) $this->fk_commande;
        $this->multiplier = max(1, (int) $this->multiplier);
        $this->is_free = (int) $this->is_free;
        $this->total_weight = (float) $this->total_weight;
        $this->total_surface = (float) $this->total_surface;
        $this->livraison_num = max(1, (int) $this->livraison_num);

        $now = dol_now();

        $this->db->begin();

        $sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element." (";
        $sql .= "fk_commande,";
        $sql .= "multiplier,";
        $sql .= "is_free,";
        $sql .= "livraison_num,";
        $sql .= "total_weight,";
        $sql .= "total_surface,";
        $sql .= "date_creation,";
        $sql .= "fk_user_creat";
        $sql .= ") VALUES (";
        $sql .= " ".((int) $this->fk_commande).",";
        $sql .= " ".((int) $this->multiplier).",";
        $sql .= " ".((int) $this->is_free).",";
        $sql .= " ".((int) $this->livraison_num).",";
        $sql .= " ".((float) $this->total_weight).",";
        $sql .= " ".((float) $this->total_surface).",";
        $sql .= " '".$this->db->idate($now)."',";
        $sql .= " ".((int) $user->id);
        $sql .= ")";

        $resql = $this->db->query($sql);
        if (!$resql) {
            $error++;
            $this->errors[] = "Error ".$this->db->lasterror();
        }

        if (!$error) {
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);
            $this->date_creation = $now;
            $this->fk_user_creat = $user->id;
        }

        if ($error) {
            $this->db->rollback();
            return -1 * $error;
        } else {
            $this->db->commit();
            return $this->id;
        }
    }

    /**
     * Load object in memory from the database
     *
     * @param int $id Id object
     * @return int <0 if KO, 0 if not found, >0 if OK
     */
    public function fetch($id)
    {
        $sql = "SELECT rowid, fk_commande, multiplier, is_free, livraison_num, total_weight, total_surface,";
        $sql .= " date_creation, date_modification, fk_user_creat, fk_user_modif";
        $sql .= " FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE rowid = ".((int) $id);

        $resql = $this->db->query($sql);
        if ($resql) {
            $numrows = $this->db->num_rows($resql);
            if ($numrows) {
                $obj = $this->db->fetch_object($resql);

                $this->id = $obj->rowid;
                $this->fk_commande = $obj->fk_commande;
                $this->multiplier = $obj->multiplier;
                $this->is_free = $obj->is_free;
                $this->livraison_num = isset($obj->livraison_num) ? (int) $obj->livraison_num : 1;
                $this->total_weight = $obj->total_weight;
                $this->total_surface = $obj->total_surface;
                $this->date_creation = $this->db->jdate($obj->date_creation);
                $this->date_modification = $this->db->jdate($obj->date_modification);
                $this->fk_user_creat = $obj->fk_user_creat;
                $this->fk_user_modif = $obj->fk_user_modif;

                // Charger les articles
                $this->fetchItems();

                $this->db->free($resql);
                return 1;
            } else {
                $this->db->free($resql);
                return 0;
            }
        } else {
            $this->errors[] = 'Error '.$this->db->lasterror();
            return -1;
        }
    }

    /**
     * Update package in database
     *
     * @param User $user User that modifies
     * @param int $notrigger 0=launch triggers after, 1=disable triggers
     * @return int <0 if KO, >0 if OK
     */
    public function update($user, $notrigger = 0)
    {
        $error = 0;

        // Clean parameters
        $this->multiplier = max(1, (int) $this->multiplier);
        $this->total_weight = (float) $this->total_weight;
        $this->total_surface = (float) $this->total_surface;
        $this->livraison_num = max(1, (int) $this->livraison_num);

        $this->db->begin();

        $sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET";
        $sql .= " multiplier = ".((int) $this->multiplier).",";
        $sql .= " livraison_num = ".((int) $this->livraison_num).",";
        $sql .= " total_weight = ".((float) $this->total_weight).",";
        $sql .= " total_surface = ".((float) $this->total_surface).",";
        $sql .= " date_modification = '".$this->db->idate(dol_now())."',";
        $sql .= " fk_user_modif = ".((int) $user->id);
        $sql .= " WHERE rowid = ".((int) $this->id);

        $resql = $this->db->query($sql);
        if (!$resql) {
            $error++;
            $this->errors[] = "Error ".$this->db->lasterror();
        }

        if ($error) {
            $this->db->rollback();
            return -1 * $error;
        } else {
            $this->db->commit();
            return 1;
        }
    }

    /**
     * Delete package from database
     *
     * @param User $user User that deletes
     * @param int $notrigger 0=launch triggers after, 1=disable triggers
     * @return int <0 if KO, >0 if OK
     */
    public function delete($user, $notrigger = 0)
    {
        $error = 0;

        $this->db->begin();

        // Supprimer d'abord tous les articles
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."colisage_items";
        $sql .= " WHERE fk_package = ".((int) $this->id);

        $resql = $this->db->query($sql);
        if (!$resql) {
            $error++;
            $this->errors[] = "Error ".$this->db->lasterror();
        }

        if (!$error) {
            // Supprimer le colis
            $sql = "DELETE FROM ".MAIN_DB_PREFIX.$this->table_element;
            $sql .= " WHERE rowid = ".((int) $this->id);

            $resql = $this->db->query($sql);
            if (!$resql) {
                $error++;
                $this->errors[] = "Error ".$this->db->lasterror();
            }
        }

        if ($error) {
            $this->db->rollback();
            return -1 * $error;
        } else {
            $this->db->commit();
            return 1;
        }
    }

    /**
     * Fetch all items of this package
     *
     * @return int <0 if KO, >=0 if OK
     */
    public function fetchItems()
    {
        require_once __DIR__.'/colisageitem.class.php';

        $this->items = array();

        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."colisage_items";
        $sql .= " WHERE fk_package = ".((int) $this->id);
        $sql .= " ORDER BY rowid ASC";

        $resql = $this->db->query($sql);
        if ($resql) {
            $num = $this->db->num_rows($resql);
            while ($obj = $this->db->fetch_object($resql)) {
                $item = new ColisageItem($this->db);
                if ($item->fetch($obj->rowid) > 0) {
                    $this->items[] = $item;
                }
            }
            $this->db->free($resql);
            return $num;
        } else {
            $this->errors[] = 'Error '.$this->db->lasterror();
            return -1;
        }
    }

    /**
     * Get all packages for a commande
     *
     * @param int $fk_commande ID de la commande
     * @return array Array of ColisagePackage objects
     */
    public function fetchByCommande($fk_commande)
    {
        $packages = array();

        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE fk_commande = ".((int) $fk_commande);
        $sql .= " ORDER BY rowid ASC";

        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $package = new ColisagePackage($this->db);
                if ($package->fetch($obj->rowid) > 0) {
                    $packages[] = $package;
                }
            }
            $this->db->free($resql);
        }

        return $packages;
    }

    /**
     * Recalculate totals based on items
     *
     * @return void
     */
    public function calculateTotals()
    {
        $this->total_weight = 0.0;
        $this->total_surface = 0.0;

        foreach ($this->items as $item) {
            $this->total_weight += $item->weight_unit * $item->quantity;
            $this->total_surface += $item->surface_unit * $item->quantity;
        }
    }
}
