# Taverna Amicii, instalare offline restaurant

## Identificare

- Client: `1008`
- Locație: `1`
- Sufix operațional: `12`
- Aplicație: `C:\xampp\htdocs\instalari_offline_ecogest\taverna_amicii\app_restaurant_v2`
- API local și stocare: `C:\xampp\htdocs\instalari_offline_ecogest\api_offline_taverna_amicii`
- Bază SQLite: `C:\xampp\htdocs\instalari_offline_ecogest\api_offline_taverna_amicii\restaurant.sqlite`
- Shortcut: `INTERFATA VANZARE - TAVERNA AMICII.url`

## Structura instalării

Folderul `taverna_amicii` conține aplicația de restaurant și utilitarele executabile:

- `app_restaurant_v2`, interfața de vânzare
- `scan_casa_marcat_v3_inp`, preluarea comenzilor fiscale și trimiterea lor către folderul urmărit de programul casei de marcat
- `printer_bold`, preluarea documentelor pentru imprimantele de secție
- `casa_marcat_veribon`, citirea răspunsurilor produse de casa de marcat și transmiterea lor către serviciul online

Folderul `api_offline_taverna_amicii` conține baza SQLite, endpointurile PHP locale, exporturile de sincronizare, cozile JSON și directoarele operaționale. Aplicația nu mai depinde de un folder `taverna_amicii\api` și nu folosește o legătură de compatibilitate.

## Fluxul aplicației

Shortcutul deschide autentificarea locală. Aplicația lucrează cu baza SQLite și rămâne disponibilă când conexiunea la internet lipsește. Notele deschise au statusul `S`. Finalizarea încasării schimbă statusul în `F` și leagă nota de închiderea de tură și de raportul Z.

Documentele destinate casei de marcat sunt scrise în `api_offline_taverna_amicii\1008\1\bon_casa_marcat.json`. Utilitarul `scan_casa_marcat_v3_inp` preia documentul prin endpointul local și generează fișierul în `api_offline_taverna_amicii\bonuri_trimise`. Copiile preluate sunt păstrate în `api_offline_taverna_amicii\bonuri_backup`.

Endpointul convertește explicit câmpurile `id`, `de_trimis_la_casa_marcat`, `nrbon` și `locatie` în numere întregi pe 32 de biți. Conversia este necesară deoarece PDO SQLite poate returna valorile numerice sub formă de șiruri, iar `AGECSScanCM` solicită tipul `System.Int32` pentru aceste proprietăți.

Documentele pentru imprimante sunt scrise în `api_offline_taverna_amicii\1008\1\de_listat_la_imprimanta.json`. Utilitarul `printer_bold` le preia prin endpointul local și le trimite către imprimantele configurate în `settings.json`.

Plata `PROTO` finalizează nota cu valoarea în câmpul `protocol`, apoi generează o notă suplimentară de plată pentru imprimanta `BAR`. Regula este identică aplicației online. Excepția care suprimă această listare există numai pentru clienții 25 și 26. Clientul 1008 nu intră în excepție, deci foaia PROTO se listează.

Șeful de sală poate deschide `Configurare imprimantă` din panoul propriu. Sunt disponibile îngroșarea întregului text, mărimea caracterelor și alinierea. Configurația inițială reproduce modelul Grand Plaza, cu bold activ, mărimea 11 și aliniere la stânga. Setările sunt salvate în `api_offline_taverna_amicii\printer_format.json` și sunt aplicate de endpointul local tuturor documentelor preluate de utilitarul de imprimare.

Utilitarul `casa_marcat_veribon` urmărește directoarele `BonANSWER`, `BonERR` și `BonOK` din `api_offline_taverna_amicii\raspunsuri_casa_marcat`. După transmiterea răspunsului către serviciul online, fișierul este mutat în directorul echivalent din `raspunsuri_casa_marcat_procesate`.

## Schema SQLite

La fiecare deschidere a conexiunii rulează `restaurant_sqlite_apply_schema`. Funcția creează tabelele lipsă și completează coloanele, indexurile, contextul locației și trigger-ele necesare. Sunt acoperite notele, detaliile, mișcările, bonurile, închiderile, rapoartele Z, discounturile, exporturile, jurnalele și coada persistentă de sincronizare.

`det_note.departament_listare` păstrează departamentul produsului din momentul adăugării pe notă. Relistarea, mutarea pe altă notă, împărțirea notei și restaurarea unei note PROTO păstrează această valoare. Schimbarea ulterioară a produsului în nomenclator nu redirecționează o comandă istorică spre altă imprimantă. Coloana `det_note.importat_din_site` este creată preventiv pentru compatibilitatea cu logica aplicației online.

