-- Copyright (C) 2024 Patochiz
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

CREATE TABLE IF NOT EXISTS llx_planchargement_um (
	rowid         INTEGER AUTO_INCREMENT PRIMARY KEY,
	fk_chargement INTEGER NOT NULL,
	fk_um_type    INTEGER NOT NULL,
	ref_um        VARCHAR(32) DEFAULT NULL,
	poids_total   DOUBLE DEFAULT 0,
	pos_x         INTEGER DEFAULT NULL COMMENT 'mm from tablier',
	pos_y         INTEGER DEFAULT NULL COMMENT 'mm from left edge',
	rotation      SMALLINT NOT NULL DEFAULT 0 COMMENT '0 or 90',
	fk_um_parent  INTEGER DEFAULT NULL COMMENT 'stacked on this UM',
	tms           TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
