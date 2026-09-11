# Revizuirea uniformizării structurii

## Surse

Analiza folosește 17 exporturi SQL, inclusiv `u681731335_taverna_amicii.sql`. Registrul central identifică Taverna Amicii prin `client_id 1008` și tipul `restaurant`.

Instalațiile offline de magazin sunt active pentru Daily Coffee, Agrem Prejba, Best Mixt și Andreea Robert. Acestea folosesc adaptarea locală a `app_vanzare_v2` din `C:\xampp\htdocs\instalari_offline`.

Taverna Amicii folosește adaptarea locală a `app_restaurant_v2` din `C:\xampp\htdocs\github\instalari_offline_ecogest\taverna_amicii`.

## Excluderi globale

Nu intră în uniformizare copiile de siguranță, tabelele vechi, tabelele cu înregistrări șterse, jurnalele pasive, tabelele de audit și structurile create pentru intervenții sau sarcini operative punctuale.

Sunt excluse explicit `bon_consum_generari_reteta_hanul`, `bon_consum_generari_reteta_hanul_detalii`, `corectii_consum_direct_mic_dejun_hanul_detalii`, `corectii_consum_productie_hanul_detalii`, `corectii_consum_productie_hanul_retete_backup`, `inchideri_stoc_operative`, `inchideri_stoc_operative_linii`, `sarcini_operative_corectii_productie_hanul`, `actiuni_operative`, `actiuni_operative_modificari_text`, `simulari_vanzari_asocieri`, `produse_servicii_sterse` și `eliberari_mese_vechi`.

Sunt excluse `audit_log`, `audit_modificari_pret_produse`, `audit_stergeri_det_note`, `miscari_audit`, `log_bonuri`, `log_reglari_casa_marcat` și `offline_sync_import_logs`.

Coloanele `actiune_operativa_id` și `actiune_operativa_pret_id` sunt eliminate din structura țintă. Coloanele existente nu sunt șterse.

## Profil offline magazin

Profilul `offline_magazin` se aplică clienților 2, 18, 21 și 22. Acesta păstrează `sincronizare_importuri_offline`, `sincronizare_operatori_offline`, `sincronizare_operatori_offline_istoric`, `offline_installation_state` și `offline_sequence_history`.

Aceste tabele păstrează starea importului, maparea operatorilor și controlul secvențelor. Sunt utilizate direct de importator și nu reprezintă copii de siguranță.

Profilul adaugă `identificator_offline` numai în structurile folosite de aplicația de magazin. Daily Coffee primește suplimentar profilul `offline_magazin_nir`, necesar pentru NIR, achiziții și transferurile dintre locații.

## Profil offline restaurant

Profilul `offline_restaurant` se aplică numai clientului 1008. Acesta păstrează `offline_sync_event_inbox`, `offline_sync_imported` și `offline_tablet_import_receipts`.

Coloana `identificator_offline` este necesară în note, detalii, discounturi, închideri, mișcări și rapoarte Z. `offline_sync_event_inbox` și `offline_sync_imported` asigură idempotentizarea și maparea entităților importate.

Sincronizarea utilizatorilor restaurantului preia direct operatorii online prin `api/offline-users.php`. Taverna Amicii nu folosește tabela `sincronizare_operatori_offline`, specifică aplicației offline de magazin.

## Siguranță

Preview-ul rămâne obligatoriu. Sarcina nu șterge, nu redenumește și nu rescrie tabele, coloane sau date existente. Structurile excluse care există deja rămân intacte.

Baza SQLite locală nu este modificată de sarcina MySQL din AGECS online.
