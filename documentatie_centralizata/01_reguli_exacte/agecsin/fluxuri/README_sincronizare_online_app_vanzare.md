# Sincronizare online app vanzare

Folder separat pentru fluxul online folosit de instalarile offline.

## Import operatiuni offline

URL local:

`/sincronizare_online_app_vanzare/import_operatiuni_offline.php`

Scriptul are doua profiluri:

- `agremprejba`, instalari doar vanzare, Agrem Prejba, Bestmixt si Andreearobertshop
- `dailycoffee`, vanzare plus NIR

Profilul poate fi selectat manual sau detectat automat. Daca fisierul contine randuri in `nir` sau `achizitii`, profilul detectat este `dailycoffee`.

## Tabele instalari doar vanzare

- `rapoarte_z`
- `inchideri_r_12`
- `note`
- `det_note`
- `discounturi_acordate`
- `bonuri_casa_marcat`
- `miscari`
- `log_reglari_casa_marcat`

`nir` si `achizitii` sunt ignorate pentru acest profil.

## Tabele Daily Coffee

- `rapoarte_z`
- `inchideri_r_12`
- `note`
- `det_note`
- `discounturi_acordate`
- `bonuri_casa_marcat`
- `nir`
- `achizitii`
- `miscari`
- `log_reglari_casa_marcat`

Pentru `nir` si `achizitii`, scriptul foloseste tabelele reale din aplicatia online daca sunt definite in `database_connection.php`, de exemplu `nir_12` si `achizitii_12`.

Pentru Daily Coffee, numai miscarile `NIR` se importa din XML. Ele sunt legate de NIR-ul, achizitia si produsul corespondente din online. Miscarile `BF` se regenereaza online din bonuri cu retetele curente.

Pentru produsele Daily Coffee, importul foloseste cu prioritate `cod_p` din XML pentru a reutiliza produsul online si reteta sa existenta. Un produs nou este creat numai daca acel cod nu exista in nomenclatorul online.

La importul miscarilor `NIR` Daily Coffee, gestiunea este preluata din produsul online, pentru ca intrarile si consumurile generate de reteta sa fie in aceeasi gestiune.

## Verificare schema

Inainte de import, scriptul verifica si completeaza automat in baza online toate tabelele profilului cu:

- `identificator_offline`
- `cod_locatie`

Pentru `produse_servicii`, aceleasi coloane si indexul sunt pregatite automat, astfel incat un produs lipsa din nomenclator sa poata fi creat la import. Importul este blocat numai daca tabela lipseste sau serverul nu permite modificarea schemei.

## Regula identificator_offline

Fisierul XML offline nu trebuie sa contina `identificator_offline`.

Valoarea se genereaza la import din prima cheie locala a randului:

- `note.nrbon`
- `det_note.id_vanz`
- `discounturi_acordate.id_discount`
- `bonuri_casa_marcat.id`
- `inchideri_r_12.id_inch`
- `rapoarte_z.id`
- `nir.id_nir`
- `achizitii.id_achiz`
- `miscari.id`
- `log_reglari_casa_marcat.id`

Format:

`client{cod_client}_loc{cod_locatie}_{profil}_{tabel}_{cheie_locala}`

## Regula cod_locatie

Daca randul are `cod_locatie`, se pastreaza.

Daca randul are `locatie`, dar nu are `cod_locatie`, importul completeaza:

`cod_locatie = locatie`

## Coloane necesare online

Pentru toate tabelele folosite de profilul activ:

```sql
ALTER TABLE rapoarte_z ADD COLUMN identificator_offline VARCHAR(191) NULL, ADD COLUMN cod_locatie INT NULL;
ALTER TABLE inchideri_r_12 ADD COLUMN identificator_offline VARCHAR(191) NULL, ADD COLUMN cod_locatie INT NULL;
ALTER TABLE note ADD COLUMN identificator_offline VARCHAR(191) NULL, ADD COLUMN cod_locatie INT NULL;
ALTER TABLE det_note ADD COLUMN identificator_offline VARCHAR(191) NULL, ADD COLUMN cod_locatie INT NULL;
ALTER TABLE discounturi_acordate ADD COLUMN identificator_offline VARCHAR(191) NULL, ADD COLUMN cod_locatie INT NULL;
ALTER TABLE bonuri_casa_marcat ADD COLUMN identificator_offline VARCHAR(191) NULL, ADD COLUMN cod_locatie INT NULL;
ALTER TABLE miscari ADD COLUMN identificator_offline VARCHAR(191) NULL, ADD COLUMN cod_locatie INT NULL;
ALTER TABLE log_reglari_casa_marcat ADD COLUMN identificator_offline VARCHAR(191) NULL, ADD COLUMN cod_locatie INT NULL;
ALTER TABLE nir ADD COLUMN identificator_offline VARCHAR(191) NULL, ADD COLUMN cod_locatie INT NULL;
ALTER TABLE achizitii ADD COLUMN identificator_offline VARCHAR(191) NULL, ADD COLUMN cod_locatie INT NULL;
ALTER TABLE produse_servicii ADD COLUMN identificator_offline VARCHAR(191) NULL, ADD COLUMN cod_locatie INT NULL;
```

Daca online foloseste tabele sufixate pentru Daily Coffee, ruleaza ALTER pe tabelele reale, de exemplu `nir_12` si `achizitii_12`.

## Nomenclator online

URL endpoint:

`/sincronizare_online_app_vanzare/nomenclator_online.php?api_key=CHEIE&cod_client=18`

Endpointul raspunde JSON si intoarce produsele, categoriile, legaturile categorie-locatie, gestiunile si cotele TVA.
