# Uniformizarea structurii bazelor de date ale clienților

## Clienți și surse

Sarcina este disponibilă pentru clienții 2, 3, 4, 6, 7, 8, 14, 17, 18, 19, 21, 22, 25, 1006, 1007 și 1008.

Structura țintă folosește 17 exporturi SQL locale analizate la 24.08.2026. `u681731335_taverna_amicii.sql` este inclus, iar Taverna Amicii este configurată ca client 1008. Hanul Patrisiei rămâne sursă structurală și nu primește sarcina de uniformizare.

Nu se copiază înregistrări între clienți. Tabelele nou create rămân goale.

## Structura funcțională comună

Manifestul reține tabelele și coloanele funcționale din exporturi. O coloană existentă nu este modificată, chiar dacă definiția sa diferă de definiția consolidată.

Nu sunt incluse structurile create numai pentru sarcini operative Hanul, intervenții punctuale, simulări, audit, log, copii de siguranță, date șterse sau tabele vechi. Câmpurile `actiune_operativa_id` și `actiune_operativa_pret_id` nu sunt propuse.

`eliberari_mese_vechi` este exclusă. Tabela funcțională păstrată este `eliberari_mese`.

## Profilul offline de magazin

Clienții 2, 18, 21 și 22 folosesc profilul `offline_magazin`, corespunzător instalațiilor locale bazate pe `app_vanzare_v2`.

Profilul include `sincronizare_importuri_offline`, `sincronizare_operatori_offline`, `sincronizare_operatori_offline_istoric`, `offline_installation_state` și `offline_sequence_history`. Aceste tabele sunt utilizate direct la import, maparea operatorilor și controlul secvențelor.

Daily Coffee primește suplimentar profilul `offline_magazin_nir`. Profilul acoperă identificatorii offline pentru NIR, achiziții și transferurile dintre locații.

## Profilul offline de restaurant

Clientul 1008 folosește profilul `offline_restaurant`, corespunzător instalației Taverna Amicii bazate pe `app_restaurant_v2`.

Profilul include `offline_sync_event_inbox`, `offline_sync_imported` și `offline_tablet_import_receipts`. Coloana `identificator_offline` este păstrată în note, detalii, discounturi, închideri, mișcări și rapoarte Z.

Taverna Amicii sincronizează operatorii direct prin `api/offline-users.php`. Tabela `sincronizare_operatori_offline` rămâne specifică profilului de magazin.

Tabelele SQLite locale, cozile locale și fișierele instalației offline nu sunt modificate de sarcina MySQL din AGECS online.

## Reguli de siguranță

Deschiderea paginii execută numai interogări asupra `information_schema`. Aplicarea instrucțiunilor necesită rang de administrator, token CSRF, confirmarea backupului, ID-ul exact al clientului și hashul planului.

Preview-ul afișează profilurile active, tabelele lipsă, coloanele lipsă, structurile excluse și structurile rezervate altor profiluri offline.

Tabelele, coloanele și datele existente nu sunt șterse, golite, redenumite sau rescrise. Indexurile tabelelor existente rămân neschimbate. Tabelele nou create primesc numai indexurile compatibile cu profilul clientului.

Constrângerile `FOREIGN KEY` declarate separat în exporturi nu sunt aplicate automat.

Coloanele obligatorii fără valoare implicită primesc o valoare inițială numai la creare. `cod_locatie` și `locatie` primesc 1. Valorile numerice primesc 0. Textele primesc șir gol. Structurile JSON primesc `{}`.

Execuția este idempotentă. Sarcina se oprește la prima eroare, iar instrucțiunile DDL aplicate anterior rămân active.
