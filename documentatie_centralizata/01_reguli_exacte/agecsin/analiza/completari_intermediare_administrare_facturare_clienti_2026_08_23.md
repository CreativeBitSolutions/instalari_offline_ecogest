@@@ activitati_reguli_lucru/agecs_demo_client_id_8/activitati_agecs_demo_si_contabilitate_client_id_8.txt
header.php nu afișează imaginea copyright pentru clientul 8. factura.php, add_product.php și listeaza_fact.php nu conțin o ramură administrativă dedicată clientului 8. Facturarea manuală folosește fluxul comun, inclusiv pregătirea și validarea artefactelor XML ANAF. Taxa hotelieră din fluxurile POS nu trebuie confundată cu factura manuală, unde regula automată din add_product.php este limitată la clienții 4, 7, 1005 și 1006.

@@@ activitati_reguli_lucru/daily_coffee_client_id_2/activitati_daily_coffee_si_contabilitate_client_id_2.txt
În PDF-ul facturii, listeaza_fact.php mărește zona maximă a siglei la 65 pe 32 mm. Ceilalți clienți folosesc limita de 50 pe 20 mm. Conținutul și calculele facturii rămân cele comune.

Documentele NIR complete și simplificate nu afișează blocul de observații pentru clientul 2. Zona de semnături este redusă la CONTABIL, MANAGER și GESTIONAR. Rapoartele NIR standard și agregate păstrează suplimentar locația în filtre, grupare și coloanele PDF sau Excel.

@@@ activitati_reguli_lucru/mark_catering_client_id_3/activitati_mark_catering_si_contabilitate_client_id_3.txt
La emiterea unei facturi din restaurant cu metoda de plată 42, vanzare_factura.php selectează seria de tip simpla. Factura creată păstrează această serie încă de la inițializare.

În listeaza_fact.php, cod_mapare_import_extern este preluat din produse_servicii și este tipărit înaintea denumirii produsului. Regula se aplică numai dacă acea coloană există și codul nu este gol.

Rapoartele produse_vandute_la_interval.php și variantele Excel includ codul extern. Rapoartele pe departamente tratează codurile istorice 36, 69, 520 și 3324 ca BUCATARIE dacă departamentul curent nu poate fi determinat. Această mapare păstrează comparabilitatea vânzărilor anterioare recreării produselor.

@@@ activitati_reguli_lucru/maya_catering_client_id_4/activitati_maya_catering_si_contabilitate_client_id_4.txt
În factura.php, selectarea unui produs care conține CAZARE afișează baza netă, taxa hotelieră și totalul de booking. Prețul cu TVA al cazării devine needitabil cât timp regula este activă.

add_product.php calculează taxa de 2% din valoarea netă și caută mai întâi produsul TAXA DE ORAS, apoi TAXA HOTELIERA. Linia are TVA 0. Valorile negative sunt acceptate la stornare. Celelalte funcții administrative și listarea facturii folosesc fluxul comun.

@@@ activitati_reguli_lucru/depo_fun_client_id_6/activitati_depo_fun_si_contabilitate_client_id_6.txt
Formatele NIR complet, defalcat și simplificat ascund observațiile pentru clientul 6. Blocul de semnături conține CONTABIL, MANAGER și GESTIONAR.

factura.php, listeaza_fact.php și fluxurile ANAF nu conțin alte reguli explicite pentru clientul 6. Facturarea manuală rămâne standard.

@@@ activitati_reguli_lucru/hotel_rin_client_id_7/activitati_hotel_rin_si_contabilitate_client_id_7.txt
Factura administrativă activează panoul de cazare când denumirea produsului conține CAZARE. Sunt afișate baza netă, taxa hotelieră și totalul de booking, iar prețul cu TVA devine needitabil pe durata calculului.

add_product.php introduce separat taxa de 2% cu TVA 0 și păstrează semnul la stornare. În lipsa unei denumiri CAZARE, formularul și listarea facturii folosesc fluxul comun. Personalizarea nu activează app_hotel și nu creează rezervări sau camere.

@@@ activitati_reguli_lucru/holztreppe_client_id_14/activitati_holztreppe_si_contabilitate_client_id_14.txt
Lista facturilor de achiziție din facturi_achizitii.php exclude numerele interne 23 și 24 pentru clientul 14. Excluderea se aplică înaintea filtrelor de stare și perioadă, astfel încât documentele nu apar nici în lista completă, nici în subseturile acceptate sau neacceptate.

În header.php, raportul de gestiune a materiilor este direcționat către raport_gestiune_materii_pu.php. Raportul vechi de producție este direcționat către raport_productie_holz.php. Sarcinile operative rămân vizibile în meniul Operațiuni, iar imaginea copyright este ascunsă în antet.

Emiterea și listarea facturilor de vânzare nu au o ramură dedicată Holztreppe. Aceste documente folosesc fluxul comun.

@@@ activitati_reguli_lucru/turkish_client_id_17/activitati_turkish_si_contabilitate_client_id_17.txt
header.php elimină raportul generic de gestiune și raportul vechi de producție completă. Formularele administrative sunt direcționate către rapoartele Turkish pentru mărfuri, materii, SGR, ambalaje, producție și combinațiile dintre acestea.

Modalele de materii și stocuri permit filtrare după ora calendaristică sau după raport Z. Lista închiderilor este încărcată prin ajax_lista_rapoarte_z_turkish.php. Interfața folosește css/turkish-report-modals.css și js/turkish-report-modals.js numai pentru clientul 17.

