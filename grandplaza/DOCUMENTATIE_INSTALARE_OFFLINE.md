# Grand Plaza, instalare offline restaurant

## Identificare

- Client ECOGEST: `25`
- Locație: `1`
- Aplicație: `C:\xampp\htdocs\github\instalari_offline_ecogest\grandplaza\grandplaza\app_restaurant_v2`
- API local: `C:\xampp\htdocs\github\instalari_offline_ecogest\grandplaza\api_offline_grandplaza`
- Bază SQLite: `C:\xampp\htdocs\github\instalari_offline_ecogest\grandplaza\api_offline_grandplaza\restaurant.sqlite`
- Interfață: `INTERFATA VANZARE - GRAND PLAZA.url`

## Date și scheme

Baza a fost generată din `u681731335_grandplaza.sql`. Conține operatorii, produsele, mesele, categoriile, gestiunile, cotele TVA, datele firmei, mapările WooCommerce și registrul comenzilor site deja importate. Notele, mișcările, bonurile și comenzile de tabletă pornesc goale. Cele 125 de rapoarte Z istorice sunt păstrate pentru continuitatea numerotării și sunt marcate drept date inițiale. Numai rapoartele Z create după instalare intră în coada către online.

La deschiderea conexiunii SQLite se rulează `ensure schema` pentru cozile de vânzări, actualizările de închidere de tură, rapoartele Z, sincronizarea tabletelor, comenzile WooCommerce, mapările de produse, jurnalele și stările persistente. Retrimiterea folosește identificatori stabili și nu recreează aceeași notă online.

Licența este valabilă 30 de zile. Reînnoirea automată începe cu 7 zile înainte de expirare, iar o solicitare nereușită este reluată după 6 ore. O revenire a ceasului local mai mare de 5 minute blochează interfața de vânzare. Starea licenței este păstrată în SQLite și în fișierul extern `license_runtime.json`.

Lucrătorul de sincronizare pornește din interfața de autentificare și continuă trimiterea evenimentelor chiar dacă licența blochează vânzarea. Evenimentele rămân într-o coadă persistentă, sunt recuperate dacă aplicația se întrerupe în timpul trimiterii și sunt expediate în loturi de cel mult 10 elemente.

## Reguli Grand Plaza

Sunt păstrate personalizările clientului 25, inclusiv mapările WooCommerce, importul meniului zilei și variantele pentru ciorbă, felul doi, meniu complet, salată și transport. Site-ul folosit este `https://restaurantgrandplazasb.ro/`.

O notă finalizată cu status `F` intră imediat în coada către online. Închiderea turei și numărul raportului Z sunt actualizate ulterior prin evenimente separate. Mișcările sunt generate online conform rețetelor disponibile acolo.

## Aplicații executabile

- `grandplaza_casa_marcat\AGECSScanCM.exe` citește endpointul local și scrie fișierele INP în `C:\fprint\in`.
- `grandplaza_printer\AGECSScanPR.exe` citește endpointul local și folosește imprimantele `BUC`, `BAR` și `Salate`.
- `grandplaza_veribon\AGECS.VeriBon.exe` urmărește răspunsurile din `C:\fprint\in\BonANSWER`, `BonERR` și `BonOK`, apoi le transmite endpointului AGECS online.
- `ecogest_autoscanner_products_restaurant_1_4_6_0` actualizează nomenclatoarele în baza SQLite a clientului 25.

Copiile din `aplicatii_grandplaza_configurate_pentru_online` rămân configurațiile originale online. Variantele din rădăcina instalării sunt cele configurate pentru fluxul offline.

## Pornire

1. Se pornește Apache din XAMPP.
2. La prima deschidere se trimite solicitarea de licențiere pentru această instalare și se așteaptă aprobarea.
3. Se pornesc `AGECSScanCM.exe.lnk`, `AGECSScanPR.exe.lnk` și `AGECS.VeriBon.exe.lnk`.
4. Se pornește `AutoScannerAgecsProducts_restaurant.exe.lnk`.
5. Se deschide `INTERFATA VANZARE - GRAND PLAZA.url`.
