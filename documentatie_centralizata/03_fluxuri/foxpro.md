# Fluxul FoxPro

Folderul `foxpro` este un subsistem separat de instalările POS și restaurant.

- Sursele DBF sunt copiate manual în `baza_date_copiata`.
- Citirea se face din copii locale pentru a evita blocarea fișierelor FoxPro.
- `note.dbf`, `compnote.dbf`, `totaluri.dbf` și `comp_total.dbf` furnizează datele pentru rapoarte.
- Atribuirea operatorului se face din `note.dbf.OSPATAR`, iar produsele se leagă prin `NR_NOTA`.
- Snapshoturile locale și baza SQLite nu se includ în arhiva de instalare.

Sursa exactă este păstrată în `01_reguli_exacte\instalari_offline_ecogest\surse_clienti\comun\foxpro-raport-notes.md`.

