CREATE TABLE notifications_lues (
    id_notifications_lues INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT UNSIGNED NOT NULL,
    cle_notification CHAR(64) NOT NULL,
    lue_le TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_notification_utilisateur (utilisateur_id, cle_notification),
    CONSTRAINT fk_notifications_lues_utilisateur
        FOREIGN KEY (utilisateur_id)
        REFERENCES utilisateurs (id_utilisateurs)
        ON DELETE CASCADE
);
