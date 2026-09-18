-- Daily Coffee, client_id 2, marcaje disponibilitate locatie 2
-- Verifica rezultatul SELECT-ului de previzualizare inainte de COMMIT.
-- Scriptul nu modifica produse_servicii.activ, preturi, retete sau tranzactii.
USE u681731335_dailycoffee;

CREATE TEMPORARY TABLE tmp_dailycoffee_locatia2_decizii (
  cod_produs BIGINT NOT NULL PRIMARY KEY,
  cod_locatie BIGINT NOT NULL,
  activ_nou TINYINT NOT NULL,
  denumire_produs VARCHAR(255) NOT NULL,
  rol_recomandat VARCHAR(30) NOT NULL
) ENGINE=InnoDB;

INSERT INTO tmp_dailycoffee_locatia2_decizii
  (cod_produs, cod_locatie, activ_nou, denumire_produs, rol_recomandat)
VALUES
  (8, 2, 0, 'CONSUMABILE', 'Locatia 1'),
  (10, 2, 0, 'CONSUMABILE MATERII PRIME', 'Locatia 1'),
  (75, 2, 0, 'SW.CHERRY PEPP', 'Locatia 1'),
  (77, 2, 0, 'GINGER BEER 0.330', 'Locatia 1'),
  (243, 2, 0, 'ESPRESSO', 'Locatia 1'),
  (244, 2, 0, 'ESPRESSO DUBLU', 'Locatia 1'),
  (245, 2, 0, 'ESPRESSO MACCHIATO', 'Locatia 1'),
  (247, 2, 0, 'CAPPUCINO', 'Locatia 1'),
  (248, 2, 0, 'CEAI TO GO', 'Locatia 1'),
  (249, 2, 0, 'LATTE MACCHIATO', 'Locatia 1'),
  (250, 2, 0, 'FLAT WHITE', 'Locatia 1'),
  (292, 2, 0, 'APA PLATA', 'Locatia 1'),
  (293, 2, 0, 'APA MINERALA', 'Locatia 1'),
  (364, 2, 0, 'VEGETAL CAPPUCINO', 'Locatia 1'),
  (366, 2, 0, 'VEGETAL LATTE', 'Locatia 1'),
  (367, 2, 0, 'VEGETAL FLAT WHITE', 'Locatia 1'),
  (370, 2, 0, 'ESPRESSO CU LAPTE', 'Locatia 1'),
  (414, 2, 0, 'SIROP VANILIE', 'Locatia 1'),
  (416, 2, 1, 'SIROP VANILIE', 'Locatia 2'),
  (446, 2, 0, 'SW.RASPBERRY LEMON', 'Locatia 1'),
  (716, 2, 0, 'KENYA', 'Locatia 1'),
  (719, 2, 0, 'COSTA RICA PF', 'Locatia 1'),
  (858, 2, 0, 'PERU PF', 'Locatia 1'),
  (890, 2, 1, 'ESPRESSO', 'Locatia 2'),
  (891, 2, 1, 'ESPRESSO CU LAPTE', 'Locatia 2'),
  (913, 2, 0, 'KONAFETTO CACAO', 'Locatia 1'),
  (923, 2, 1, 'KENYA', 'Locatia 2'),
  (924, 2, 1, 'CAPPUCINO', 'Locatia 2'),
  (925, 2, 1, 'FLAT WHITE', 'Locatia 2'),
  (926, 2, 1, 'LATTE MACCHIATO', 'Locatia 2'),
  (939, 2, 1, 'CEAI TO GO', 'Locatia 2'),
  (945, 2, 1, 'ESPRESSO DUBLU', 'Locatia 2'),
  (950, 2, 1, 'SW.CHERRY PEPP', 'Locatia 2'),
  (958, 2, 1, 'ESPRESSO MACCHIATO', 'Locatia 2'),
  (961, 2, 1, 'PERU PF', 'Locatia 2'),
  (962, 2, 1, 'APA MINERALA', 'Locatia 2'),
  (970, 2, 1, 'APA PLATA', 'Locatia 2'),
  (981, 2, 1, 'COSTA RICA PF', 'Locatia 2'),
  (984, 2, 1, 'KONAFETTO CACAO', 'Locatia 2'),
  (1018, 2, 1, 'VEGETAL LATTE', 'Locatia 2'),
  (1027, 2, 1, 'GINGER BEER 0.330', 'Locatia 2'),
  (1033, 2, 1, 'SW.RASPBERRY LEMON', 'Locatia 2'),
  (1038, 2, 1, 'VEGETAL CAPPUCINO', 'Locatia 2'),
  (1049, 2, 1, 'VEGETAL FLAT WHITE', 'Locatia 2'),
  (1051, 2, 1, 'CONSUMABILE MATERII PRIME', 'Locatia 2'),
  (1055, 2, 1, 'CONSUMABILE', 'Locatia 2');

-- PREVIEW: randurile care urmeaza sa fie schimbate pe locatia 2.
SELECT
  d.cod_produs,
  p.nume,
  p.pret_cu_tva,
  COALESCE(psl.activ, 0) AS activ_actual,
  d.activ_nou,
  d.rol_recomandat
FROM tmp_dailycoffee_locatia2_decizii d
INNER JOIN produse_servicii p ON p.cod_produs = d.cod_produs
LEFT JOIN produse_servicii_locatii psl
  ON psl.cod_produs = d.cod_produs AND psl.cod_locatie = 2
WHERE COALESCE(psl.activ, 0) <> d.activ_nou
ORDER BY d.denumire_produs, d.cod_produs;

-- BACKUP separat, pastrat pentru revenire. INSERT IGNORE face scriptul rerulabil.
CREATE TABLE IF NOT EXISTS backup_dailycoffee_locatia2_disponibilitate_20260918 LIKE produse_servicii_locatii;

START TRANSACTION;

INSERT IGNORE INTO backup_dailycoffee_locatia2_disponibilitate_20260918
SELECT psl.*
FROM produse_servicii_locatii psl
INNER JOIN tmp_dailycoffee_locatia2_decizii d
  ON d.cod_produs = psl.cod_produs AND d.cod_locatie = psl.cod_locatie
WHERE psl.cod_locatie = 2;

INSERT INTO produse_servicii_locatii (cod_produs, cod_locatie, activ)
SELECT cod_produs, cod_locatie, activ_nou
FROM tmp_dailycoffee_locatia2_decizii
ON DUPLICATE KEY UPDATE
  activ = VALUES(activ),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

-- VERIFICARE dupa actualizare.
SELECT
  d.cod_produs,
  p.nume,
  COALESCE(psl.activ, 0) AS activ_final,
  d.activ_nou AS activ_asteptat
FROM tmp_dailycoffee_locatia2_decizii d
INNER JOIN produse_servicii p ON p.cod_produs = d.cod_produs
LEFT JOIN produse_servicii_locatii psl
  ON psl.cod_produs = d.cod_produs AND psl.cod_locatie = 2
WHERE COALESCE(psl.activ, 0) <> d.activ_nou
ORDER BY d.denumire_produs, d.cod_produs;

SELECT COUNT(*) AS randuri_backup
FROM backup_dailycoffee_locatia2_disponibilitate_20260918;

DROP TEMPORARY TABLE tmp_dailycoffee_locatia2_decizii;
