@@@ activitati_reguli_lucru/agecs_demo_client_id_8/activitati_agecs_demo_si_contabilitate_client_id_8.txt
ACTIVITĂȚI AGECS DEMO ȘI CONTABILITATE
Client AGECS: client_id 8
Export local analizat: u681731335_agecsdemo.sql
Tip client: toate
Data analizei: 22.08.2026

1. ACCES ȘI APLICAȚII

Contul oferă acces la vânzare, restaurant, hotel și meniuri dinamice.

Pentru vânzare, login.php direcționează clientul către app_vanzare_v2/agecs_login.php.

Pentru restaurant, locația 1 este direcționată către app_restaurant_v2/agecs_login.php. Locația 2 și celelalte situații folosesc app_restaurant/agecs_login.php.

Butonul Hotel deschide app_hotel/agecs_login.php. Butonul Meniuri Dinamice deschide app_vanzare_v2_meniuri_dinamice/agecs_login.php.

Exportul local are autologin_restaurant = 1 și listare_automata_comenzi_site = 1. Comunicarea ANAF este oprită. Imprimanta este activată.

2. REGULI DE LUCRU ÎN VÂNZARE

Interfața app_vanzare_v2 afișează Facturi și Import Comenzi Online. Categoria MENIURI și panoul meniu_vanzare_widget.php sunt active.

Metoda ONLINE, stocată ca glovo, este disponibilă. Citirea prin Web Serial COM1 este permisă la locația 1.

Adăugarea produselor folosește ramura specială vanzare_adaug_prod_pe_nota_1005.php. Sunt folosite elemente_bon_1005.php și serviciile de schimbare temporară sau permanentă a denumirii produsului.

Produsele care conțin CAZARE generează o taxă hotelieră de 2% din valoarea netă. Taxa este introdusă separat, cu TVA 0. Denumirile TAXA HOTELIERĂ, TAXA DE ORAȘ și TAXA ORAȘ sunt tratate ca aceeași taxă. Recalcularea șterge taxa anterioară din bon și o înlocuiește cu valoarea curentă.

Cantitățile negative sunt acceptate de ramura specială de adăugare. Ele permit corecții și stornări, inclusiv taxa hotelieră cu semn negativ.

3. REGULI DE LUCRU ÎN RESTAURANT

Interfața restaurant afișează Facturi. Pentru numerar, card și plata mixtă, când există CIF și valoarea este sub limita configurată, operatorul poate continua cu fluxul de factură automată sau îl poate omite explicit.

În app_restaurant_v2, butonul Camera este vizibil. La listarea comenzilor, pozițiile nu sunt grupate. Ordinea și fiecare apariție a produsului se păstrează.

Conținutul trimis la imprimantă este formatat cu evidențiere pentru clientul 8. La fiscalizare, unitatea de măsură este eliminată. Fișierul local de listare la imprimantă este salvat numai pentru clientul 8.

Perioada maximă permisă pentru vânzări poate fi extinsă din panoul administrativ. Regula este comună cu Mark Catering și folosește fișierul local api/8/data_ora_maxima_vanzari.json.

4. CONTABILITATE ȘI RAPOARTE

Nu există rapoarte contabile cu sufix dedicat clientului 8. Rutele standard folosesc raport_gestiune_marfuri.php, raport_gestiune_marfuri_si_productie.php, raport_gestiune_materii.php, situatia_stocurilor_marfuri.php, situatia_stocurilor_materii.php și v2_raport_productie.php.

Interfața administrativă oferă suplimentar dispozițiile de plată seria A, D și R, aceeași excepție fiind disponibilă clientului Versailles.

Închiderea trebuie verificată pe note, detalii, mișcări BF, rapoarte Z, facturi automate și comenzile importate. Taxa hotelieră rămâne linie distinctă la TVA 0 și nu trebuie inclusă în baza de cazare de două ori.

5. DIFERENȚE DE STRUCTURĂ

Exportul conține 115 tabele.

Lipsesc observatii_predefinite, prezentă în 14 din 16 exporturi, și anaf_xml_facturi, prezentă în 11 din 16 exporturi.

În produse_servicii lipsește id_categ_meniuri_personalizate, prezentă în 14 din 16 exporturi. Exportul folosește însă structura separată categ_meniuri_personalizate și meniuri_personalizate.

Structura hotelieră este cea mai completă dintre exporturile analizate. Sunt prezente camere, tipuri_camere, rezervari, rezervari_camere, hotel_setari, hotel_rezervari_facturi și hotel_rezervari_vanzari.

Sunt prezente mapare_woo_pos și woo_order_imports. Crearea anaf_xml_facturi are sens numai dacă se activează fluxul care stochează XML-urile ANAF în această tabelă.

6. FIȘIERE ACTIVE RELEVANTE

login.php
conectare.php
app_vanzare_v2/vanzare_magazin.php
app_vanzare_v2/vanzare_adaug_prod_pe_nota_1005.php
app_restaurant/vanzare_restaurant.php
app_restaurant_v2/vanzare_restaurant.php
app_restaurant_v2/vanzare_restaurant_listare_nota.php
app_restaurant_v2/casa_marcat_vanzare.php
extinde_perioada.php

@@@ activitati_reguli_lucru/daily_coffee_client_id_2/activitati_daily_coffee_si_contabilitate_client_id_2.txt
ACTIVITĂȚI DAILY COFFEE ȘI CONTABILITATE
Client AGECS: client_id 2
Export local analizat: u681731335_dailycoffee.sql
Tip client: magazin
Data analizei: 22.08.2026

1. ACCES ȘI APLICAȚIE

Butonul Vânzare din conectare.php este disponibil. login.php direcționează clientul către app_vanzare_v2/agecs_login.php.

Exportul local are comunicare_anaf = 1 și listare_automata_comenzi_site = 1. Imprimanta, modul touch și listenerul sunt oprite în setari_platforma.

2. REGULI DE LUCRU

NIR-urile sunt separate pe locații. note_receptie.php permite filtrarea după cod_locatie și afișează denumirea locației din loc_mese_12.

Rapoartele NIR standard și agregate, în PDF și Excel, includ filtrul de locație și o coloană distinctă pentru locație.

Importul operațiunilor offline este blocat pe profilul dailycoffee. Clientul 2 generează mișcări de stoc la import, remapează produsele și păstrează identificatori offline pentru note, NIR-uri și mișcări. Locația 2 aplică transferul tehnic de materii definit de profilul Daily Coffee.

Codul vechi din app_vanzare/vanzare_magazin.php conține o interfață vizuală dedicată clientului 2. Ruta curentă din login.php este app_vanzare_v2, astfel încât regula veche nu reprezintă interfața principală.

3. CONTABILITATE ȘI RAPOARTE

calcul_situatia_stocurilor.php direcționează gestiunea MĂRFURI către situatia_stocurilor_marfuri_daily.php și gestiunea MATERII către situatia_stocurilor_materii_daily.php.

Situația stocurilor de mărfuri curăță valorile de vânzare când cantitatea ajunge la zero. La 30.09.2025 aplică o ajustare valorică internă, propagată în soldurile perioadelor următoare. Pentru începutul de la 01.11.2025 este fixată continuitatea soldului valoric de 185,37 lei.

Situația stocurilor de materii normalizează CMP-ul și păstrează un reper pozitiv când stocul revine pe plus. Pentru perioadele începând cu 01.10.2025 există o forțare a soldului inițial valoric. Totalurile generale ascund cantitățile care nu au sens prin însumare între articole.

Rapoartele de gestiune rămân cele standard. Verificarea lunară trebuie făcută separat pe locații, apoi consolidată. NIR-urile, notele și mișcările importate offline trebuie urmărite prin identificator_offline pentru evitarea dublării.

