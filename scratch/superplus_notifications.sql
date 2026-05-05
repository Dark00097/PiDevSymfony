CREATE TABLE IF NOT EXISTS superplus_notifications (
    idUser INT NOT NULL,
    moisAffiche VARCHAR(7) NOT NULL,
    dateCreation DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (idUser, moisAffiche)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
