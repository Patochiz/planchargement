-- Copyright (C) 2024 Patochiz
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

CREATE TABLE IF NOT EXISTS llx_planchargement_camion_type (
	rowid          INTEGER AUTO_INCREMENT PRIMARY KEY,
	label          VARCHAR(255) NOT NULL,
	longueur_utile INTEGER NOT NULL DEFAULT 0 COMMENT 'mm',
	largeur_utile  INTEGER NOT NULL DEFAULT 0 COMMENT 'mm',
	hauteur_utile  INTEGER NOT NULL DEFAULT 0 COMMENT 'mm',
	charge_utile   DOUBLE NOT NULL DEFAULT 0 COMMENT 'kg',
	active         SMALLINT NOT NULL DEFAULT 1,
	tms            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
