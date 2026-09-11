# Documentație centralizată pentru instalările offline

Data consolidării: 10.09.2026

Acest folder separă sursele exacte de sintezele operaționale. Sursele originale rămân în repository-urile lor. Copiile din `01_reguli_exacte` sunt păstrate pentru consultare și comparație.

## Organizare

- `00_registru`, harta clienților, codurile, locațiile și manifestul surselor.
- `01_reguli_exacte`, copii neschimbate ale documentelor din `instalari_offline_ecogest` și `agecsin`.
- `02_sinteze`, fișe pe client și reguli comune, fără înlocuirea surselor primare.
- `03_fluxuri`, descrierea fluxurilor comune pentru magazin, restaurant, facturare și sincronizare.
- `04_lacune_si_conflicte`, versiuni istorice, neconcordanțe și documente lipsă.
- `05_manifest`, liste de surse, copii și sinteze.

Nu sunt copiate baze de date, exporturi operaționale, loguri, binare, fișiere de licență, parole sau chei API.

Fluxul FoxPro este documentat separat, deoarece folosește DBF și snapshoturi locale, nu același flux SQLite ca instalările POS și restaurant.

## Regula surselor

Documentul activ `tipuri_instalari_offline.txt` are prioritate pentru clasificarea instalărilor offline. Documentele din `agecsin\activitati_reguli_lucru` descriu activități, contabilitate, rutare și particularități ale clienților. Atunci când două surse diferă, diferența este notată în `04_lacune_si_conflicte` și nu este ascunsă prin concatenarea textelor.

## Repository-uri consultate

- `C:\xampp\htdocs\github\instalari_offline_ecogest`
- `C:\xampp\htdocs\github\agecsin`