ajax_change_nr_raport_z.php cere selectarea înregistrării concrete din rapoarte_z. Modificarea numărului actualizează raportul selectat și structurile asociate după regulile Turkish, fără a folosi ramura generică bazată numai pe numărul vechi. Registrele de casă și bancă deplasează momentul raportului Z cu 5 ore pentru atribuirea zilei fiscale.

Factura manuală și lista facturilor folosesc fluxul comun. Nu există o ramură Turkish în factura.php sau listeaza_fact.php.

@@@ activitati_reguli_lucru/agrem_prejba_srl_client_id_18/activitati_agrem_prejba_srl_si_contabilitate_client_id_18.txt
În documentele NIR complete și defalcate, secțiunea Observații este eliminată pentru clientul 18. Zona de semnături conține CONTABIL, MANAGER și GESTIONAR. Formatele simplificate nu au o ramură separată pentru acest client.

Facturarea manuală, listarea facturilor și fluxurile ANAF folosesc implementarea comună. Nu există o condiție explicită pentru clientul 18 în factura.php, add_product.php sau listeaza_fact.php.

@@@ activitati_reguli_lucru/casa_luanna_client_id_19/activitati_casa_luanna_si_contabilitate_client_id_19.txt
Factura manuală, factura din restaurant și listarea PDF nu au o ramură dedicată clientului 19. Particularitatea fiscală pentru MASA SERVITA 11% rămâne în generarea bonului fiscal și nu schimbă structura facturii.

@@@ activitati_reguli_lucru/best_mixt_client_id_21/activitati_best_mixt_si_contabilitate_client_id_21.txt
În modalul administrativ pentru gestiunea mărfurilor, header.php direcționează clientul 21 către raport_gestiune_marfuri_bestmixt.php. Celelalte formulare de gestiune și facturare rămân pe rutele comune dacă nu au o regulă Best Mixt explicită.

factura.php, listeaza_fact.php și facturi_achizitii.php nu conțin o personalizare explicită pentru clientul 21.

@@@ activitati_reguli_lucru/andreea_robert_client_id_22/activitati_andreea_robert_si_contabilitate_client_id_22.txt
Factura manuală, lista facturilor, facturile de achiziție și fluxurile ANAF folosesc implementarea comună. Nu există condiții explicite pentru clientul 22 în factura.php, add_product.php, listeaza_fact.php sau facturi_achizitii.php.

@@@ activitati_reguli_lucru/grand_plaza_client_id_25/activitati_grand_plaza_si_contabilitate_client_id_25.txt
După autorizarea administrării prin viz_admin, factura manuală, lista facturilor, facturile de achiziție și fluxurile ANAF folosesc implementarea comună.

Sincronizarea WooCommerce și opțiunile produsului 20090 sunt limitate la restaurant și la preluarea comenzilor. Ele nu schimbă seriile, calculele sau formatul facturii administrative.

@@@ activitati_reguli_lucru/coche_house_sibiu_client_id_1006/activitati_coche_house_sibiu_si_contabilitate_client_id_1006.txt
În factura.php, un produs cu CAZARE activează panoul pentru baza netă, taxa hotelieră și totalul de booking. Prețul cu TVA este blocat pentru editare cât timp calculul automat este activ.

add_product.php adaugă taxa de 2% cu TVA 0, folosind produsul TAXA DE ORAS sau TAXA HOTELIERA. Stornările păstrează valoarea negativă. Listeaza_fact.php folosește formatul comun, fără adaptarea siglei sau a codului extern aplicată clienților 2 și 3.

Imaginea copyright este ascunsă în conectare.php pentru clientul 1006. Această regulă nu schimbă drepturile funcționale din administrare.

@@@ activitati_reguli_lucru/korn_atelier_client_id_1007/activitati_korn_atelier_si_contabilitate_client_id_1007.txt
Factura manuală, listarea PDF, facturile de achiziție și fluxurile ANAF folosesc implementarea comună. api_import_comenzi_korn/import_woocommerce.php nu emite facturi și nu modifică seriile documentelor fiscale.

@@@ activitati_reguli_lucru/hanul_patrisiei_client_id_9/activitati_hanul_patrisiei_si_contabilitate_client_id_9.txt
În administrare, header.php și index.php elimină rapoartele generice care ar dubla logica dedicată. Formularele sunt direcționate către raport_gestiune_marfuri_si_productie_hanul.php, raport_gestiune_materii_hanul.php și raport_productie_hanul.php. calcul_situatia_stocurilor.php deschide situațiile de stoc dedicate materiilor și mărfurilor. Panoul principal afișează verificarea PYN cu viramentul bancar și accesul la sarcinile operative. Opțiunea Uniformizare structură BD nu este afișată pentru clientul 9.

Interfața restaurantului păstrează numărul camerei și, numai pentru clientul 9, numărul rezervării. Autentificarea POS este direcționată către tableta_app_restaurant. La listarea notei, produsele nu sunt cumulate, prețul unitar este adăugat în denumire, iar observația produsului nu este tipărită. Filtrarea categoriilor diferă între mesele de tip brățară din locația 2 și mesele simple din locația 1.

factura.php, listeaza_fact.php și facturi_achizitii.php nu conțin o ramură dedicată clientului 9. Factura administrativă și fluxurile ANAF rămân comune. Facturile PYN sunt tratate separat în rapoartele Hanul, ca documente fiscale și elemente de reconciliere, fără o a doua descărcare de gestiune.
