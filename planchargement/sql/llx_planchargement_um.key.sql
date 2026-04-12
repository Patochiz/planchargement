-- Copyright (C) 2024 Patochiz
--
-- Indexes for llx_planchargement_um

ALTER TABLE llx_planchargement_um ADD INDEX idx_planchargement_um_fk_chargement (fk_chargement);
ALTER TABLE llx_planchargement_um ADD INDEX idx_planchargement_um_fk_um_type (fk_um_type);
ALTER TABLE llx_planchargement_um ADD INDEX idx_planchargement_um_fk_um_parent (fk_um_parent);
