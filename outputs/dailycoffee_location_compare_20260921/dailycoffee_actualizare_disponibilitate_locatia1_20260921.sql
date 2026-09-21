-- Daily Coffee, client 2
-- Dezactiveaza la locatia 1 variantele identificate ca produse pentru locatia 2.
-- Nu modifica produse_servicii, preturi, TVA, categorii sau tranzactii.

USE `u681731335_dailycoffee`;

DROP TEMPORARY TABLE IF EXISTS `tmp_dailycoffee_locatia1_excluderi`;

CREATE TEMPORARY TABLE `tmp_dailycoffee_locatia1_excluderi` (
  `cod_produs` BIGINT NOT NULL PRIMARY KEY,
  `denumire_verificata` VARCHAR(255) NOT NULL,
  `pret_verificat` DECIMAL(10,2) NOT NULL
) ENGINE=InnoDB;

INSERT INTO `tmp_dailycoffee_locatia1_excluderi`
  (`cod_produs`, `denumire_verificata`, `pret_verificat`)
VALUES
  (416, 'SIROP VANILIE', 37.00),
  (890, 'ESPRESSO', 6.00),
  (891, 'ESPRESSO CU LAPTE', 7.00),
  (923, 'KENYA', 175.00),
  (924, 'CAPPUCINO', 8.00),
  (925, 'FLAT WHITE', 10.00),
  (926, 'LATTE MACCHIATO', 8.00),
  (939, 'CEAI TO GO', 6.00),
  (945, 'ESPRESSO DUBLU', 9.00),
  (950, 'SW. CHERRY PEPP', 10.00),
  (958, 'ESPRESSO MACCHIATO', 7.00),
  (961, 'PERU PF', 180.00),
  (962, 'APA MINERALA', 5.00),
  (970, 'APA PLATA', 5.00),
  (981, 'COSTA RICA PF', 180.00),
  (984, 'KONAFETTO CACAO', 7.00),
  (1018, 'VEGETAL LATTE', 9.00),
  (1027, 'GINGER BEER 0.330', 9.50),
  (1033, 'SW. RASPBERRY LEMON', 10.00),
  (1038, 'VEGETAL CAPPUCINO', 9.00),
  (1049, 'VEGETAL FLAT WHITE', 11.00),
  (1051, 'CONSUMABILE MATERII PRIME', 0.00),
  (1055, 'CONSUMABILE', 0.00);

-- PREVIEW. Rezultatul trebuie verificat inainte de partea de modificare.
SELECT
  d.`cod_produs`,
  p.`nume`,
  p.`pret_cu_tva`,
  p.`activ` AS `activ_global`,
  CASE
    WHEN psl.`cod_produs` IS NULL THEN p.`activ`
    ELSE psl.`activ`
  END AS `disponibil_locatia1_actual`,
  0 AS `disponibil_locatia1_dorit`,
  CASE
    WHEN p.`cod_produs` IS NULL THEN 'PRODUS LIPSA DIN ONLINE'
    WHEN psl.`cod_produs` IS NULL THEN 'SE INSEREAZA MARCAJ LOCATIA 1 = 0'
    WHEN psl.`activ` = 0 THEN 'DEJA CORESPUNDE'
    ELSE 'SE DEZACTIVEAZA LOCATIA 1'
  END AS `actiune`
FROM `tmp_dailycoffee_locatia1_excluderi` d
LEFT JOIN `produse_servicii` p
  ON p.`cod_produs` = d.`cod_produs`
LEFT JOIN `produse_servicii_locatii` psl
  ON psl.`cod_produs` = d.`cod_produs`
 AND psl.`cod_locatie` = 1
ORDER BY d.`cod_produs`;

-- Daca preview-ul este corect, se poate continua cu aplicarea.
CREATE TABLE IF NOT EXISTS `backup_dailycoffee_locatia1_disponibilitate_20260921`
LIKE `produse_servicii_locatii`;

START TRANSACTION;

INSERT IGNORE INTO `backup_dailycoffee_locatia1_disponibilitate_20260921`
SELECT psl.*
FROM `produse_servicii_locatii` psl
INNER JOIN `tmp_dailycoffee_locatia1_excluderi` d
  ON d.`cod_produs` = psl.`cod_produs`
WHERE psl.`cod_locatie` = 1;

INSERT INTO `produse_servicii_locatii`
  (`cod_produs`, `cod_locatie`, `activ`)
SELECT
  d.`cod_produs`,
  1,
  0
FROM `tmp_dailycoffee_locatia1_excluderi` d
INNER JOIN `produse_servicii` p
  ON p.`cod_produs` = d.`cod_produs`
ON DUPLICATE KEY UPDATE
  `activ` = 0,
  `updated_at` = CURRENT_TIMESTAMP;

COMMIT;

-- VERIFICARE FINALĂ. Trebuie sa returneze zero randuri nealiniate.
SELECT
  d.`cod_produs`,
  p.`nume`,
  p.`pret_cu_tva`,
  psl.`cod_locatie`,
  psl.`activ` AS `activ_locatia1_final`
FROM `tmp_dailycoffee_locatia1_excluderi` d
INNER JOIN `produse_servicii` p
  ON p.`cod_produs` = d.`cod_produs`
LEFT JOIN `produse_servicii_locatii` psl
  ON psl.`cod_produs` = d.`cod_produs`
 AND psl.`cod_locatie` = 1
WHERE psl.`cod_produs` IS NULL
   OR psl.`activ` <> 0
ORDER BY d.`cod_produs`;

SELECT COUNT(*) AS `randuri_backup`
FROM `backup_dailycoffee_locatia1_disponibilitate_20260921`;

DROP TEMPORARY TABLE `tmp_dailycoffee_locatia1_excluderi`;