## Compatibilitate cu aplicația online

Regulile de vânzare și listare provin din `C:\xampp\htdocs\github\agecsin\app_restaurant_v2`. Varianta locală păstrează diferențele necesare pentru SQLite, API-ul extern, serviciile locale de imprimare și coada offline. Au fost aliniate editarea observației existente, numele produsului salvat pe linia notei, departamentul istoric de listare, plata PROTO, confirmarea CIF pentru valori mai mari de 500 lei, etichetele metodelor de plată și numărul raportului Z.

La o încasare cu CIF și total mai mare de 500 lei, interfața solicită confirmarea continuării. Viramentul bancar separat, fără bon fiscal, rămâne exceptat. Regulile speciale ale clienților 3, 8, 9, 23, 25 și 26 sunt păstrate în aceleași puncte ca în aplicația online. Funcțiile care depind exclusiv de WooCommerce sau de conexiunea MySQL online rămân inactive în modul SQLite.

## Sincronizarea produselor

Aplicația nu verifică și nu importă automat produsele la autentificare. Sincronizarea automată din online spre SQLite este realizată de `autoscanneragecsproducts_restaurant_cu_api_fisier_1_4_5_0`. Actualizarea din interfața restaurantului pornește numai la apăsarea butonului `Sincronizare Produse` din pagina de autentificare.

Sincronizarea manuală folosește hashuri normalizate. Cotele TVA sunt comparate numeric, astfel încât valori echivalente precum `0` și `0.0` nu produc diferențe false.

Setul instalat conține 361 de produse, 22 de categorii, 23 de legături dintre categorii și locații, 12 gestiuni și 3 cote TVA.

## Sincronizarea vânzărilor

Sincronizarea folosește coada persistentă `offline_sync_outbox`. Workerul este pornit la aproximativ 1,5 secunde după încărcarea interfeței ospătarului, a panoului șefului de sală sau a paginii locale de alegere a operatorului și verifică din nou coada la 30 de secunde. Din pagina de autentificare, workerul fără sesiune este acceptat numai în modul SQLite și numai dacă cererea vine de pe calculatorul local. Butonul `Sync Online` rămâne disponibil pentru procesare imediată. O întrerupere de rețea păstrează evenimentul în `retry`, cu reîncercări temporizate. Erorile nerecuperabile trec evenimentul în `blocked`.

Nota cu status `F` produce imediat evenimentul `sale_finalized`, fără să aștepte `cod_inchidere` sau `nr_raport_z`. Evenimentul conține nota, detaliile și discounturile. Câmpurile de închidere și raport Z sunt zero în acest payload. Notele în lucru, cu status `S`, nu intră în coadă.

Închiderea operatorului produce evenimentul `shift_closed`. Acesta conține închiderea și notele locale asociate, iar importatorul actualizează `cod_inchidere` pe notele existente online. Generarea raportului Z produce `z_closed`, cu raportul, închiderile și notele asociate. Importatorul completează ulterior `nr_raport_z` pe închideri, note și mișcările deja generate.

Online generează mișcările numai pentru `sale_finalized`, pe baza nomenclatorului, gestiunilor și rețetelor existente online. Evenimentele `shift_closed` și `z_closed` nu regenerează rețeta. Ele actualizează numai legăturile documentelor.

Fiecare instalare are un `installation_uuid` permanent. Evenimentele au `event_uuid`, hash de conținut și identificatori stabili pentru fiecare entitate. Importatorul online folosește `offline_sync_event_inbox` pentru confirmările `processed` și `already_processed`. Tabelele `offline_sync_imported` și coloanele `identificator_offline` păstrează maparea dintre identificatorii locali și cei online. Retrimiterea aceluiași eveniment nu inserează duplicate.

Copiile JSON sunt păstrate în `api_offline_taverna_amicii\offline_sync_exports`. Rezultatul fiecărei încercări este înregistrat în `offline_sync_logs`. Cheia API rămâne numai în configurația locală.

Butonul `Status trimiteri` din panoul șefului de sală deschide registrul detaliat al sincronizării. Modalul afișează evenimentele `pending`, `sending`, `retry`, `sent` și `blocked`, notele `F` pregătite, notele care așteaptă închiderea sau raportul Z și ultimele 75 de evenimente. Comanda `Retrimite acum` reactivează inclusiv evenimentele blocate, apoi pornește procesarea cozii.

