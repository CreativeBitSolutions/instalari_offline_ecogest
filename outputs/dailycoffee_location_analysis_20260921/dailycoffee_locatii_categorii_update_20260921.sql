/*
  Daily Coffee, client_id = 2
  Scop: produsele dedicate locației 2 să nu mai fie eligibile la locația 1.

  Nu modifică produse_servicii, categorii sau date tranzacționale.
  Modifică numai marcajul din produse_servicii_locatii pentru cod_locatie = 1.
*/

USE `u681731335_dailycoffee`;

/*
  1. Verificare înainte de modificare.
  Lista trebuie citită înainte de executarea blocului de actualizare.
*/
SELECT
    p.cod_produs,
    p.nume,
    p.pret_cu_tva,
    p.id_categorie,
    c.den_categ AS categorie,
    p.identificator_offline,
    COALESCE(psl1.activ, 'LIPSĂ') AS marcaj_locatia_1,
    CASE
        WHEN p.identificator_offline LIKE 'client2_loc2_dailycoffee_produs_%'
            THEN 'locația 2, identificator offline'
        ELSE 'locația 2, categorie A'
    END AS motiv
FROM `produse_servicii` p
JOIN `categorii` c
    ON c.id_categorie = p.id_categorie
LEFT JOIN `produse_servicii_locatii` psl1
    ON psl1.cod_produs = p.cod_produs
   AND psl1.cod_locatie = 1
WHERE p.identificator_offline LIKE 'client2_loc2_dailycoffee_produs_%'
   OR p.id_categorie IN (33, 34, 35, 36, 37, 38, 39)
ORDER BY c.den_categ, p.nume, p.cod_produs;

/*
  2. Backup al stării locului 1.
  Se păstrează și produsele care nu aveau încă rând în produse_servicii_locatii,
  pentru ca starea inițială să poată fi refăcută dacă va fi nevoie.
*/
CREATE TABLE IF NOT EXISTS `backup_dailycoffee_loc1_location2_products_20260921` (
    `cod_produs` bigint(20) NOT NULL,
    `cod_locatie` bigint(20) NOT NULL,
    `exista_marcaj` tinyint(1) NOT NULL,
    `activ_initial` tinyint(1) DEFAULT NULL,
    `created_at_initial` timestamp NULL DEFAULT NULL,
    `updated_at_initial` timestamp NULL DEFAULT NULL,
    `backup_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`cod_produs`, `cod_locatie`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `backup_dailycoffee_loc1_location2_products_20260921` (
    `cod_produs`,
    `cod_locatie`,
    `exista_marcaj`,
    `activ_initial`,
    `created_at_initial`,
    `updated_at_initial`
)
SELECT
    p.cod_produs,
    1 AS cod_locatie,
    CASE WHEN psl1.cod_produs IS NULL THEN 0 ELSE 1 END AS exista_marcaj,
    psl1.activ,
    psl1.created_at,
    psl1.updated_at
FROM `produse_servicii` p
LEFT JOIN `produse_servicii_locatii` psl1
    ON psl1.cod_produs = p.cod_produs
   AND psl1.cod_locatie = 1
WHERE p.identificator_offline LIKE 'client2_loc2_dailycoffee_produs_%'
   OR p.id_categorie IN (33, 34, 35, 36, 37, 38, 39);

/*
  3. Actualizare.
  Pentru fiecare produs identificat se creează sau se actualizează marcajul
  locației 1 cu activ = 0. Marcajul locației 2 nu este modificat.
*/
START TRANSACTION;

INSERT INTO `produse_servicii_locatii` (`cod_produs`, `cod_locatie`, `activ`)
SELECT p.cod_produs, 1 AS cod_locatie, 0 AS activ
FROM `produse_servicii` p
WHERE p.identificator_offline LIKE 'client2_loc2_dailycoffee_produs_%'
   OR p.id_categorie IN (33, 34, 35, 36, 37, 38, 39)
ON DUPLICATE KEY UPDATE
    `activ` = 0,
    `updated_at` = CURRENT_TIMESTAMP;

COMMIT;

/*
  4. Verificare după actualizare.
  Prima interogare trebuie să returneze 0.
*/
SELECT COUNT(*) AS produse_neactivate_la_locatia_1
FROM `produse_servicii` p
LEFT JOIN `produse_servicii_locatii` psl1
    ON psl1.cod_produs = p.cod_produs
   AND psl1.cod_locatie = 1
WHERE (
        p.identificator_offline LIKE 'client2_loc2_dailycoffee_produs_%'
        OR p.id_categorie IN (33, 34, 35, 36, 37, 38, 39)
      )
  AND (psl1.cod_produs IS NULL OR psl1.activ <> 0);

SELECT
    p.cod_produs,
    p.nume,
    c.den_categ AS categorie,
    p.pret_cu_tva,
    psl1.activ AS activ_locatia_1,
    psl2.activ AS activ_locatia_2
FROM `produse_servicii` p
JOIN `categorii` c
    ON c.id_categorie = p.id_categorie
LEFT JOIN `produse_servicii_locatii` psl1
    ON psl1.cod_produs = p.cod_produs
   AND psl1.cod_locatie = 1
LEFT JOIN `produse_servicii_locatii` psl2
    ON psl2.cod_produs = p.cod_produs
   AND psl2.cod_locatie = 2
WHERE (
        p.identificator_offline LIKE 'client2_loc2_dailycoffee_produs_%'
        OR p.id_categorie IN (33, 34, 35, 36, 37, 38, 39)
      )
ORDER BY c.den_categ, p.nume, p.cod_produs;

/*
  Opțional, rollback folosind backupul creat mai sus.
  Nu se rulează acum. Se păstrează pentru caz de nevoie.

  START TRANSACTION;

  DELETE psl
  FROM `produse_servicii_locatii` psl
  JOIN `backup_dailycoffee_loc1_location2_products_20260921` b
    ON b.cod_produs = psl.cod_produs
   AND b.cod_locatie = psl.cod_locatie
  WHERE b.exista_marcaj = 0;

  UPDATE `produse_servicii_locatii` psl
  JOIN `backup_dailycoffee_loc1_location2_products_20260921` b
    ON b.cod_produs = psl.cod_produs
   AND b.cod_locatie = psl.cod_locatie
  SET psl.activ = b.activ_initial,
      psl.created_at = b.created_at_initial,
      psl.updated_at = b.updated_at_initial
  WHERE b.exista_marcaj = 1;

  COMMIT;
*/
