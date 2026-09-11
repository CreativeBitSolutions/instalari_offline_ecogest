# Fluxul restaurantului offline

1. Interfața scrie notele și detaliile în `restaurant.sqlite`.
2. Nota finalizată și închiderile locale sunt identificate cu instalarea și locația.
3. Coada construiește evenimente pentru vânzare, închidere de tură și raport Z.
4. Workerul trimite evenimentele către `api/offline-sync.php` sau endpointul restaurantului.
5. Serverul validează schema, clientul, locația și idempotenta.
6. Importul online scrie datele în MySQL și generează mișcările necesare.
7. Comenzile tabletelor folosesc endpointuri GET și POST pentru preluare și confirmare.
8. La eroare de rețea, evenimentul rămâne în retry și nu se recreează local.

WooCommerce și imprimarea pe departamente sunt componente client-specifice. Grand Plaza, Glovo Grand Plaza, Taverna și copia de test nu trebuie tratate ca aceeași instalație.