Pentru fiecare tentativă sunt păstrate declanșarea manuală sau automată, durata, codul HTTP, mesajul online, numărul de note, linii, închideri, rapoarte Z și discounturi, fișierul exportat, amprenta payloadului, elementele inserate, actualizate și duplicatele confirmate. Modalul verifică situația la deschidere, la apăsarea butonului `Actualizează`, după sincronizare și automat la 20 de secunde cât timp rămâne deschis.

## Comenzile de pe tablete

Aplicația instalată pe tablete și autentificarea ei nu au fost modificate. Tabletele continuă să folosească aplicația online din orice rețea cu acces la internet. La trimiterea unei comenzi, sistemul online păstrează antetul în `com_tableta`, detaliile în `det_com_tableta` și starea `TRIMISA`.

Instalația offline verifică la 30 de secunde endpointul `api/offline-tablet-orders.php`, folosind aceeași cheie API atribuită clientului. Endpointul validează cheia în baza centrală, determină automat baza clientului 1008 și returnează numai comenzile locației 1. Fiecare comandă conține identificatorul tabletei, ospătarul proprietar, masa transmisă, totalurile, produsele, observațiile și departamentul istoric de listare.

Comenzile sunt copiate idempotent în tabelele SQLite `com_tableta` și `det_com_tableta`. `nrbon` online rămâne identificatorul sursei. O comandă locală marcată `IMPORTATA` nu este suprascrisă dacă API-ul o returnează din nou. Identificatorii sunt procesați ca întregi pe 64 de biți, astfel încât valorile mari nu sunt trunchiate.

Ospătarul vede butonul `Comenzi Tabletă` în interfața de vânzare. Numărul comenzilor locale disponibile apare pe buton. Pagina de import verifică online la deschidere și permite o verificare manuală. Sunt afișate numai comenzile care aparțin ospătarului conectat. Comanda poate fi adăugată pe o notă deja deschisă de același ospătar sau pe o masă liberă.

Importul copiază produsele în `det_note`, recalculează totalurile, ocupă masa și păstrează marcajul `tableta=1`. Tranzacția locală marchează sursa `IMPORTATA` numai după crearea completă a notei. După commit, aplicația confirmă importul către API. Online schimbă starea în `IMPORTATA` și scrie o confirmare unică în `offline_tablet_import_receipts`.

Dacă internetul cade după importul local, comanda rămâne cu `online_ack_status=pending` sau `retry`. Workerul reia confirmarea. Dacă serverul a procesat deja confirmarea, răspunsul repetat este acceptat ca succes. Acest flux împiedică pierderea comenzii și inserarea ei de două ori într-o notă locală.

Ultima preluare, ultima confirmare, erorile, comenzile primite și istoricul încercărilor sunt păstrate în `offline_tablet_sync_runtime` și `offline_tablet_sync_logs`. Situația este inclusă în răspunsul detaliat al ferestrei `Status trimiteri`.

## Operare

1. Se pornește Apache din XAMPP.
2. Se pornesc utilitarele necesare pentru casa de marcat, imprimante și verificarea răspunsurilor fiscale.
3. Se deschide `INTERFATA VANZARE - TAVERNA AMICII.url`.
4. Se selectează operatorul și se introduce parola configurată.
5. După finalizarea unei note, interfața de restaurant pune imediat vânzarea în coadă și încearcă transmiterea fără să aștepte închiderea turei.
6. Închiderea turei și raportul Z sunt puse ulterior în aceeași coadă ca evenimente separate.
7. Starea afișată lângă butonul `Sync Online` se verifică înainte de oprirea calculatorului. Butonul poate porni imediat o nouă încercare.
8. `Status trimiteri` se folosește pentru verificarea confirmării online, a reîncercărilor, a erorilor blocate și a documentelor care așteaptă completarea.
9. Butonul `Comenzi Tabletă` se verifică atunci când o tabletă a trimis o comandă. Importul se face pe masa indicată sau pe o altă masă disponibilă a aceluiași ospătar.

## Copie de siguranță

Aplicația și Apache trebuie oprite înainte de copierea bazei. Se salvează `restaurant.sqlite` împreună cu `restaurant.sqlite-wal` și `restaurant.sqlite-shm`, dacă aceste fișiere există. Restaurarea se face în același folder API extern.

## Operatorii instalați

| ID | Nume | Rol | Locație |
|---:|---|---|---:|
| 1 | OSPATAR1 | ospatar | 1 |
| 2 | OSPATAR2 | ospatar | 1 |
| 3 | SEF SALA1 | sefsala | 1 |
| 4 | SEF SALA2 | sefsala | 1 |

Parolele hash și atributele conturilor provin din baza sursă. Valorile lor nu sunt reproduse în documentație.
