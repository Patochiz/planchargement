-- Copyright (C) 2024 Patochiz
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

CREATE TABLE IF NOT EXISTS llx_planchargement_chargement (
	rowid           INTEGER AUTO_INCREMENT PRIMARY KEY,
	ref             VARCHAR(32) NOT NULL,
	date_chargement DATE DEFAULT NULL,
	fk_camion_type  INTEGER NOT NULL,
	poids_total     DOUBLE DEFAULT 0,
	note_public     TEXT,
	note_private    TEXT,
	statut          SMALLINT NOT NULL DEFAULT 0,
	fk_user_creat   INTEGER DEFAULT NULL,
	fk_user_modif   INTEGER DEFAULT NULL,
	date_creation   DATETIME DEFAULT NULL,
	tms             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
