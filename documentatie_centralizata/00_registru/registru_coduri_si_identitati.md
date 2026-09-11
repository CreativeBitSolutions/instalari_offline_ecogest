# Registrul codurilor și identităților

## Coduri AGECS și coduri locale

| Instalare | Cod folosit de interfața locală | Cod folosit la sincronizare | Locație | Observație |
|---|---:|---:|---:|---|
| Daily Coffee | 999 | 2 | 2 | Configurația locală și identitatea persistentă trebuie documentate împreună. |
| AGECSDEMO | 8 | 8 | 1 | Derivat funcțional din Lorand, cu configurație proprie. |
| Agremprejba | 18 | 18 | 1 | Magazin sales-only. |
| Casa Luanna | 19 | 19 | 1 | Flux separat de facturare. |
| Bestmixt | 21 | 21 | 1 | Magazin fără imprimantă pentru note de plată. |
| Andreea Robert Shop | 22 | 22 | 1 | Magazin orientat spre scanner. |
| Grand Plaza | 25 | 25 | 1 | Restaurant și WooCommerce. |
| Glovo Grand Plaza | 26 | 26 | 1 | Restaurant și WooCommerce, cu branding separat. |
| Taverna Amicii | 1008 | 1008 | 1 | Restaurant cu tablete și sincronizare de evenimente. |
| La Voinica | 1015 | 1015 | 1 | Magazin sales-only. |
| Lorand | 1019 | 1019 | 1 | Magazin cu imprimare BAR. |
| Teste Taverna Amicii | 1021 | 1021 | 1 | Instalare de test. |

## Identitate persistentă

Identitatea offline trebuie tratată ca stare operațională a instalării, nu ca document de copiat între clienți. La distribuire se păstrează regulile și structura, dar nu se copiază identitatea fizică, licența sau cozile existente.

Identificarea evenimentelor offline combină instalarea, clientul, locația, entitatea locală și hashul conținutului. Pentru restaurante se adaugă identificatori de eveniment și de agregat.

## Atenționări

- Nu se folosește codul local 999 ca înlocuitor pentru codul online 2 la Daily Coffee.
- Nu se copiază identitatea instalării Taverna Amicii în Teste Taverna Amicii.
- Nu se copiază identitatea Lorand în AGECSDEMO.
- Fișierele de identitate găsite în directoare `backups` sunt istorice și nu reprezintă starea activă.

