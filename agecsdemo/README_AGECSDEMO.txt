ECOGEST POS OFFLINE AGECSDEMO
Client 8, locația 1. Instalare de MAGAZIN, derivată din Lorand la 04.09.2026.

DESCHIDERE ȘI COMPILARE
Admin Login.url deschide interfața prin localhost, fără compilare.
Surse: ecogest_offline_agecsdemo\interfata_vanzare.
Proiect: compiler exe project agecsdemo.exop.
Destinație build: ecogest_offline_agecsdemo\ECOGEST POS OFFLINE AGECSDEMO.exe.
Data rămâne lângă executabil. Executabilul POS Lorand nu a fost copiat.
Proiectul nou are căile și identificatorii proprii. Sunt incluse schema
SQLite versionată și endpointurile de trimitere alternativă.
Înainte de build se actualizează fișierele din proiect. Nu s-a rulat build.

CONFIGURARE ȘI DATE
Configurația externă este config_offline_agecsdemo.json.
Cheia API proprie este configurată, inclusiv în resursa criptată a
AutoScannerului de produse. URL-urile online rămân pe agecs.agecs.in.
API local: api_offline_ecogest_agecsdemo.
Baza activă: api_offline_ecogest_agecsdemo\db_local\pos.db.
Nomenclator inițial: 1.117 produse Lorand, categoriile, TVA, sinonimele,
regulile și observațiile existente la momentul copierii.
Operatorii de magazin pentru locația 1 și datele firmei provin din exportul
local AGECSDEMO din 22.08.2026. Sunt doi operatori. Se actualizează prin
butonul Utilizatori și TVA. Nu sunt folosite conturile operatorilor Lorand.
Sincronizarea automată va prelua produsele din online pentru clientul 8.
Aceasta poate actualiza nomenclatorul Lorand păstrat inițial în copie.
Nu au fost copiate tranzacții, istoric, cozi, licență, identitate hardware
sau stări de sincronizare. Identitatea instalării este distinctă.
Licența trebuie validată pentru clientul 8 pe calculatorul de instalare.

FUNCȚII PĂSTRATE DIN LORAND
Login modern, sincronizare produse și observații, utilizatori și TVA,
istoric sincronizare, trimitere automată și curățare locală cu preview.
Rapoarte, istoric PROTOCOL și configurator imprimantă în interfața de vânzare.
Numerar, Card, Mix, PROTOCOL și ONLINE. Câmpul intern glovo rămâne ONLINE.
Notă de plată BAR după generarea fișierului fiscal, inițial două exemplare.
La închiderea zilei, documente separate pentru închidere, produse vândute
și produse PROTOCOL, după regulile Lorand.
Așteptare fiscală și imprimantă, revendicare atomică și protecție la dublare.
Metode alternative după 5 secunde. INP fără BOM, listare RAW fără HTML
și diacritice, cu avans de hârtie și tăiere. Continuare fără retrimitere.
Fără coduri de bare și fără cântar, exact ca Lorand.
Nu se activează excepțiile istorice pentru clientul 8 privind meniurile,
facturile, editarea specială de produse, cantitățile negative sau recalculul T.
Optimizările Lorand sunt păstrate: schemă SQLite versionată, WAL la migrare,
indexuri, ensure fără DDL repetat, cereri AJAX anulate/versionate, cache
limitat și eliberarea sesiunii pe endpointurile de citire.
Fișierele și funcțiile interne cu numele lorand rămân pentru compatibilitate.
Nu indică instalarea Lorand. Condițiile dedicate verifică acum clientul 8.

UTILITARE ȘI PACHET
AutoScanner produse, scanner fiscal și printer_bold sunt copiate din Lorand.
Configurațiile și shortcuturile indică noul folder și clientul 8.
Imprimanta Windows configurată este BAR. Se selectează numele real din
configurator pe calculatorul de instalare, dacă diferă.
Folder INP: agecsdemo\bonuri_trimise. Backup: agecsdemo\bonuri_backup.
arhiveaza_agecsdemo.bat, din rădăcina instalărilor, folosește scriptul comun.
Necesită mai întâi EXE-ul propriu și Data. Include baza locală, API-ul,
configurarea, utilitarele și shortcuturile. Exclude sursele PHP ale POS,
proiectul .exop, shortcutul Admin Login și stările operaționale excluse
de scriptul comun. Fișierul vechi de import destinat serverului online,
care nu este folosit de fluxul offline, nu a fost preluat în această copie.
Nu înlocui o bază activă cu această bază inițială. Pentru un client nou,
împachetează înainte de a crea tranzacții locale.

VERIFICARE
Inspecție statică și verificare sintactică PHP, fără pornirea aplicației,
fără teste funcționale, fără bonuri de probă și fără acces la baza online.