4. DIFERENȚE DE STRUCTURĂ

Exportul conține 104 tabele. Nu lipsește nicio tabelă prezentă în cel puțin 8 dintre cele 16 exporturi și nicio coloană prezentă în cel puțin 8 exporturi.

Sunt prezente tabelele sincronizare_importuri_offline, sincronizare_operatori_offline, sincronizare_operatori_offline_istoric, offline_installation_state și offline_sequence_history.

Structura include bonuri_transfer_locatie și det_bonuri_transfer_locatie. Acestea susțin transferurile între locații și nu trebuie înlocuite cu bonuri_transfer, folosit într-un alt flux.

5. FIȘIERE ACTIVE RELEVANTE

login.php
note_receptie.php
raport_nir_standard_portrait.php
raport_nir_standard_landscape.php
raport_nir_excel.php
raport_nir_agregat_portrait.php
raport_nir_agregat_landscape.php
raport_nir_agregat_excel.php
calcul_situatia_stocurilor.php
situatia_stocurilor_marfuri_daily.php
situatia_stocurilor_materii_daily.php
sincronizare_online_app_vanzare/import_operatiuni_offline.php

@@@ activitati_reguli_lucru/mark_catering_client_id_3/activitati_mark_catering_si_contabilitate_client_id_3.txt
ACTIVITĂȚI MARK CATERING ȘI CONTABILITATE
Client AGECS: client_id 3
Export local analizat: u681731335_markcatering.sql
Tip client: restaurant
Data analizei: 22.08.2026

1. ACCES ȘI APLICAȚIE

Butonul Restaurant din conectare.php este disponibil. Clientul nu se află în lista clientsHP din login.php, astfel încât ruta principală este app_restaurant/agecs_login.php.

Exportul local are comunicare_anaf = 1, cu_imprimanta = 1 și listare_automata_comenzi_site = 1.

2. REGULI DE LUCRU ÎN RESTAURANT

Ecranul de autentificare oferă acces la Caiet Ospătar și la shortcutul Avize. Shortcutul Avize este permis strict clientului 3 și păstrează separat operatorul folosit pentru aviz.

Interfața afișează Facturi și Mutare produse. Notele deschise sunt filtrate după operatorul curent. Lista laterală prezintă numai notele operatorului autentificat.

La imprimarea comenzii, denumirea în limba engleză este tipărită sub produs când descriere_en este completată. Regula este aplicată atât în fluxul POS, cât și în cel de tabletă.

Produsele din interfață sunt agregate numai după încărcare. Mutarea produselor este deblocată explicit și lucrează pe bonul și masa curentă.

Pentru numerar, card și plata mixtă, un bon cu CIF poate declanșa fluxul de factură automată. Operatorul poate omite factura automată prin confirmarea prevăzută în interfață.

Perioada maximă permisă pentru vânzări poate fi extinsă din panoul administrativ prin api/3/data_ora_maxima_vanzari.json.

3. ÎNCHIDEREA TUREI ȘI FISCALIZAREA

Închiderea turei și închiderea zilei calculează distinct totalurile pe departamente și metode de plată. Sumele numerar, card, tichete, protocol, glovo și virament bancar sunt repartizate proporțional pe BAR, BUCĂTĂRIE și celelalte departamente rezultate din det_note.departament_listare sau produse_servicii.departament.

Închiderea automată generează raportul de vânzări pentru imprimanta termică. Tabela fiscalizare_raspunsuri este prezentă și permite reconcilierea răspunsurilor casei de marcat cu note.fiscalizat.

cron_email_bonuri_nefiscalizate_mark.php verifică bonurile nefiscalizate și poate transmite alertă. Scriptul este dedicat clientului 3. Nu trebuie rulat în timpul unei analize statice.

4. CONTABILITATE ȘI RAPOARTE

Nu există un raport de gestiune cu sufix Mark. Rutele administrative folosesc rapoartele standard de mărfuri, materii, producție, NIR, mișcări și rapoarte Z.

Controlul zilnic trebuie să compare raportul Z, totalurile pe departamente, totalurile pe metode de plată și marcajul fiscalizat al fiecărei note. Produsele listate în engleză nu modifică denumirea contabilă și valoarea din det_note.

Avizele folosesc avize_expeditie și avize_expeditie_linii. Bonurile de transfer folosesc bonuri_transfer și det_bonuri_transfer.

5. DIFERENȚE DE STRUCTURĂ

Exportul conține 100 de tabele. Nu lipsește nicio tabelă prezentă în cel puțin 8 exporturi și nicio coloană prezentă în cel puțin 8 exporturi.

Sunt prezente fiscalizare_raspunsuri, avize_expeditie, avize_expeditie_linii, categorii_tableta, consum_personal și det_consum_personal.

Nu sunt prezente mapare_woo_pos și woo_order_imports. Aceste tabele sunt necesare numai dacă Mark Catering va prelua comenzi WooCommerce prin fluxul folosit de Grand Plaza.

6. FIȘIERE ACTIVE RELEVANTE

login.php
app_restaurant/agecs_login.php
app_restaurant/admin_logincheck.php
app_restaurant/vanzare_restaurant.php
app_restaurant/vanzare_restaurant_listare_nota.php
app_restaurant/vanzare_listare_inchide_tura.php
app_restaurant/vanzare_listare_inchidere_zi.php
api/Tableta/trimite_comanda.php
tableta/trimite_la_imprimanta.php
cron_email_bonuri_nefiscalizate_mark.php

@@@ activitati_reguli_lucru/maya_catering_client_id_4/activitati_maya_catering_si_contabilitate_client_id_4.txt
ACTIVITĂȚI MAYA CATERING ȘI CONTABILITATE
Client AGECS: client_id 4
Export local analizat: u681731335_mayacatering.sql
Tip client: magazin
Data analizei: 22.08.2026

1. ACCES ȘI APLICAȚIE

Butonul Vânzare este disponibil. Clientul 4 nu se află în lista vanzareV2Clients, iar login.php îl direcționează către app_vanzare/agecs_login.php.

Exportul local are comunicare_anaf = 1. Imprimanta, modul touch, listenerul și activarea automată a comenzilor de pe site sunt oprite sau indisponibile în setari_platforma.

2. REGULI DE LUCRU

În fluxul de facturare, adăugarea unui produs care conține CAZARE generează automat taxa hotelieră de 2% din valoarea netă. Taxa are TVA 0 și este înlocuită la recalculare. Stornarea păstrează semnul negativ prin cantitatea taxei.

În fișierul trimis casei de marcat, unitatea de măsură este lăsată goală pentru fiecare produs. După liniile de plată este adăugată o linie T suplimentară. Aceeași regulă există în genereaza_bon.php, genereaza_bon_dep_casa_personalizat.php și în modulul de reglare a casei de marcat din interfețele v2.

Regulile din app_vanzare_v2 pentru clientul 4 nu sunt accesate prin ruta principală actuală. Ele devin active numai dacă aplicația v2 este deschisă direct sau dacă ID-ul este introdus ulterior în vanzareV2Clients.

3. CONTABILITATE ȘI RAPOARTE

Nu există rapoarte cu sufix dedicat Maya Catering. Sunt folosite rutele standard pentru gestiune mărfuri, materii, producție, NIR, situația stocurilor și rapoarte Z.

Taxa hotelieră trebuie urmărită separat la TVA 0. Valoarea cazării reprezintă baza taxei, iar linia taxei nu intră din nou în baza de calcul.

Formatul fiscal special trebuie păstrat la retrimiterea bonurilor și la reglarea diferențelor casei de marcat. Eliminarea liniei T suplimentare poate schimba comportamentul imprimantei configurate pentru acest client.

