# AGECS API Tableta

API PHP simplu pentru aplicația mobilă. Nu folosește router, Composer, clase sau
tabele API suplimentare. Fiecare adresă corespunde unui fișier PHP.

## Instalare pe host

Folderul trebuie încărcat astfel:

```text
api/
├── database_connection.php
├── persistent_session.php
└── Tableta/
    ├── _common.php
    ├── conectare.php
    ├── login.php
    ├── mese.php
    ├── produse.php
    └── ...
```

API-ul folosește direct fișierul părinte `api/database_connection.php`. Acesta
trebuie să creeze conexiunea PDO a clientului în variabila `$pdo`, pe baza lui
`$_SESSION['client_id']`. În PHP constanta corectă este `__DIR__`, cu două
caractere underscore înainte și după `DIR`.

Nu se introduc parole în niciun fișier din `Tableta`.

URL de test:

```text
GET https://domeniul-tau.ro/api/Tableta/status.php
```

## Sesiunea aplicației

`conectare.php`, `login.php` și `verifica_2fa.php` returnează `session_id`.
Aplicația îl păstrează și îl trimite la apelurile următoare:

```http
X-AGECS-Session: valoarea_session_id
```

Cookie-ul PHP standard funcționează în paralel. Dacă un răspuns de login sau 2FA
conține un `session_id` nou, aplicația înlocuiește valoarea salvată.

Toate cererile POST acceptă `application/json` și formulare clasice.

## Ordinea autentificării

1. `POST conectare.php` cu `client_id` și `cod_locatie`.
2. `GET operatori.php` pentru operatorii de tip tabletă.
3. `POST login.php` cu `operator_id` și `pin`.
4. Dacă răspunsul are `requires_2fa: true`, se apelează `POST verifica_2fa.php`.
5. Se păstrează ultimul `session_id` primit.

Exemplu:

```json
{
  "client_id": 12,
  "cod_locatie": 1
}
```

```json
{
  "operator_id": 31,
  "pin": "1234"
}
```

```json
{
  "operator_id": 31,
  "code": "123456"
}
```

## Fișiere publice

| Fișier | Metodă | Rol |
|---|---:|---|
| `status.php` | GET | Verifică dacă API-ul răspunde |
| `conectare.php` | POST | Selectează clientul și locația |
| `operatori.php` | GET | Listează operatorii tabletă |
| `login.php` | POST | Verifică PIN-ul și pornește 2FA |
| `verifica_2fa.php` | POST | Verifică acel cod și autentifică operatorul |
| `sesiune.php` | GET | Returnează sesiunea curentă |
| `logout.php` | POST | Deconectează operatorul, păstrând clientul |
| `mese.php` | GET | Returnează locațiile și mesele |
| `produse.php` | GET | Categorii, produse, căutare și paginare |
| `recomandate.php` | GET | Cele mai vândute produse |
| `comenzi.php` | GET | Comenzile operatorului |
| `comanda.php` | GET | Detaliile unei comenzi |
| `comanda_noua.php` | POST | Creează sau refolosește o comandă |
| `adauga_produs.php` | POST | Adaugă un produs în comandă |
| `modifica_produs.php` | POST | Modifică cantitatea, observația sau prioritatea |
| `sterge_produs.php` | POST | Șterge o linie din comandă |
| `sterge_comanda.php` | POST | Șterge o comandă nefinalizată |
| `observatii.php` | GET | Returnează observațiile predefinite |
| `trimite_comanda.php` | POST | Generează JSON-ul pentru imprimantă |

## Parametri uzuali

Produse:

```text
GET produse.php?categorie=3&q=pizza&page=1&limit=50
```

Comandă nouă:

```json
{"cod_locatie": 1, "cod_masa": 14, "reuse": true}
```

Adăugare produs:

```json
{
  "nrbon": 1001,
  "cod_produs": "101",
  "cantitate": 1.5,
  "observatie": "Fără ceapă"
}
```

Modificarea unei linii folosește `id_vanz` sau `line_id`:

```json
{
  "nrbon": 1001,
  "id_vanz": "55",
  "action": "cantitate",
  "cantitate": 2
}
```

Acțiunile acceptate de `modifica_produs.php` sunt `cantitate`, `observatie` și
`prioritate`.

Trimiterea comenzii:

```json
{"nrbon": 1001, "listeaza_tot": false}
```

Fișierul este scris în locația deja folosită de platformă:

```text
api/{client_id}/{cod_locatie}/de_listat_la_imprimanta.json
```

Dacă fișierul anterior încă există, endpoint-ul răspunde cu HTTP `409` și nu îl
suprascrie.

## Verificare PHP

Din folderul `api/Tableta`:

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
```
