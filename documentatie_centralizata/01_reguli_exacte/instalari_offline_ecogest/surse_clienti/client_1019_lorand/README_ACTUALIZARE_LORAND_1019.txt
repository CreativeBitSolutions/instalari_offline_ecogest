ACTUALIZARE ECOGEST OFFLINE LORAND - client_id 1019 / locatie 1
Data: 03.09.2026

Mod de aplicare:
- continutul folderului lorand se suprascrie peste instalarea existenta lorand;
- sursele aplicatiei de magazin sunt in lorand\ecogest_offline_lorand\interfata_vanzare;
- executabilul compilat si folderul extern Data se genereaza in lorand\ecogest_offline_lorand;
- nu se modifica executabilele .exe/.dll din printer_bold si nu este necesar rebuild;
- printer_bold existent ramane compatibil, iar appsettings.json/settings.json din pachet confirma endpointul Lorand si imprimanta BAR.

Functionalitati incluse:
1. Rapoarte produse + istoric PROTOCOL pentru magazin Lorand, cu terminologie operator/magazin.
2. Loginul pastreaza PRODUSE + OBSERVATII, UTILIZATORI + TVA, TRIMITE OPERATIUNILE, ISTORIC SINCRONIZARE, VERIFICARE LICENTA si CURATARE DATE. RAPOARTE + PROTOCOL si CONFIGURARE IMPRIMANTA sunt disponibile numai in antetul vanzarii.
3. Ensure schema Lorand pe agecs_login.php offline; varianta online client_login.php/setari_lorand_schema.php era deja prezenta in codul online analizat si nu necesita schimbare in acest patch offline.
4. Configurator imprimanta Lorand: bold, dimensiune text, aliniere, latime nota, numar exemplare 1-5. Implicit: 2 exemplare, destinatie fixa BAR.
5. Dupa scrierea cu succes a bon_casa_marcat.json, pentru client 1019 se introduce automat nota de plata in coada BAR. Marcarea bonului ca preluat se face numai dupa scrierea integrala a fisierului fiscal.
6. Relistarea bonului fiscal NU genereaza inca o nota de plata BAR pentru client 1019.
7. Coada de imprimare este pe fisiere unice in print_queue, iar de_listat_la_imprimanta.php pastreaza compatibilitate si cu vechiul fisier de_listat_la_imprimanta.json.
8. Sectiunea de cod bare este ascunsa la 1019, iar listener-ele de cod bare nu se initializeaza pentru Lorand.
9. printer_bold/settings.json: ClientId 1019, Locatie 1, BarPrinter BAR.
10. Aplicatia de magazin Lorand nu foloseste i18n. Incarcarea bootstrapului i18n este optionala si nu produce warning cand folderul lipseste.
11. Endpointul de utilizatori primeste explicit app_mode=magazin, astfel incat sincronizarea sa preia operatorii Lorand, nu utilizatorii de restaurant.
12. Bonul fiscal existent nu este suprascris. Fluxul asteapta preluarea lui de aplicatia casei de marcat, apoi continua la pasul urmator.
13. Dupa listare se urmareste coada imprimantei BAR. Erorile de publicare sunt afisate fara anularea vanzarii sau a inchiderii deja salvate.
14. Inchiderea zilei publica trei fisiere distincte, in ordine: nota de inchidere, produse fara PROTOCOL, produse PROTOCOL. Fiecare se listeaza pe o foaie separata la BAR.
15. Ensure schema adauga automat in SQLite observatii_predefinite.toate_produsele cu DEFAULT 1. Daca un endpoint online vechi nu trimite coloana, sincronizarea foloseste temporar valoarea compatibila 0 si pastreaza atribuirile explicite pe produse.
16. Sincronizarea Utilizatori + TVA foloseste configurarea offline_sales_sync. Daca lipsesc codurile separate ale casei, acestea sunt derivate din cote_tva.dep_casa.

Verificari efectuate:
- php -l pe toate fisierele PHP modificate/noi;
- validare JSON pe configuratiile incluse;
- verificare statica a referintelor si a logicii 1019.

Regula de lucru respectata:
NU s-a facut conectare la baza de date, NU s-a pornit aplicatia si NU s-au executat scripturi/endpoints impotriva bazei de date. Au fost facute exclusiv citire/editare de cod si verificari statice de sintaxa/structura.