4. DIFERENȚE DE STRUCTURĂ

Exportul conține 89 de tabele, cel mai mic număr din lotul analizat.

Lipsesc observatii_predefinite, prezentă în 14 exporturi, și reguli_cmp_override, prezentă în 9 exporturi.

Lipsesc det_note.departament_listare și produse_servicii.cod_mapare_import_extern, prezente în 12 exporturi. Lipsește retururi.operator, prezentă în 12 exporturi.

Lipsesc continut_bonuri_consum.id_retur și setari_platforma.listare_automata_comenzi_site, prezente în 8 exporturi.

Crearea departament_listare este justificată dacă se dorește listarea independentă de departamentul produsului. cod_mapare_import_extern este necesară numai pentru importuri din sisteme externe. operator în retururi permite atribuirea returului unui operator. id_retur permite legarea bonului de consum de returul care l-a generat.

5. FIȘIERE ACTIVE RELEVANTE

login.php
app_vanzare/vanzare_magazin.php
add_product.php
factura.php
genereaza_bon.php
genereaza_bon_dep_casa_personalizat.php
app_vanzare_v2/reglare_casa_marcat.php
app_vanzare_v2/reglare_casa_marcat_procesare_bon.php

@@@ activitati_reguli_lucru/depo_fun_client_id_6/activitati_depo_fun_si_contabilitate_client_id_6.txt
ACTIVITĂȚI DEPO FUN ȘI CONTABILITATE
Client AGECS: client_id 6
Export local analizat: u681731335_depofun.sql
Tip client: magazin
Data analizei: 22.08.2026

1. ACCES ȘI APLICAȚIE

Butonul Vânzare este disponibil. Clientul 6 nu se află în lista vanzareV2Clients, iar ruta principală este app_vanzare/agecs_login.php.

Exportul local are comunicare_anaf = 1. Imprimanta, modul touch și listenerul sunt oprite.

2. REGULI DE LUCRU

Interfața veche afișează Verifică Stoc și Recalculează Stoc. La apăsare este apelat update_stoc_produse.php.

Lista de produse folosește load_prod_cu_stoc.php. Câmpul de cantitate pornește gol și primește automat focus, pentru introducerea explicită a cantității înainte de adăugare.

În note_receptie.php, NIR-urile pot fi filtrate după starea achitat. Coloana nir.achitat este prezentă numai în exportul Depo Fun dintre cele 16 baze analizate.

La introducerea produselor în NIR sunt disponibile cotele 9%, 19%, 11%, 21% și 0%. Pentru ceilalți clienți, formularul curent pornește numai cu 11%, 21% și 0%.

Bonul de consum manual oferă Import PVI. bon_consum_importa_pvi.php este blocat pentru orice client diferit de 6 și importă liniile unui proces verbal de inventariere în bonul de consum selectat.

Codul app_vanzare_v2 păstrează aceleași reguli de cantitate, produs cu stoc și recalculare. Ruta principală rămâne aplicația veche până la modificarea listei vanzareV2Clients.

3. CONTABILITATE ȘI RAPOARTE

Rapoartele sunt cele standard. Controlul specific include situația achitării NIR-urilor, concordanța dintre PVI și bonurile de consum importate, stocul recalculat și mișcările produse de consum.

Importul PVI într-un bon de consum nu trebuie confundat cu o intrare nouă. El generează consum pe baza liniilor inventariate selectate.

4. DIFERENȚE DE STRUCTURĂ

Exportul conține 93 de tabele. Nu lipsește nicio tabelă prezentă în cel puțin 8 exporturi.

Lipsește retururi.operator, prezentă în 12 exporturi. Lipsesc continut_bonuri_consum.id_retur și setari_platforma.listare_automata_comenzi_site, prezente în 8 exporturi.

Tabela reguli_cmp_override este prezentă. Tabela pvi_recalculari_data_ora este prezentă numai în două exporturi și corespunde operațiunilor de recalculare PVI.

operator în retururi trebuie creat numai dacă retururile vor fi urmărite pe operator. id_retur este necesară pentru legarea consumului de retur. listare_automata_comenzi_site este necesară numai la preluarea comenzilor din site.

5. FIȘIERE ACTIVE RELEVANTE

login.php
app_vanzare/vanzare_magazin.php
app_vanzare/load_prod_cu_stoc.php
note_receptie.php
nir_get_produs.php
bon_consum.php
bon_consum_importa_pvi.php
app_vanzare/update_stoc_produse.php

@@@ activitati_reguli_lucru/hotel_rin_client_id_7/activitati_hotel_rin_si_contabilitate_client_id_7.txt
ACTIVITĂȚI HOTEL RIN ȘI CONTABILITATE
Client AGECS: client_id 7
Export local analizat: u681731335_hotelrin.sql
Tip client: magazin
Data analizei: 22.08.2026

1. ACCES ȘI APLICAȚIE

Deși denumirea comercială indică activitate hotelieră, tip_client din exportul central este magazin. conectare.php afișează Vânzare, iar login.php direcționează clientul către app_vanzare/agecs_login.php.

Butonul Hotel nu este afișat pentru tip_client magazin. Schimbarea către app_hotel necesită mai întâi modificarea controlată a tipului clientului sau o regulă explicită de rutare.

Exportul local are comunicare_anaf = 0, cu_imprimanta = 0 și listare_automata_comenzi_site = 1.

2. REGULI DE LUCRU

În facturare, orice produs cu CAZARE în denumire generează taxa hotelieră de 2% din valoarea netă. Taxa are TVA 0, este introdusă separat și este recalculată la modificarea cazării.

Regula este prezentă în add_product.php și factura.php. Nu există o ramură dedicată clientului 7 în app_vanzare/vanzare_magazin.php.

3. CONTABILITATE ȘI RAPOARTE

Rapoartele sunt cele standard pentru vânzare, gestiune, NIR, stocuri, facturi și rapoarte Z. Nu există fișiere cu sufix Hotel Rin.

Taxa hotelieră se verifică la TVA 0 și trebuie să reprezinte 2% din valoarea netă a cazării. Stornarea cazării trebuie să producă taxă cu semn negativ.

Activitatea hotelieră completă nu este susținută de structura acestui export. Lipsesc camere, tipuri_camere, rezervari, rezervari_camere, hotel_setari, hotel_rezervari_facturi și hotel_rezervari_vanzari. Aceste tabele există numai în exportul Agecs Demo.

4. DIFERENȚE DE STRUCTURĂ

Exportul conține 90 de tabele.

Lipsește reguli_cmp_override, prezentă în 9 exporturi.

Lipsesc det_note.departament_listare și produse_servicii.cod_mapare_import_extern, prezente în 12 exporturi. Lipsește retururi.operator, prezentă în 12 exporturi. Lipsește continut_bonuri_consum.id_retur, prezentă în 8 exporturi.

setari_platforma.listare_automata_comenzi_site este prezentă. Nu sunt prezente mapare_woo_pos și woo_order_imports, astfel încât activarea listării automate nu asigură singură importul WooCommerce.

Structura hotelieră trebuie creată numai dacă Hotel Rin va utiliza efectiv app_hotel. Simplul calcul al taxei hoteliere în facturi nu impune aceste tabele.

5. FIȘIERE ACTIVE RELEVANTE

login.php
conectare.php
app_vanzare/vanzare_magazin.php
add_product.php
factura.php

@@@ activitati_reguli_lucru/holztreppe_client_id_14/activitati_holztreppe_si_contabilitate_client_id_14.txt
ACTIVITĂȚI HOLZTREPPE ȘI CONTABILITATE
Client AGECS: client_id 14
Export local analizat: u681731335_holz.sql
Tip client: restaurant
Data analizei: 22.08.2026

