# Interfață multilingvă pentru `app_restaurant_v2`

Interfața în engleză și vietnameză este aplicată în browser ca strat vizual peste textele românești existente. Codul operațional, valorile formularelor, request-urile, datele comerciale și documentele fiscale nu sunt modificate.

## Limbi

- `RO`, interfața românească originală
- `EN`, English
- `VI`, Tiếng Việt

Limba este memorată în `localStorage` cu cheia `agecs.restaurant.ui_lang`. Schimbarea limbii reîncarcă pagina. O valoare necunoscută folosește automat româna.

## Fișiere

- `dictionary.js`, expresiile românești și traducerile EN/VI
- `overlay.js`, traducerea nodurilor vizuale, conținutului AJAX și mesajelor `alert`/`confirm`
- `overlay.css`, selectorul compact de limbă
- `i18n_bootstrap.php`, încărcarea resurselor locale

## Zone protejate

Motorul nu modifică valori de formular, atribute tehnice, date trimise către backend, produse, observații, categorii dinamice, mese, operatori, coduri sau conținut pentru imprimare.

## Debug local

Modul de inventariere poate fi activat prin `?i18n_debug=1` sau:

```js
localStorage.setItem('agecs.restaurant.i18n_debug', '1');
```

Mesajele apar numai în consola browserului. Pentru dezactivare:

```js
localStorage.removeItem('agecs.restaurant.i18n_debug');
```
