# Andreea Robert Shop, instalare offline

## Identificare

- Client: `22`
- Locație: `1`
- Identitate: `andreearobertshop-c22-l1-pos1`
- Aplicație: `ecogest_offline_andreearobertshop`
- API local: `api_offline_ecogest_andreearobertshop`
- Bază SQLite: `api_offline_ecogest_andreearobertshop\db_local\pos.db`
- Proiect de compilare: `compiler exe project andreearobertshop.exop`

## Componente

- `ecogest_autoscanner_products_magazin_1_4_6_0`, actualizarea nomenclatorului din online
- `ecogest_casa_marcat_v3_inp`, preluarea bonului pregătit de POS și generarea fișierului `.inp`
- `ecogest_offline_andreearobertshop\interfata_vanzare\asteapta_casa_marcat.php`, așteptarea și controlul cozii fiscale
- `ecogest_offline_andreearobertshop\interfata_vanzare\offline_fiscal_inp_alternative.php`, trimiterea directă în folderul configurat
- `api_offline_ecogest_andreearobertshop\printer_queue_atomic_helper.php`, protecția atomică a cozii fiscale
- `casa_marcat_veribon`, transmiterea răspunsurilor casei de marcat către AGECS online
- `bonuri_trimise` și `bonuri_backup`, directoare operaționale pentru casa de marcat

Instalarea nu are imprimanta BAR a clientului Lorand. Nu se livrează `printer_bold`, `imprimare_directa_lorand`, `lorand_payment_note_printer`, `lorand_z_printer` sau sincronizarea pentru tabletă.

## Identitatea și casa de marcat

Identitatea activă este clientul 22, locația 1. Configurația casei de marcat folosește:

`andreearobertshop\api_offline_ecogest_andreearobertshop\22\1\bon_casa_marcat.json`

Fișierul fiscal este preluat de aplicația `ecogest_casa_marcat_v3_inp` și scris în `andreearobertshop\bonuri_trimise`. Copia de siguranță este păstrată în `andreearobertshop\bonuri_backup`. Configurația curentă folosește sufixul `.inp` și clientul 22. Nu se utilizează căi din instalarea Lorand.

## Interfața de vânzare

Clientul 22 folosește aceleași reguli de funcționare și același design ca fluxul online `app_vanzare_v2`, cu adaptarea necesară pentru SQLite și serviciile locale.

- Sume Tură este ascuns, iar Sume Zi, Discount global, Reglare dif. CM și Închide Tura urmează condițiile online.
- Butonul Numerar afișează totalul bonului și îl actualizează după modificarea bonului.
- Protocol, discountul pe linie și modificarea numelui sau prețului produsului sunt ascunse pentru clientul 22.
- Inputul codului de bare nu folosește autocomplete, autocorrect, autocapitalizare sau spellcheck. Codul scanat este curățat de spații înainte de căutare.
- La produs negăsit se afișează alerta centrală și se redă semnalul sonor. Alerta se declanșează și dacă Enter este apăsat în afara unui câmp interactiv.
- Nu se adaugă tabul custom „PROMOȚII”, `__PROMOTII__` sau integrarea `produse_promotionale.php`.

## Flux fiscal cu așteptare

După înregistrarea vânzării, `casa_marcat_vanzare.php` publică bonul în `22\1\bon_casa_marcat.json` prin scriere temporară și redenumire atomică. Dacă există deja un bon în coadă, noua vânzare așteaptă preluarea bonului anterior și nu îl suprascrie.

Pagina `asteapta_casa_marcat.php` verifică automat coada. După cinci secunde apar două acțiuni, trimiterea directă prin `offline_fiscal_inp_alternative.php` sau continuarea fără o nouă trimitere. A doua acțiune arhivează bonul rămas în `22\1\bonuri_neprocesate`, pentru a evita o dublă fiscalizare. După cinci minute, un bon care nu a fost preluat este păstrat în același director și fluxul continuă.

Trimiterea directă revendică atomic coada, validează conținutul fiscal, elimină BOM-ul UTF-8, normalizează liniile la CRLF și scrie fișiere `.inp` în `bonuri_trimise`. Când este configurat, păstrează o copie în `bonuri_backup`. Nu folosește imprimanta BAR și nu modifică fluxul VeriBon.

## VeriBon

Aplicația `casa_marcat_veribon\AGECS.VeriBon.exe` folosește endpointul online `https://agecs.agecs.in/api/endpoint.php`.

Răspunsurile sunt preluate din:

- `api_offline_ecogest_andreearobertshop\raspunsuri_casa_marcat\BonANSWER`
- `api_offline_ecogest_andreearobertshop\raspunsuri_casa_marcat\BonERR`
- `api_offline_ecogest_andreearobertshop\raspunsuri_casa_marcat\BonOK`

După transmitere, fișierele sunt mutate în directoarele echivalente din `raspunsuri_casa_marcat_procesate`. `Data\config.json` are `ClientId` 22 și `LocationId` 1. Shortcutul de pornire este `ECOGEST_Verificare_Bon_offline_andreearobertshop.lnk`.

VeriBon este separat de coada de sincronizare a vânzărilor. Coada trimite operațiunile POS, iar VeriBon trimite răspunsurile produse de casa de marcat. Cele două fluxuri nu folosesc aceleași directoare și nu se înlocuiesc reciproc.

## Sincronizare

Sincronizarea utilizatorilor și a cotelor TVA pornește din pagina locală de autentificare și apelează `https://agecs.agecs.in/api/offline-users.php` cu clientul 22, locația 1 și modul `magazin`. Răspunsul este verificat înainte de modificarea SQLite. Sunt actualizate `admins_12`, `cote_tva`, `coduri_casa_tva` și `date_firma`.

Din `date_firma` sunt preluate și folosite local seria casei de marcat, NUI și seria memoriei fiscale. Notele goale sunt realiniate la aceste valori. Actualizarea este refuzată cât timp există un bon fiscal în așteptare sau o notă cu date de vânzare nefinalizate.

Vânzările finalizate sunt descoperite în `offline_sync_outbox`. Workerul transmite evenimentele către `api_import_operatiuni_offline.php`. Evenimentele care nu pot fi trimise din cauza rețelei rămân în `retry`, iar transmiterile abandonate sunt recuperate după expirarea perioadei de blocare. Butonul manual și workerul automat folosesc aceeași coadă.

Sincronizarea produselor este separată de sincronizarea vânzărilor. Ea folosește `sincronizare_date_offline.php`, validează nomenclatorul local și folosește certificatul CA din API-ul local. Andreea nu are schema locală pentru observațiile predefinite, prin urmare nu se importă tabelele de observații specifice Bestmixt sau Lorand.

## Separarea față de alte instalări

Bestmixt este reperul pentru sincronizarea generică, deoarece are configurație separată pentru vânzări, coadă persistentă, actualizare sigură utilizatori și TVA și verificare TLS prin certificatul local. Lorand are aceleași elemente generale, dar include reguli suplimentare pentru imprimanta BAR și tabletă.

Andreea folosește doar partea comună a sincronizării. Clientul, locația, identificatorul instalării, baza SQLite, directoarele fiscale, răspunsurile VeriBon și cheile API sunt configurate separat pentru această instalare.
