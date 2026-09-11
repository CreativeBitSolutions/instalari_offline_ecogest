# Reguli pentru facturarea offline

Casa Luanna este o instalare de facturare offline, nu o instalare POS și nu o copie a fluxului restaurantului.

- Clientul este 19, locația 1.
- Baza SQLite este externă aplicației compilate.
- Istoricul importat rămâne separat de documentele noi eligibile pentru sincronizare.
- Facturile noi folosesc o coadă de sincronizare cu hash, confirmare și reguli de idempotentă.
- Ștergerea este permisă numai pentru documentele create în instalația locală și respectă verificările online.
- Seria, numărul, încasările, stornările, încărcările ANAF și modificările concurente sunt validate înaintea ștergerii.
- Utilizatorii, firma, seriile, TVA-ul și produsele pot fi preluate din online.
- Compilarea folosește surse administrative din `ecogest_offline_casaluanna\admin`.

Regulile de facturare se păstrează separat de regulile pentru bonuri, note, tablete și rapoarte Z.