1. ACCES ȘI APLICAȚIE

Butonul Restaurant este disponibil. Clientul 14 nu se află în lista clientsHP, iar ruta principală este app_restaurant/agecs_login.php.

Ecranul de autentificare afișează accesul la Caiet Ospătar. Copyrightul este ascuns pe pagina de conectare și în antet.

Exportul local are comunicare_anaf = 1, cu_imprimanta = 1 și listare_automata_comenzi_site = 1.

2. REGULI DE LUCRU ÎN RESTAURANT

Interfața afișează Muta nota pe caiet și Muta de pe caiet. Funcțiile sunt permise numai clientului 14.

Notele temporare sunt păstrate în note_temporare și det_note_temporare. Lista administrativă Caiete temporare ospătari este vizibilă numai pentru Holztreppe.

Lista laterală afișează notele operatorului. Când există un bon activ, interfața verifică periodic starea acestuia prin vanzare_check_status.php.

3. REGULI CMP ȘI PRODUCȚIE

Exportul conține 51 de reguli active în reguli_cmp_override. Ele stabilesc valori CMP pe produs, gestiunea MATERII și dată de început. Multe reguli corectează situațiile în care CMP-ul istoric a devenit negativ.

raport_productie_holz.php calculează recursiv costul produsului finit din rețetă. Materiile și mărfurile sunt evaluate la CMP istoric. Dacă rezultatul este zero sau negativ, clientul 14 folosește pret_achizitie din produse_servicii ca reper pozitiv.

Aceeași protecție este aplicată în raport_consum_operatori.php, reteta_continut.php și reteta_cost_cmp.php. Costul de rezervă nu trebuie folosit pentru ceilalți clienți.

4. CONTABILITATE ȘI RAPOARTE

Raportul de producție este raport_productie_holz.php.

Raportul de gestiune pentru materii este raport_gestiune_materii_pu.php. Situația stocurilor pentru materii este situatie_stocuri_materii_pu.php.

Rapoartele de mărfuri și celelalte situații folosesc rutele standard. Raportul consum operatori aplică protecția CMP a clientului 14.

Sarcini operative este disponibil. Configurația curentă include corectarea metodei de plată pentru două note din 09.08.2026, fără modificarea produselor și a mișcărilor de stoc.

Controlul lunar trebuie să compare costul rețetelor, CMP-ul rezultat, regulile override active, consumurile operatorilor și raportul de producție. O regulă CMP nouă trebuie delimitată prin produs și dată.

5. DIFERENȚE DE STRUCTURĂ

Exportul conține 103 tabele. Nu lipsește nicio tabelă sau coloană prezentă în cel puțin 8 exporturi.

Structurile note_temporare și det_note_temporare sunt unice în lot. Sunt prezente actiuni_operative, actiuni_operative_modificari_text, consum_personal, det_consum_personal, istoric_mutari_mese și mesageria internă.

reguli_cmp_override este prezentă și populată. Nu este necesară o intervenție structurală pentru regulile CMP curente.

6. FIȘIERE ACTIVE RELEVANTE

login.php
app_restaurant/agecs_login.php
app_restaurant/vanzare_restaurant.php
app_restaurant/note_temporare_common.php
raport_productie_holz.php
raport_gestiune_materii_pu.php
situatie_stocuri_materii_pu.php
raport_consum_operatori.php
reteta_continut.php
reteta_cost_cmp.php
sarcini_operative_taskuri.php

@@@ activitati_reguli_lucru/turkish_client_id_17/activitati_turkish_si_contabilitate_client_id_17.txt
ACTIVITĂȚI TURKISH ȘI CONTABILITATE
Client AGECS: client_id 17
Export local analizat: u681731335_turkish.sql
Tip client: magazin
Data analizei: 22.08.2026

1. ACCES ȘI APLICAȚIE

Butonul Vânzare este disponibil. login.php direcționează clientul către app_vanzare_v2/agecs_login.php.

Exportul local are comunicare_anaf = 0 și cu_imprimanta = 1. listare_automata_comenzi_site nu există în setari_platforma.

2. REGULI DE LUCRU ÎN VÂNZARE

Interfața afișează categoria MENIURI și include meniu_vanzare_widget.php. Butonul ONLINE este disponibil, iar Protocol este ascuns.

Căutarea după cod de bare și butonul de cântar sunt ascunse. După introducerea cantității, focusul revine la căutarea după denumire.

vanzare_adaug_meniu_pe_nota.php permite adăugarea meniurilor numai clienților 8 și 17.

3. PERIOADA FISCALĂ ȘI CMP

Rapoartele Turkish pot filtra după dată și oră calendaristică sau după interval de rapoarte Z. Ziua fiscală este stabilită prin deplasarea închiderii Z cu patru ore înapoi. Registrele de casă și bancă aplică o corecție de cinci ore la data rapoartelor Z.

turkish_z_fiscal_cache păstrează maparea perioadelor fiscale. Schimbarea numărului raportului Z are reguli distincte pentru clientul 17, iar lista Z este furnizată prin ajax_lista_rapoarte_z_turkish.php.

Exportul conține 16 reguli active în reguli_cmp_override. turkish_cmp_guard.php aplică protecție strictă începând cu 01.08.2026. Un CMP candidat care depășește de zece ori reperul sau scade sub o zecime din reper este înlocuit cu ultima valoare validă ori cu prețul de achiziție.

Pentru produsul 267, orez, CMP-ul este fixat la 10,50 în intervalul 01.07.2026 până la 01.08.2026 ora 04:00.

4. CONTABILITATE ȘI RAPOARTE

Sunt folosite raport_gestiune_marfuri_turkish.php, raport_gestiune_marfuri_si_productie_turkish.php, raport_gestiune_materii_turkish.php, raport_gestiune_sgr_turkish.php, raport_gestiune_ambalaje_turkish.php și raport_gestiune_marfuri_si_productie_si_sgr_turkish.php.

Situațiile de stoc dedicate sunt situatia_stocurilor_marfuri_turkish.php, situatia_stocurilor_materii_turkish.php și situatia_stocurilor_sgr_turkish.php. Producția folosește v2_raport_productie_turkish.php.

Pentru iunie 2026 sunt fixate ținte contabile. Soldul inițial mărfuri este 47.078,14 lei, soldul final mărfuri este 38.241,00 lei, soldul inițial materii este 18.350,48 lei, soldul final materii este 35.664,26 lei, soldul inițial SGR este 5.033,00 lei, iar soldul final SGR este 4.762,00 lei.

Țintele se aplică perioadei 01.06.2026 până la 30.06.2026 sau intervalului Z 595 până la 626, fără filtre suplimentare. Din 01.07.2026, deschiderea este preluată de la aceste ținte, iar diferențele de continuitate rămân în soldurile perioadelor ulterioare.

Rapoartele vechi de gestiune și producție completă sunt ascunse. Rapoartele Turkish reprezintă ruta activă și trebuie comparate între ele pe aceeași perioadă fiscală.

5. DIFERENȚE DE STRUCTURĂ

Exportul conține 99 de tabele.

Lipsește anaf_xml_facturi, prezentă în 11 exporturi.

Lipsesc continut_bonuri_consum.id_retur și setari_platforma.listare_automata_comenzi_site, prezente în 8 exporturi.

Sunt prezente reguli_cmp_override și tabela unică turkish_z_fiscal_cache. Sunt prezente structurile pentru meniuri personalizate și comenzile de tabletă.

Crearea anaf_xml_facturi are sens numai dacă se activează stocarea locală a XML-urilor ANAF. id_retur este necesară numai pentru consumuri generate din retur. listare_automata_comenzi_site este necesară dacă se activează importul automat de comenzi online.

6. FIȘIERE ACTIVE RELEVANTE

