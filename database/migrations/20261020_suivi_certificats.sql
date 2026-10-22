CREATE TABLE certificats (
    id_certificats INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT UNSIGNED NOT NULL,
    saison_id INT UNSIGNED NOT NULL,
    statut_certificats VARCHAR(20) NOT NULL DEFAULT 'manquant',
    date_reception DATE NULL,
    modifie_le TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_certificats_utilisateur_saison (utilisateur_id, saison_id),
    CONSTRAINT fk_certificats_utilisateur
        FOREIGN KEY (utilisateur_id)
        REFERENCES utilisateurs (id_utilisateurs)
        ON DELETE CASCADE,
    CONSTRAINT fk_certificats_saison
        FOREIGN KEY (saison_id)
        REFERENCES saisons (id_saisons)
        ON DELETE CASCADE
);
