# Note tehnice FoxPro

## Clasificarea existenta a produselor

In versiunea anterioara, produsele erau clasificate cu ajutorul tabelului `disponibilitati.dbf`.

- `DEP_LIST = 2` inseamna BAR.
- `DEP_LIST = 1` inseamna BUC.
- Asocierea se face prin `CODP` si, cand este disponibil, prin `GEST`.

Clasificarea ramane pastrata in `ReportRepository.php` pentru o eventuala nevoie ulterioara, dar sumele BAR si BUC nu mai sunt afisate in interfata sau in PDF.

## Citire fara lock FoxPro

sync_foxpro_from_server.bat foloseste sursa hardcodata a serverului si copiaza fisierele in baza_date_copiata. BAT-ul este rulat manual de utilizator, nu de Apache. Interfata indica numele fisierului si folderul destinatie.

Cache-ul SQLite si rapoartele citesc copiile din baza_date_copiata. Dupa copiere, utilizatorul reincarca pagina si poate reincarca manual cache-ul.

DbfReader.php incearca sa creeze si o copie locala a fiecarui DBF in app/storage/dbf_snapshots si citeste copia, nu fisierul FoxPro original. Daca FoxPro tine temporar fisierul blocat, se foloseste ultima copie valida. Folderele cu date locale nu sunt incluse in arhiva de instalare.

## Surse raport

- `note.dbf` contine operatorul, nota si metodele de plata.
- `compnote.dbf` contine produsele legate prin `NR_NOTA`.
- `totaluri.dbf` confirma totalurile zilei.
- `comp_total.dbf` contine agregarea pe `TOTAL_ID` si marcajul logic `PROTOCOL`, dar marcajul singur nu permite atribuirea unui produs unui operator.
- Pentru foaia separata de protocol, operatorul se obtine din `note.dbf.OSPATAR`, iar produsul se obtine din `compnote.dbf` prin `NR_NOTA`. Sunt incluse doar notele pentru care `note.dbf.PROTOCOL` este activ sau are o valoare numerica mai mare decat zero.
