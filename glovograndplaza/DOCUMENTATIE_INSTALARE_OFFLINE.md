# Glovo Grand Plaza, instalare offline restaurant

## Identificare

- Client AGECS: `26`
- Locație: `1`
- Aplicație: `C:\xampp\htdocs\github\instalari_offline_ecogest\glovograndplaza\glovograndplaza\app_restaurant_v2`
- API local: `C:\xampp\htdocs\github\instalari_offline_ecogest\glovograndplaza\api_offline_glovograndplaza`
- Bază SQLite: `C:\xampp\htdocs\github\instalari_offline_ecogest\glovograndplaza\api_offline_glovograndplaza\restaurant.sqlite`
- Interfață: `INTERFATA VANZARE - GLOVO GRAND PLAZA.url`

## Date inițiale

Baza SQLite a fost generată din `u681731335_glovgrandplaza.sql`. Au fost importate 11 conturi de operator, 241 produse, 40 de mese, 22 de categorii, gestiunile, cotele TVA, datele firmei și setările clientului 26. Notele, detaliile, mișcările, bonurile și comenzile de tabletă nu au fost copiate. Cele 77 de rapoarte Z istorice sunt păstrate pentru continuitatea numerotării.

Baza sursă nu conține rețete, mapări WooCommerce sau istoric WooCommerce. Tabelele necesare există în SQLite și sunt completate ulterior de mecanismele aplicației. La fiecare conectare se rulează `ensure schema` pentru cozile de sincronizare, stările entităților, comenzile de tabletă, importul WooCommerce și jurnalele tehnice.

## Personalizări client 26

Interfața păstrează personalizările Glovo Grand Plaza din aplicația online, inclusiv identificarea vizuală galbenă, ascunderea discountului și listarea pe departamentele `BUC`, `BAR` și `Salate`. Clientul 26 folosește aceleași endpointuri ale site-ului `https://restaurantgrandplazasb.ro/` ca Grand Plaza.

Regula specială pentru meniul zilei și importul exclusiv al variantei de ciorbă rămâne numai la clientul 25. Nu este aplicată automat clientului 26.

O notă finalizată cu status `F` intră imediat în coada către online. Închiderea turei și raportul Z sunt sincronizate ulterior, fără duplicarea notei sau a mișcărilor.

## Aplicații executabile

- `glovo_casa_marcat\AGECSScanCM.exe` este configurat pentru clientul 26, locația 1, endpointul local și destinația `C:\fprint\in`.
- `glovo_printer\AGECSScanPR.exe` este configurat pentru clientul 26 și imprimantele `BUC`, `BAR` și `Salate`.
- Configurația online furnizată nu include o aplicație VeriBon separată pentru clientul 26. Nu a fost creată o configurație nouă.
- AutoScannerul are traseele locale și clientul 26 configurate. Programarea lui rămâne oprită până este furnizată și configurată cheia API distinctă a clientului 26.

## Cheia API necesară

În baza centrală disponibilă, clientul 26 nu are o cheie API. Sincronizarea automată a produselor, vânzărilor și comenzilor de tabletă este păstrată oprită în `offline_config.local.php`. Activarea necesită cheia API aferentă clientului 26 și refacerea fișierului `Resurse` al AutoScannerului. Cheia clientului 25 nu poate fi reutilizată, deoarece API-ul verifică asocierea dintre cheie și client.

Importul direct de pe site folosește configurația WordPress comună Grand Plaza și poate funcționa independent de cheia API centrală.

## Pornire

1. Se pornește Apache din XAMPP.
2. Se pornesc `AGECSScanCM.exe.lnk` și `AGECSScanPR.exe.lnk`.
3. Se deschide `INTERFATA VANZARE - GLOVO GRAND PLAZA.url`.
4. AutoScannerul se pornește după configurarea cheii API pentru clientul 26.