login.php
app_vanzare_v2/vanzare_magazin.php
app_vanzare_v2/vanzare_adaug_meniu_pe_nota.php
turkish_fiscal_period_helper.php
turkish_accounting_targets.php
turkish_cmp_guard.php
turkish_report_dispatch.php
ajax_lista_rapoarte_z_turkish.php
registru_de_casa.php
registru_banca.php
registru_casa_banca.php

@@@ activitati_reguli_lucru/agrem_prejba_srl_client_id_18/activitati_agrem_prejba_srl_si_contabilitate_client_id_18.txt
ACTIVITĂȚI AGREM PREJBA SRL ȘI CONTABILITATE
Client AGECS: client_id 18
Export local analizat: u681731335_agremprejba.sql
Tip client: magazin
Data analizei: 22.08.2026

1. ACCES ȘI APLICAȚIE

Butonul Vânzare este disponibil. login.php direcționează clientul către app_vanzare_v2/agecs_login.php.

Exportul local are comunicare_anaf = 0 și cu_imprimanta = 0. listare_automata_comenzi_site nu există în setari_platforma.

2. REGULI DE LUCRU

Discountul global este ascuns și blocat la nivel de server. Reglarea diferențelor casei de marcat este ascunsă. Metoda Protocol este indisponibilă.

Sumele turei exclud notele care au identificator_offline. Regula evită includerea operațiunilor provenite din sistemul offline în totalul interfeței curente.

Căutarea începe în câmpul cod de bare. Dacă textul introdus începe cu o cifră, load_prod.php caută numai potrivirea exactă a codului de bare, după eliminarea spațiilor și a cratimelor.

La locația 1 este disponibilă citirea directă a cântarului prin Web Serial COM1. Pentru un produs cu unitatea KG, cântarul este citit înainte de adăugarea produsului.

Interfața include ghidul local pentru comunicarea cu scannerul.

3. SINCRONIZARE OFFLINE

Importul operațiunilor offline este blocat pe profilul agremprejba. Sunt folosite sincronizare_importuri_offline, sincronizare_operatori_offline și sincronizare_operatori_offline_istoric.

Produsele, notele și liniile importate au identificator_offline și cod_locatie. Acești identificatori trebuie păstrați pentru detectarea dublurilor.

4. CONTABILITATE ȘI RAPOARTE

Nu există rapoarte cu sufix Agrem Prejba. Sunt folosite rapoartele standard de gestiune, stocuri, NIR, mișcări, facturi și rapoarte Z.

La închiderea zilnică trebuie separate vânzările curente de înregistrările importate offline. Interdicția discountului global nu exclude discounturile deja înscrise pe linii înainte de import, iar acestea trebuie verificate în det_note.

5. DIFERENȚE DE STRUCTURĂ

Exportul conține 98 de tabele.

Lipsesc anaf_xml_facturi, prezentă în 11 exporturi, și reguli_cmp_override, prezentă în 9 exporturi.

Lipsește setari_platforma.listare_automata_comenzi_site, prezentă în 8 exporturi.

Structura offline este prezentă și include tabelele de import, coloanele identificator_offline și cod_locatie. Crearea listare_automata_comenzi_site nu este necesară pentru importul offline. Câmpul se referă la comenzile provenite din site.

reguli_cmp_override trebuie creată numai dacă apar corecții CMP delimitate pe produs și dată. anaf_xml_facturi este necesară numai pentru stocarea XML-urilor ANAF.

6. FIȘIERE ACTIVE RELEVANTE

login.php
app_vanzare_v2/vanzare_magazin.php
app_vanzare_v2/load_prod.php
app_vanzare_v2/modal_discount_global.php
app_vanzare_v2/modal_ghiduri.php
sincronizare_online_app_vanzare/import_operatiuni_offline.php

@@@ activitati_reguli_lucru/casa_luanna_client_id_19/activitati_casa_luanna_si_contabilitate_client_id_19.txt
ACTIVITĂȚI CASA LUANNA ȘI CONTABILITATE
Client AGECS: client_id 19
Export local analizat: u681731335_casaluanna.sql
Tip client: restaurant
Data analizei: 22.08.2026

1. ACCES ȘI APLICAȚIE

Butonul Restaurant este disponibil. Clientul 19 nu se află în lista clientsHP, iar login.php îl direcționează către app_restaurant/agecs_login.php.

Exportul local are comunicare_anaf = 1 și cu_imprimanta = 1.

2. REGULI DE LUCRU

Administrarea operatorilor permite rangul client numai pentru client_id 19. La salvare, rangul rămâne client, lucreaza_la este fixat la restaurant, iar locația este preluată din sesiune dacă formularul nu conține o locație.

La generarea bonului fiscal, produsul cu denumirea exactă MASA SERVITA 11% folosește codul de cotă al casei de marcat 4. Regula este prezentă atât în genereaza_bon.php, cât și în genereaza_bon_dep_casa_personalizat.php.

Denumiri asemănătoare sau scrise diferit nu intră în această excepție. Schimbarea denumirii produsului poate modifica încadrarea fiscală la imprimantă.

3. CONTABILITATE ȘI RAPOARTE

Nu există rapoarte cu sufix Casa Luanna. Rutele sunt cele standard pentru restaurant, mărfuri, materii, producție, NIR, facturi și rapoarte Z.

Controlul fiscal trebuie să compare cota TVA din produs, maparea coduri_casa_tva și excepția cod 4 pentru MASA SERVITA 11%. Codul transmis casei de marcat nu schimbă direct cota și valoarea păstrate în det_note.

Rangul client trebuie separat de operatorii care încasează. Dacă acest rang este folosit numai pentru acces, nu trebuie inclus automat în rapoartele de consum pe operator.

4. DIFERENȚE DE STRUCTURĂ

Exportul conține 90 de tabele.

Lipsește reguli_cmp_override, prezentă în 9 exporturi.

Lipsesc det_note.departament_listare și produse_servicii.cod_mapare_import_extern, prezente în 12 exporturi. Lipsesc continut_bonuri_consum.id_retur și setari_platforma.listare_automata_comenzi_site, prezente în 8 exporturi.

retururi.operator este prezentă. anaf_xml_facturi este prezentă.

departament_listare este necesară dacă nota trebuie listată pe alt departament decât cel definit în nomenclator. cod_mapare_import_extern este necesară la importuri externe. reguli_cmp_override este utilă numai când există corecții CMP reale.

5. FIȘIERE ACTIVE RELEVANTE

login.php
app_restaurant/agecs_login.php
app_restaurant/vanzare_restaurant.php
operatori.php
operatori_action.php
genereaza_bon.php
genereaza_bon_dep_casa_personalizat.php

@@@ activitati_reguli_lucru/best_mixt_client_id_21/activitati_best_mixt_si_contabilitate_client_id_21.txt
ACTIVITĂȚI BEST MIXT ȘI CONTABILITATE
Client AGECS: client_id 21
Export local analizat: u681731335_bestmixt.sql
Tip client: magazin
Data analizei: 22.08.2026

1. ACCES ȘI APLICAȚIE

Butonul Vânzare este disponibil. login.php direcționează clientul către app_vanzare_v2/agecs_login.php.

Exportul local are comunicare_anaf = 0 și cu_imprimanta = 0. listare_automata_comenzi_site nu există în setari_platforma.

2. REGULI DE LUCRU

Metoda Protocol este ascunsă. După adăugarea produsului, schimbarea categoriei și actualizarea bonului, focusul revine la căutarea după cod de bare.

Sarcini operative este disponibil. Configurația conține importul vânzărilor offline din 11.08.2026. Importul creează note, detalii și mișcări BF, folosește numere noi de bon și păstrează prefixul OFFLINE-BESTMIXT-20260811.

