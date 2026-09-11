# Glovo Grand Plaza, instalare offline restaurant

## Identificare

- Client ECOGEST: `26`
- Locație: `1`
- Aplicație: `C:\xampp\htdocs\github\instalari_offline_ecogest\glovograndplaza\glovograndplaza\app_restaurant_v2`
- API local: `C:\xampp\htdocs\github\instalari_offline_ecogest\glovograndplaza\api_offline_glovograndplaza`
- Bază SQLite: `C:\xampp\htdocs\github\instalari_offline_ecogest\glovograndplaza\api_offline_glovograndplaza\restaurant.sqlite`
- Interfață: `INTERFATA VANZARE - GLOVO GRAND PLAZA.url`

## Date inițiale

Baza SQLite a fost generată din `u681731335_glovgrandplaza.sql`. Au fost importate 11 conturi de operator, 241 produse, 40 de mese, 22 de categorii, gestiunile, cotele TVA, datele firmei și setările clientului 26. Notele, detaliile, mișcările, bonurile și comenzile de tabletă nu au fost copiate. Cele 77 de rapoarte Z istorice sunt păstrate pentru continuitatea numerotării și sunt marcate drept date inițiale. Numai rapoartele Z create după instalare intră în coada către online.

Baza sursă nu conține rețete, mapări WooCommerce sau istoric WooCommerce. Tabelele necesare există în SQLite și sunt completate ulterior de mecanismele aplicației. La fiecare conectare se rulează `ensure schema` pentru cozile de sincronizare, stările entităților, comenzile de tabletă, importul WooCommerce și jurnalele tehnice.

Licența este valabilă 30 de zile. Reînnoirea automată începe cu 7 zile înainte de expirare, iar o solicitare nereușită este reluată după 6 ore. O revenire a ceasului local mai mare de 5 minute blochează interfața de vânzare. Starea licenței este păstrată în SQLite și în fișierul extern `license_runtime.json`.

Lucrătorul de sincronizare pornește din interfața de autentificare și continuă trimiterea evenimentelor chiar dacă licența blochează vânzarea. Evenimentele rămân într-o coadă persistentă, sunt recuperate dacă aplicația se întrerupe în timpul trimiterii și sunt expediate în loturi de cel mult 10 elemente.

## Personalizări client 26

Interfața păstrează personalizările Glovo Grand Plaza din aplicația online, inclusiv identificarea vizuală galbenă, ascunderea discountului și listarea pe departamentele `BUC`, `BAR` și `Salate`. Clientul 26 folosește aceleași endpointuri ale site-ului `https://restaurantgrandplazasb.ro/` ca Grand Plaza.

Regula specială pentru meniul zilei și importul exclusiv al variantei de ciorbă rămâne numai la clientul 25. Nu este aplicată automat clientului 26.

O notă finalizată cu status `F` intră imediat în coada către online. Închiderea turei și raportul Z sunt sincronizate ulterior, fără duplicarea notei sau a mișcărilor.

## Aplicații executabile

- `glovo_casa_marcat\AGECSScanCM.exe` este configurat pentru clientul 26, locația 1, endpointul local și destinația `C:\fprint\in`.
- `glovo_printer\AGECSScanPR.exe` este configurat pentru clientul 26 și imprimantele `BUC`, `BAR` și `Salate`.
- Configurația online furnizată nu include o aplicație VeriBon separată pentru clientul 26. Nu a fost creată o configurație nouă.
- AutoScannerul are traseele locale, clientul 26 și programarea automată configurate.

## Cheia API necesară

Cheia API distinctă a clientului 26 este configurată în `offline_config.local.php` pentru produse, vânzări și comenzile de tabletă. Aceeași cheie trebuie înscrisă online în câmpul `api_key` al clientului 26. Cheia clientului 25 nu poate fi reutilizată, deoarece API-ul verifică asocierea dintre cheie și client.

Fișierul criptat `Resurse` al AutoScannerului este comun instalărilor verificate și nu conține configurarea specifică a clientului. Clientul, locația și traseele bazei sunt stabilite în `settings.json`.

Importul direct de pe site folosește configurația WordPress comună Grand Plaza și poate funcționa independent de cheia API centrală.

## Pornire

1. Se pornește Apache din XAMPP.
2. La prima deschidere se trimite solicitarea de licențiere pentru această instalare și se așteaptă aprobarea.
3. Se pornesc `AGECSScanCM.exe.lnk` și `AGECSScanPR.exe.lnk`.
4. Se deschide `INTERFATA VANZARE - GLOVO GRAND PLAZA.url`.
5. Se pornește `AutoScannerAgecsProducts_restaurant.exe.lnk` după înscrierea cheii API în online.
