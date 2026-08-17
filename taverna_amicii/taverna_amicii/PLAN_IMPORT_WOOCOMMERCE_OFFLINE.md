# Plan import comenzi WooCommerce în aplicația offline

## Obiectiv

Aplicația offline Taverna Amicii va importa comenzile WooCommerce direct de pe `https://pizza-sibiu-amicii.ro/`. Fluxul va fi construit după mecanismul funcțional Grand Plaza, adaptat pentru clientul 1008, locația 1 și baza locală SQLite.

Sincronizarea notelor finalizate, închiderilor de tură, rapoartelor Z, produselor și celorlalte date AGECS nu face parte din această modificare. Mecanismele existente de sincronizare rămân neschimbate.

## Starea planului și dependența pentru continuare

Implementarea este amânată până la furnizarea codului pluginului instalat pe site-ul online Taverna Amicii. Pluginul este, în cea mai mare parte, același cu cel utilizat la Grand Plaza și va reprezenta baza analizei pentru endpointuri, autentificare, structura comenzilor, statusuri și confirmări.

După primirea codului se vor compara punctual funcțiile existente cu necesarul clientului 1008. Se vor păstra regulile reutilizabile și se vor elimina configurațiile specifice Grand Plaza, inclusiv domeniul, cheile, identificatorii produselor, transportul și condițiile dependente de client. Nu se va începe implementarea endpointurilor prin presupunerea structurii pluginului înainte de această verificare.

## Arhitectură

1. Pe site-ul Taverna Amicii se instalează un plugin WordPress cu endpointuri REST dedicate.
2. Aplicația offline interoghează periodic site-ul pentru comenzi WooCommerce noi sau actualizate.
3. Comenzile sunt salvate mai întâi într-un inbox local SQLite.
4. Produsele WooCommerce sunt asociate produselor POS locale printr-un tabel de mapare.
5. Operatorul poate importa comanda într-o notă existentă sau într-o notă nouă, după regulile interfeței de restaurant.
6. Importul notei și al produselor se face într-o singură tranzacție SQLite.
7. Confirmarea către site se trimite numai după finalizarea tranzacției locale.
8. Identificatorul WooCommerce rămâne înregistrat local pentru prevenirea importurilor duplicate.

## Endpointuri WordPress propuse

```text
GET  /wp-json/agecs-offline/v1/orders
GET  /wp-json/agecs-offline/v1/orders/{order_id}
POST /wp-json/agecs-offline/v1/orders/{order_id}/acknowledge
```

Endpointul de listare va accepta filtre pentru data ultimei verificări, status, pagină, număr de rezultate și comenzile neconfirmate de instalație.

Răspunsul pentru o comandă va conține cel puțin:

- identificatorul comenzii și data actualizării
- statusul WooCommerce
- produsele, variațiile, cantitățile și prețurile
- reducerile și taxele aplicate
- costul și metoda de livrare
- metoda de plată
- datele clientului și adresa de livrare
- observațiile clientului
- totalurile comenzii

Identificatorii comenzilor, produselor și variațiilor vor fi transmiși ca șiruri sau valori compatibile cu numere pe 64 de biți. Nu se vor presupune valori compatibile exclusiv cu `Int32`.

## Securitate

Pluginul va expune numai datele necesare importului. Aplicația offline nu va primi cheia administrativă WooCommerce și nu va accesa direct `wc/v3/orders`.

Autentificarea se va face printr-o cheie dedicată clientului 1008 și instalației din locația 1. Cheia va fi transmisă într-un antet HTTP. Endpointurile vor folosi HTTPS și vor refuza cererile neautorizate.

## Schema SQLite necesară

La implementare se vor adăuga operații `ensure schema`, idempotente, pentru următoarele structuri:

- inboxul comenzilor WooCommerce
- liniile și metadatele comenzilor
- maparea produselor și variațiilor WooCommerce la produsele POS
- evidența importurilor finalizate
- confirmările care trebuie retrimise către site
- starea ultimei verificări
- jurnalul erorilor de preluare, mapare, import și confirmare