În reguli_vanzare există o regulă activă pentru produsul 911, denumită INVENTAR, valabilă în 10.06.2026. modificator_cantitate este 1. Regula trebuie verificată înainte de refolosirea produsului în alte perioade.

3. CONTABILITATE ȘI RAPOARTE

Raportul de gestiune dedicat este raport_gestiune_marfuri_bestmixt.php. Ieșirile provin numai din mișcări BF legate de note cu fiscalizat = 1. BC, PVI, AVIZ și bonurile fără confirmare fiscală nu intră în ieșirile acestui raport.

Situația stocurilor dedicată este situatia_stocurilor_marfuri_bestmixt.php. calcul_situatia_stocurilor.php o selectează pentru gestiunea MĂRFURI.

La 30.06.2026 ora 23:59, soldul valoric este adus la 5.873,00 lei pentru TVA 11%, 5.873,01 lei pentru TVA 21% și 11.746,01 lei cumulat. Ajustarea nu este afișată ca mișcare reală, dar este propagată în soldurile următoare.

Pentru perioadele începute după schimbarea TVA din 01.08.2025, soldul inițial la 11% include istoricul de 9%, iar soldul inițial la 21% include istoricul de 19%.

Tabela fiscalizare_raspunsuri este prezentă. Înaintea raportării, răspunsurile fiscale stocate sunt reconciliate cu note.fiscalizat.

Raportul dedicat și situația stocurilor trebuie să aibă același sold final pentru aceeași perioadă și cotă TVA. Bonurile nefiscalizate rămân în afara ieșirilor până la confirmarea fiscală.

4. DIFERENȚE DE STRUCTURĂ

Exportul conține 99 de tabele.

Lipsesc anaf_xml_facturi, prezentă în 11 exporturi, și reguli_cmp_override, prezentă în 9 exporturi.

Lipsesc continut_bonuri_consum.id_retur și setari_platforma.listare_automata_comenzi_site, prezente în 8 exporturi.

Sunt prezente fiscalizare_raspunsuri, actiuni_operative, sincronizarea offline și starea instalării offline. Aceste structuri susțin regulile curente.

reguli_cmp_override nu este necesară pentru ajustarea valorică din 30.06.2026, deoarece aceasta este implementată în rapoartele dedicate. Tabela ar fi necesară numai pentru override CMP pe produse.

5. FIȘIERE ACTIVE RELEVANTE

login.php
app_vanzare_v2/vanzare_magazin.php
raport_gestiune_marfuri_bestmixt.php
situatia_stocurilor_marfuri_bestmixt.php
calcul_situatia_stocurilor.php
fiscalizare_verificare_interval_service.php
sarcini_operative.php
sarcini_operative_taskuri.php

@@@ activitati_reguli_lucru/andreea_robert_client_id_22/activitati_andreea_robert_si_contabilitate_client_id_22.txt
ACTIVITĂȚI ANDREEA ROBERT ȘI CONTABILITATE
Client AGECS: client_id 22
Export local analizat: u681731335_andreearobert.sql
Tip client: magazin
Data analizei: 22.08.2026

1. ACCES ȘI APLICAȚIE

Butonul Vânzare este disponibil. login.php direcționează clientul către app_vanzare_v2/agecs_login.php.

Exportul local are comunicare_anaf = 0 și cu_imprimanta = 0. listare_automata_comenzi_site nu există în setari_platforma.

2. REGULI DE LUCRU

Sume Tura este ascuns. Butoanele de discount, editare denumire, modificare cantitate și ștergere sunt eliminate din fiecare linie a bonului.

Butonul Numerar afișează totalul curent al bonului. Metoda Protocol este ascunsă.

Căutarea după cod de bare primește focus după încărcare, adăugare și schimbarea categoriei.

Sarcini operative este disponibil. Configurația curentă conține importul notelor finalizate din SUMECASA și BONCASA pentru 11.08.2026. Importul păstrează plățile numerar, card și tichete și creează note, detalii și mișcări BF.

3. CONTABILITATE ȘI RAPOARTE

Nu există rapoarte cu sufix Andreea Robert. Sunt folosite rapoartele standard de gestiune, stocuri, NIR, mișcări, facturi și rapoarte Z.

Restricțiile din interfață reduc corecțiile făcute de operator după adăugarea produsului. Orice corecție necesară trebuie făcută prin flux administrativ și verificată în audit_modificari_pret_produse sau în sarcina operativă relevantă.

Importul legacy trebuie verificat prin identificator_offline și prin totalurile plăților. Bonurile importate nu trebuie introduse încă o dată manual.

4. DIFERENȚE DE STRUCTURĂ

Exportul conține 96 de tabele.

Lipsește reguli_cmp_override, prezentă în 9 exporturi. Lipsește setari_platforma.listare_automata_comenzi_site, prezentă în 8 exporturi.

Sunt prezente actiuni_operative, actiuni_operative_modificari_text, audit_modificari_pret_produse, audit_stergeri_det_note și produse_servicii_sterse.

reguli_cmp_override trebuie creată numai dacă sunt necesare valori CMP fixe pe produs și dată. listare_automata_comenzi_site nu este necesară pentru importul legacy din sarcini operative.

5. FIȘIERE ACTIVE RELEVANTE

login.php
app_vanzare_v2/vanzare_magazin.php
sarcini_operative.php
sarcini_operative_taskuri.php
audit_modificari_pret_produse_helpers.php

@@@ activitati_reguli_lucru/grand_plaza_client_id_25/activitati_grand_plaza_si_contabilitate_client_id_25.txt
ACTIVITĂȚI GRAND PLAZA ȘI CONTABILITATE
Client AGECS: client_id 25
Export local analizat: u681731335_grandplaza.sql
Tip client: restaurant
Data analizei: 22.08.2026

1. ACCES ȘI APLICAȚIE

Butonul Restaurant este disponibil. La locația 1, login.php direcționează clientul către app_restaurant_v2/agecs_login.php. Alte locații folosesc app_restaurant/agecs_login.php.

Butonul Administrare este ascuns dacă sesiunea nu conține viz_admin = da.

Exportul local are comunicare_anaf = 1, cu_imprimanta = 1 și autologin_restaurant = 1. listare_automata_comenzi_site este 0.

2. REGULI DE LUCRU ÎN RESTAURANT

Discounturile sunt ascunse în bon, la împărțirea notei și în afișarea produselor. Acțiunile de tură sunt ascunse în fereastra de selectare a mesei.

Comenzile WooCommerce sunt verificate periodic din pagina de login și din restaurant. Importul folosește mapare_woo_pos și woo_order_imports.

Pentru produsul WooCommerce 20090, opțiunea Ciorbă meniului zilei fără salată este mapată la produsul POS 10017. Celelalte opțiuni folosesc maparea standard.

În listarea de bucătărie, produsele cu mai multe departamente care includ BUCĂTĂRIE rămân pe linii individuale. Regula este aplicată și comenzilor venite de pe tabletă.

Pentru produsul POS 447, alegerile WooCommerce MENIUL ZILEI COMPLET și FEL 2 MEN ZILEI rămân poziții separate, cu denumirea și prețul propriu.

Bonurile și comenzile sunt tipărite cu evidențiere. La plata pe protocol este blocată listarea suplimentară duplicată.

3. CONTABILITATE ȘI RAPOARTE

Nu există rapoarte contabile cu sufix Grand Plaza. Sunt folosite rutele standard de gestiune mărfuri, materii, producție, stocuri, NIR, rapoarte Z și registre.

Controlul zilnic trebuie să compare comenzile Woo importate, maparea produselor, liniile din det_note, nota finalizată și raportul Z. woo_order_imports previne dublarea importului aceleiași comenzi.

