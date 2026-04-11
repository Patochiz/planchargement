-- Copyright (C) 2024 Patochiz
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

CREATE TABLE IF NOT EXISTS llx_planchargement_um_colis (
	rowid      INTEGER AUTO_INCREMENT PRIMARY KEY,
	fk_um      INTEGER NOT NULL,
	fk_package INTEGER NOT NULL,
	quantity   INTEGER NOT NULL DEFAULT 1
) ENGINE=InnoDB;
