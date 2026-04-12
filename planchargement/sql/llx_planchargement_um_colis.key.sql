-- Copyright (C) 2024 Patochiz
--
-- Indexes for llx_planchargement_um_colis

ALTER TABLE llx_planchargement_um_colis ADD UNIQUE INDEX uk_planchargement_um_colis_pair (fk_um, fk_package);
ALTER TABLE llx_planchargement_um_colis ADD INDEX idx_planchargement_um_colis_fk_um (fk_um);
ALTER TABLE llx_planchargement_um_colis ADD INDEX idx_planchargement_um_colis_fk_package (fk_package);