Separarea pe departamente trebuie păstrată pentru produsele BAR,BUCĂTĂRIE. Gruparea acestor linii poate reduce numărul de bonuri de producție sau poate trimite o comandă incompletă către bucătărie.

4. DIFERENȚE DE STRUCTURĂ

Exportul conține 107 tabele.

În produse_servicii lipsește id_categ_meniuri_personalizate, prezentă în 14 exporturi. Exportul are însă categ_meniuri_personalizate, meniuri_personalizate și produse_meniu_categ.

Sunt prezente mapare_woo_pos, woo_order_imports, fiscalizare_raspunsuri, atribuiri_observatii_produse, carduri, evenimente și mesageria internă.

Structura necesară fluxului WooCommerce este prezentă. id_categ_meniuri_personalizate trebuie creată numai dacă aplicația va lega direct produsul de categoria meniului prin această coloană.

5. FIȘIERE ACTIVE RELEVANTE

login.php
conectare.php
app_restaurant_v2/agecs_login.php
app_restaurant_v2/vanzare_restaurant_listare_nota.php
app_restaurant_v2/vanzare_imparte_nota_complex.php
app_restaurant_v2/woo_check_comenzi_noi.php
app_restaurant_v2/includes/woo_sync_helpers.php
api/Tableta/trimite_comanda.php
tableta/trimite_la_imprimanta.php

@@@ activitati_reguli_lucru/coche_house_sibiu_client_id_1006/activitati_coche_house_sibiu_si_contabilitate_client_id_1006.txt
ACTIVITĂȚI COCHE HOUSE SIBIU ȘI CONTABILITATE
Client AGECS: client_id 1006
Export local analizat: u681731335_cochethouse.sql
Tip client: magazin
Data analizei: 22.08.2026

1. ACCES ȘI APLICAȚIE

Butonul Vânzare este disponibil. login.php direcționează clientul către app_vanzare_v2/agecs_login.php.

Imaginea copyright este ascunsă pe pagina de conectare.

Exportul local are comunicare_anaf = 1 și cu_imprimanta = 0. listare_automata_comenzi_site nu există în setari_platforma.

2. REGULI DE LUCRU

Interfața folosește ramura specială vanzare_adaug_prod_pe_nota_1005.php, elemente_bon_1005.php și schimbarea denumirii produsului.

Produsele cu CAZARE în denumire generează taxa hotelieră de 2% din valoarea netă. Taxa este recalculată pe bon, are TVA 0 și poate avea semn negativ la stornare.

Aceeași regulă se aplică facturilor create din interfața administrativă prin add_product.php și factura.php.

Endpointul pentru modificarea permanentă a denumirii păstrează o listă de clienți mai restrânsă decât endpointul de modificare temporară. Pentru clientul 1006 trebuie verificat dacă schimbarea permanentă este permisă înainte de folosirea ei ca regulă operațională.

3. CONTABILITATE ȘI RAPOARTE

Nu există rapoarte cu sufix Coche House. Sunt folosite rutele standard de gestiune, stocuri, NIR, facturi și rapoarte Z.

Taxa hotelieră se verifică separat la TVA 0. Valoarea ei trebuie să fie 2% din baza netă a cazării, fără includerea taxei în propria bază.

Exportul conține facturi_vanzare_spv și continut_facturi_vanzare_spv. Aceste tabele păstrează documentele de vânzare preluate din SPV și sunt prezente numai în două exporturi.

4. DIFERENȚE DE STRUCTURĂ

Exportul conține 92 de tabele.

Lipsește reguli_cmp_override, prezentă în 9 exporturi.

Lipsesc det_note.departament_listare, produse_servicii.cod_mapare_import_extern și retururi.operator, prezente în 12 exporturi. Lipsesc continut_bonuri_consum.id_retur și setari_platforma.listare_automata_comenzi_site, prezente în 8 exporturi.

Nu există structura hotelieră completă cu camere și rezervări. Taxa hotelieră din vânzare nu depinde de aceste tabele.

cod_mapare_import_extern este necesară numai pentru maparea produselor din surse externe. departament_listare este necesară pentru rutarea imprimării pe un departament diferit. operator în retururi permite responsabilizarea retururilor.

5. FIȘIERE ACTIVE RELEVANTE

login.php
conectare.php
app_vanzare_v2/vanzare_magazin.php
app_vanzare_v2/vanzare_adaug_prod_pe_nota_1005.php
app_vanzare_v2/vanzare_update_product_name_1005.php
add_product.php
factura.php

@@@ activitati_reguli_lucru/korn_atelier_client_id_1007/activitati_korn_atelier_si_contabilitate_client_id_1007.txt
ACTIVITĂȚI KORN ATELIER ȘI CONTABILITATE
Client AGECS: client_id 1007
Export local analizat: u681731335_kornatelier.sql
Tip client: magazin
Data analizei: 22.08.2026

1. ACCES ȘI APLICAȚIE

Butonul Vânzare este disponibil. login.php direcționează clientul către app_vanzare_v2/agecs_login.php.

Exportul local are comunicare_anaf = 1, cu_imprimanta = 0 și listare_automata_comenzi_site = 1.

2. IMPORTUL COMENZILOR ONLINE

Interfața app_vanzare_v2 afișează Import Comenzi Online numai clienților 1007 și 8.

api_import_comenzi_korn/import_woocommerce.php primește o comandă WooCommerce, validează cheia API, normalizează comanda și produsele, apoi scrie antetul în comenzi și liniile în det_comenzi. O comandă cu același ID este respinsă.

vanzare_importa_comenzi_online.php preia comenzile locale din comenzi și det_comenzi. Comenzile anulate, eșuate, rambursate, cu mapare incompletă sau marcate cu eroare nu pot fi importate.

Importul creează ori reutilizează bonul curent, adaugă liniile în det_note și marchează comanda cu importata_pos, nr_bon_pos, operator_import, locatie_import și data_import_pos. Metoda de plată este aleasă de operator la finalizarea bonului.

Produsele sunt mapate prin cod_produs. O linie fără produs valid trebuie blocată înainte de finalizarea importului.

3. CONTABILITATE ȘI RAPOARTE

Nu există rapoarte cu sufix Korn Atelier. Sunt folosite rapoartele standard pentru gestiune, stocuri, NIR, facturi, mișcări și rapoarte Z.

Controlul zilnic trebuie să compare comanda online, det_comenzi, bonul POS rezultat, det_note și metoda de plată aleasă. Marcajul importata_pos împiedică preluarea repetată a aceleiași comenzi.

Comanda online nu generează ieșire de stoc înainte de finalizarea bonului. Mișcările BF se verifică după închiderea notei.

4. DIFERENȚE DE STRUCTURĂ

Exportul conține 90 de tabele.

Lipsește anaf_xml_facturi, prezentă în 11 exporturi. Lipsește continut_bonuri_consum.id_retur, prezentă în 8 exporturi.

Tabela comenzi conține toate coloanele cerute de importul POS: importata_pos, nr_bon_pos, operator_import, locatie_import, data_import_pos, telefon_client, metoda_plata și nume_client. det_comenzi conține cod_produs și valorile necesare importului.

mapare_woo_pos și woo_order_imports nu sunt prezente. Fluxul Korn nu le folosește, deoarece maparea este transmisă direct prin cod_produs și starea importului este păstrată în comenzi.

anaf_xml_facturi trebuie creată numai dacă se activează stocarea XML-urilor ANAF. id_retur este necesară numai pentru bonuri de consum generate din retur.

5. FIȘIERE ACTIVE RELEVANTE

login.php
app_vanzare_v2/vanzare_magazin.php
app_vanzare_v2/vanzare_importa_comenzi_online.php
api_import_comenzi_korn/import_woocommerce.php
api_import_comenzi_korn/import_woocommerce_helpers.php

