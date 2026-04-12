-- Copyright (C) 2024 Patochiz
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

CREATE TABLE IF NOT EXISTS llx_planchargement_um_type (
	rowid                INTEGER AUTO_INCREMENT PRIMARY KEY,
	label                VARCHAR(255) NOT NULL,
	longueur             INTEGER NOT NULL DEFAULT 0 COMMENT 'mm',
	largeur              INTEGER NOT NULL DEFAULT 0 COMMENT 'mm',
	hauteur              INTEGER NOT NULL DEFAULT 0 COMMENT 'mm',
	charge_max           DOUBLE NOT NULL DEFAULT 0 COMMENT 'kg',
	gerbable             SMALLINT NOT NULL DEFAULT 0,
	active               SMALLINT NOT NULL DEFAULT 1,
	is_custom            SMALLINT NOT NULL DEFAULT 0 COMMENT '1=one-shot UM created from a composition tab',
	fk_chargement_origin INTEGER NULL COMMENT 'For is_custom=1: the chargement that created this type',
	tms                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
