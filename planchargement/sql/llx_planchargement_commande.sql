-- Copyright (C) 2024 Patochiz
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

CREATE TABLE IF NOT EXISTS llx_planchargement_commande (
	rowid         INTEGER AUTO_INCREMENT PRIMARY KEY,
	fk_chargement INTEGER NOT NULL,
	fk_commande   INTEGER NOT NULL
) ENGINE=InnoDB;