@@@ activitati_reguli_lucru/diferente_structura_baze_date_clienti.txt
DIFERENȚE DE STRUCTURĂ ÎNTRE BAZELE DE DATE ALE CLIENȚILOR
Sursă: 16 exporturi SQL locale din baze_date_clienti
Data comparației: 22.08.2026

1. REGULA DE COMPARAȚIE

Comparația folosește numai instrucțiunile CREATE TABLE din exporturile locale. Nu a fost deschisă nicio conexiune MySQL.

O lipsă majoritară este o tabelă sau coloană prezentă în cel puțin 8 dintre cele 16 exporturi. Structurile prezente la puțini clienți sunt tratate separat, deoarece aparțin unor funcții dedicate.

2. NUMĂRUL DE TABELE

AGECS Demo, client 8: 115
Daily Coffee, client 2: 104
Mark Catering, client 3: 100
Maya Catering, client 4: 89
Depo Fun, client 6: 93
Hotel Rin, client 7: 90
Holztreppe, client 14: 103
Turkish, client 17: 99
Agrem Prejba, client 18: 98
Casa Luanna, client 19: 90
Best Mixt, client 21: 99
Andreea Robert, client 22: 96
Grand Plaza, client 25: 107
Coche House Sibiu, client 1006: 92
Korn Atelier, client 1007: 90
Hanul Patrisiei, client 9: 115

3. TABELE MAJORITARE ABSENTE

AGECS Demo: observatii_predefinite, prezentă în 14 exporturi. anaf_xml_facturi, prezentă în 11 exporturi.

Agrem Prejba: anaf_xml_facturi, prezentă în 11 exporturi. reguli_cmp_override, prezentă în 9 exporturi.

Andreea Robert: reguli_cmp_override, prezentă în 9 exporturi.

Best Mixt: anaf_xml_facturi, prezentă în 11 exporturi. reguli_cmp_override, prezentă în 9 exporturi.

Casa Luanna: reguli_cmp_override, prezentă în 9 exporturi.

Coche House Sibiu: reguli_cmp_override, prezentă în 9 exporturi.

Daily Coffee: nicio tabelă majoritară absentă.

Depo Fun: nicio tabelă majoritară absentă.

Grand Plaza: nicio tabelă majoritară absentă.

Holztreppe: nicio tabelă majoritară absentă.

Hotel Rin: reguli_cmp_override, prezentă în 9 exporturi.

Korn Atelier: anaf_xml_facturi, prezentă în 11 exporturi.

Mark Catering: nicio tabelă majoritară absentă.

Maya Catering: observatii_predefinite, prezentă în 14 exporturi. reguli_cmp_override, prezentă în 9 exporturi.

Turkish: anaf_xml_facturi, prezentă în 11 exporturi.

4. COLOANE MAJORITARE ABSENTE

AGECS Demo: produse_servicii.id_categ_meniuri_personalizate, prezentă în 14 exporturi.

Agrem Prejba: setari_platforma.listare_automata_comenzi_site, prezentă în 8 exporturi.

Andreea Robert: setari_platforma.listare_automata_comenzi_site, prezentă în 8 exporturi.

Best Mixt: continut_bonuri_consum.id_retur și setari_platforma.listare_automata_comenzi_site, prezente în 8 exporturi.

Casa Luanna: det_note.departament_listare și produse_servicii.cod_mapare_import_extern, prezente în 12 exporturi. continut_bonuri_consum.id_retur și setari_platforma.listare_automata_comenzi_site, prezente în 8 exporturi.

Coche House Sibiu: det_note.departament_listare, produse_servicii.cod_mapare_import_extern și retururi.operator, prezente în 12 exporturi. continut_bonuri_consum.id_retur și setari_platforma.listare_automata_comenzi_site, prezente în 8 exporturi.

Daily Coffee: nicio coloană majoritară absentă.

Depo Fun: retururi.operator, prezentă în 12 exporturi. continut_bonuri_consum.id_retur și setari_platforma.listare_automata_comenzi_site, prezente în 8 exporturi.

Grand Plaza: produse_servicii.id_categ_meniuri_personalizate, prezentă în 14 exporturi.

Holztreppe: nicio coloană majoritară absentă.

Hotel Rin: det_note.departament_listare, produse_servicii.cod_mapare_import_extern și retururi.operator, prezente în 12 exporturi. continut_bonuri_consum.id_retur, prezentă în 8 exporturi.

Korn Atelier: continut_bonuri_consum.id_retur, prezentă în 8 exporturi.

Mark Catering: nicio coloană majoritară absentă.

Maya Catering: det_note.departament_listare, produse_servicii.cod_mapare_import_extern și retururi.operator, prezente în 12 exporturi. continut_bonuri_consum.id_retur și setari_platforma.listare_automata_comenzi_site, prezente în 8 exporturi.

Turkish: continut_bonuri_consum.id_retur și setari_platforma.listare_automata_comenzi_site, prezente în 8 exporturi.

5. LEGĂTURA DINTRE LIPSĂ ȘI FUNCȚIE

observatii_predefinite păstrează observații reutilizabile pentru produse și comenzi.

anaf_xml_facturi păstrează XML-uri ANAF. Comunicarea ANAF poate funcționa prin alte tabele și fișiere, astfel încât absența ei nu dovedește singură o eroare.

reguli_cmp_override permite fixarea CMP-ului pe produs, gestiune și dată. Este necesară numai pentru clienții cu excepții CMP controlate.

det_note.departament_listare separă departamentul de imprimare de departamentul implicit al produsului.

produse_servicii.cod_mapare_import_extern leagă produsul AGECS de un cod dintr-un sistem extern.

retururi.operator atribuie returul unui operator.

continut_bonuri_consum.id_retur leagă o linie de consum de returul sursă.

setari_platforma.listare_automata_comenzi_site controlează listarea automată a comenzilor venite din site.

produse_servicii.id_categ_meniuri_personalizate leagă direct produsul de categoria meniului personalizat. Unele baze folosesc tabele de legătură separate.

6. STRUCTURI SPECIALIZATE

actiuni_operative și actiuni_operative_modificari_text sunt prezente la Andreea Robert, Best Mixt, Hanul Patrisiei și Holztreppe.

sincronizare_importuri_offline este prezentă la Agrem Prejba, Best Mixt și Daily Coffee.

fiscalizare_raspunsuri este prezentă la Best Mixt, Grand Plaza și Mark Catering.

mapare_woo_pos și woo_order_imports sunt prezente la AGECS Demo, Grand Plaza și Hanul Patrisiei.

Structura hotelieră completă este prezentă numai la AGECS Demo.

note_temporare și det_note_temporare sunt prezente numai la Holztreppe.

turkish_z_fiscal_cache este prezentă numai la Turkish.

Tabelele de corecții și închideri cu sufix hanul sunt dedicate clientului 9 și nu trebuie replicate fără adoptarea fluxurilor corespunzătoare.

7. ORDINEA DECIZIEI DE ACTUALIZARE

Prima verificare privește codul care va folosi structura. O coloană se creează numai dacă fluxul este activ pentru client.

A doua verificare privește datele inițiale și valorile implicite. O tabelă goală poate produce rezultate diferite față de lipsa tabelei dacă scriptul verifică numai existența ei.

A treia verificare privește indexurile, cheile și istoricul. CREATE TABLE trebuie preluat dintr-un export compatibil cu același modul, nu reconstruit numai din denumirea coloanelor.

A patra verificare privește raportarea. După actualizare trebuie comparate soldurile inițiale, intrările, ieșirile, soldurile finale și numărul documentelor înainte și după schimbare.
