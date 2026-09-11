# Reguli comune pentru magazine

Sursa principală: `tipuri_instalari_offline.txt`, secțiunea comună pentru magazine.

- Interfața locală rulează cu SQLite externă, de regulă `pos.db`.
- Baza rămâne în API-ul local, nu în folderul PHP compilat și nu în `Data`.
- Coada de sincronizare este persistentă și folosește identificatori de instalare, client, locație și entitate locală.
- Vânzările finalizate, închiderile și rapoartele Z sunt transmise idempotent către AGECS.
- Configurația externă definește clientul, locația, baza, URL-urile și utilitarele locale.
- Produsele și operatorii pot fi actualizați din AGECS prin endpointurile offline dedicate.
- Sursele PHP, proiectele `.exop`, logurile, backupurile și identitatea fizică nu se includ automat în pachetul clientului.
- Executabilul compilat și folderul `Data` se livrează separat de sursele de dezvoltare.
- Regulile comerciale ale fiecărui client au prioritate față de regulile comune.

## Excepții importante

- Daily Coffee permite NIR și operațiuni de intrare și folosește maparea local 999, online 2.
- Bestmixt nu folosește imprimanta pentru note de plată.
- Lorand are flux de imprimare BAR și rapoarte proprii.
- La Voinica păstrează fluxul de magazin și cântarul configurat pentru clientul 1015.

