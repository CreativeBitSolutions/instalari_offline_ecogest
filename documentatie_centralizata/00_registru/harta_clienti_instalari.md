# Harta clienților și instalărilor

Sursa principală pentru instalările offline este `tipuri_instalari_offline.txt`. Codurile din `agecsin\activitati_reguli_lucru` sunt menționate separat, deoarece nu există document dedicat pentru fiecare instalare offline.

| Client | Locație | Instalare | Tip | Document dedicat în `agecsin` |
|---:|---:|---|---|---|
| 2, local 999 | 2 | Daily Coffee | Magazin cu gestiune locală, NIR și operațiuni de intrare | Da, client 2 |
| 8 | 1 | AGECSDEMO | Magazin offline derivat din Lorand | Da |
| 18 | 1 | Agremprejba | Magazin POS offline, sales-only | Da |
| 19 | 1 | Casa Luanna | Facturare offline | Da |
| 21 | 1 | Bestmixt | Magazin POS offline, fără imprimantă pentru note de plată | Da |
| 22 | 1 | Andreea Robert Shop | Magazin POS offline, orientat spre scanner | Da |
| 25 | 1 | Grand Plaza | Restaurant offline cu WooCommerce | Da |
| 26 | 1 | Glovo Grand Plaza | Restaurant offline cu WooCommerce și branding Glovo | Nu |
| 1008 | 1 | Taverna Amicii | Restaurant offline cu ospătari, șefi de sală și tablete | Da |
| 1015 | 1 | La Voinica | Magazin POS offline | Nu |
| 1019 | 1 | Lorand | Magazin offline cu fiscalizare și imprimare BAR | Nu |
| 1021 | 1 | Teste Taverna Amicii | Instalare de test derivată din Taverna Amicii | Nu |

## Clienți documentați în `agecsin`, fără instalare offline identificată

Clienții 3, 4, 6, 7, 9, 14, 17, 1006 și 1007 au documente în `agecsin\activitati_reguli_lucru`, dar nu au o instalare offline corespunzătoare în catalogul principal al repository-ului offline. Documentele lor sunt păstrate separat în copiile exacte și nu sunt prezentate ca instalări offline active.

## Observații de identificare

- Daily Coffee folosește clientul local 999 și sincronizează cu clientul online 2.
- AGECSDEMO folosește codul 8 și are identitate proprie, chiar dacă funcțional moștenește elemente din Lorand.
- Teste Taverna Amicii, client 1021, este mediu de test și nu este aceeași instalare cu Taverna Amicii, client 1008.