Cheia unică pentru prevenirea duplicatelor va include sursa, identificatorul WooCommerce, clientul 1008 și locația 1.

## Preluarea comenzilor

Verificarea automată va putea rula din loginul de alegere a ospătarului, interfața ospătarului și interfața șefului de sală. Se va folosi un mecanism de blocare locală pentru ca două pagini deschise simultan să nu execute aceeași preluare.

Preluarea va folosi paginare și un cursor bazat pe momentul actualizării comenzii. În lipsa internetului, comenzile locale existente rămân disponibile. Verificarea se reia automat după revenirea conexiunii.

## Maparea produselor

Mecanismul de mapare va urma regulile Grand Plaza, fără copierea domeniului, cheilor sau identificatorilor de produse ai acelui client.

Maparea va putea diferenția:

- produsul WooCommerce simplu
- produsul variabil și variația selectată
- opțiunile și adaosurile care trebuie transformate în produse POS
- produsele folosite pentru transport
- produsele inactive sau inexistente local

Importul va fi oprit dacă o linie obligatorie nu are mapare. Interfața va afișa produsul WooCommerce care trebuie asociat, fără a crea automat un produs POS cu date incomplete.

## Importul într-o notă

Înainte de import se verifică dacă identificatorul WooCommerce a fost deja procesat. Dacă există un import finalizat, comanda nu se introduce din nou.

În tranzacția locală se execută următoarele operații:

1. Se selectează sau se creează nota țintă.
2. Se introduc produsele mapate și cantitățile.
3. Se aplică prețurile, TVA-ul și reducerile potrivit regulilor aplicației de restaurant.
4. Se adaugă transportul când comanda este pentru livrare.
5. Se salvează observațiile și datele relevante ale comenzii.
6. Se recalculează totalurile notei.
7. Se actualizează masa sau contextul notei.
8. Se înregistrează importul WooCommerce ca finalizat.

Dacă o operație eșuează, tranzacția se anulează integral. Comanda rămâne în inbox cu eroarea aferentă și poate fi reluată după remediere.

## Listare și notificări

Interfața va putea afișa numărul comenzilor noi, starea ultimei verificări și erorile de mapare. Notificarea sonoră și listarea automată la BAR vor urma regulile Grand Plaza, activate separat prin configurator pentru clientul 1008.

O comandă nu va fi considerată importată doar pentru că a fost listată. Starea de listare și starea de import vor fi păstrate separat.

## Confirmarea către site

După importul local complet, aplicația trimite confirmarea către endpointul `acknowledge`. Dacă această cerere eșuează, confirmarea intră într-o coadă locală și este retrimisă ulterior. Comanda nu se importă din nou, deoarece evidența locală reprezintă sursa de control pentru idempotentă.

Confirmarea poate include identificatorul instalației, data importului, nota locală și rezultatul procesării. Site-ul nu va șterge comanda WooCommerce.

## Configurații necesare înainte de implementare

- cheia API dedicată importului WooCommerce
- statusurile WooCommerce care trebuie preluate
- maparea produselor și variațiilor la nomenclatorul POS
- produsele POS folosite pentru taxele de transport
- regula pentru alegerea sau crearea notei
- activarea listării automate la BAR
- activarea notificării sonore
- intervalul de verificare automată

## Verificări de acceptanță

- aceeași comandă nu poate fi importată de două ori
- o eroare la un produs anulează întregul import al notei
- lipsa internetului nu blochează funcționarea restaurantului
- revenirea conexiunii reia preluarea și confirmările restante
- două interfețe deschise simultan nu procesează aceeași comandă
- o comandă modificată pe site este detectată fără dublarea produselor deja importate
- valorile mari ale identificatorilor nu produc erori de conversie `Int32`
- listarea la BAR nu marchează implicit comanda ca importată
- mecanismele AGECS existente de sincronizare rămân nemodificate
