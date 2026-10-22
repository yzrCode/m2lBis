ALTER TABLE utilisateurs
    ADD COLUMN doit_changer_mot_de_passe TINYINT(1) NOT NULL DEFAULT 0 AFTER actif;

ALTER TABLE dossiers
    ADD COLUMN id_utilisateurs INT UNSIGNED NULL AFTER id_dossiers,
    ADD COLUMN date_naissance_dossiers DATE NULL AFTER categorie_dossiers,
    ADD INDEX idx_dossiers_utilisateur (id_utilisateurs),
    ADD CONSTRAINT fk_dossiers_utilisateur
        FOREIGN KEY (id_utilisateurs)
        REFERENCES utilisateurs (id_utilisateurs)
        ON DELETE SET NULL;
