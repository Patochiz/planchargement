-- Copyright (C) 2024 Patochiz
--
-- Indexes for llx_planchargement_commande

ALTER TABLE llx_planchargement_commande ADD UNIQUE INDEX uk_planchargement_commande_pair (fk_chargement, fk_commande);
ALTER TABLE llx_planchargement_commande ADD INDEX idx_planchargement_commande_fk_chargement (fk_chargement);
ALTER TABLE llx_planchargement_commande ADD INDEX idx_planchargement_commande_fk_commande (fk_commande);
