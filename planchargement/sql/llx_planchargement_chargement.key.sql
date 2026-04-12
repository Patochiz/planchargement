-- Copyright (C) 2024 Patochiz
--
-- Indexes for llx_planchargement_chargement

ALTER TABLE llx_planchargement_chargement ADD UNIQUE INDEX uk_planchargement_chargement_ref (ref);
ALTER TABLE llx_planchargement_chargement ADD INDEX idx_planchargement_chargement_statut (statut);
ALTER TABLE llx_planchargement_chargement ADD INDEX idx_planchargement_chargement_fk_camion_type (fk_camion_type);
ALTER TABLE llx_planchargement_chargement ADD INDEX idx_planchargement_chargement_date_chargement (date_chargement);
ALTER TABLE llx_planchargement_chargement ADD INDEX idx_planchargement_chargement_fk_user_creat (fk_user_creat);
