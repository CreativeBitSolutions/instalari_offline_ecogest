# Reguli comune pentru restaurante

Sursa principală: `tipuri_instalari_offline.txt`, secțiunea comună pentru restaurante.

- Sursele de dezvoltare sunt păstrate separat de executabilul livrat.
- Baza locală este `restaurant.sqlite`, în API-ul local extern.
- Coada de sincronizare urmărește note, detalii, închideri de tură, rapoarte Z și evenimente de sincronizare.
- Evenimentele au identificator UUID, hash de conținut și identitate de instalare.
- Retrimiterea aceluiași eveniment trebuie să fie idempotentă.
- Comenzile tabletelor sunt filtrate după ospătar și locație și sunt confirmate online după import.
- AutoScannerul indică executabilul compilat și baza SQLite externă.
- Actualizările nu trebuie să suprascrie baza activă, identitatea, licența sau cozile locale.
- WooCommerce, imprimarea pe departamente și fiscalizarea se documentează separat pentru fiecare client.

## Regula de testare

Testele funcționale se execută în instalarea 1021. Instalarea activă 1008 primește doar actualizări controlate și verificări statice.

