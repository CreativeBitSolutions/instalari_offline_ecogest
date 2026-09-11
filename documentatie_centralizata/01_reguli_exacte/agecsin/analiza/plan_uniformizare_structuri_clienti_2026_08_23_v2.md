# Uniformizarea structurii bazelor de date ale clienților

## Clienți vizați

Sarcina este disponibilă pentru clienții 2, 3, 4, 6, 7, 8, 14, 17, 18, 19, 21, 22, 25, 1006 și 1007.

## Structura țintă

Structura țintă este reuniunea definițiilor din cele 16 exporturi SQL locale analizate la 23.08.2026. Hanul Patrisiei este inclus numai ca sursă structurală. Nu primește sarcina nouă.

Se includ tabelele funcționale comune, structurile hotelului, comenzile online, importurile offline, meniurile personalizate, operațiunile de restaurant, auditul și sarcinile operative specializate. Coloanele lipsă sunt completate chiar dacă modulul nu este activ pentru client.

Nu se copiază înregistrări din exporturi. Tabelele nou create rămân goale.

## Structuri excluse

Sunt excluse tabelele daily_activity_dump, mesaje_anaf_structurat_backup, miscari_02_09, miscari_03_09, miscari_31_12_2025, pvi_fix și retururi_vechi. Acestea reprezintă copii istorice, intervenții punctuale sau tabele de lucru, nu structuri funcționale permanente.

## Reguli de siguranță

Deschiderea paginii execută numai interogări asupra information_schema. Nu se execută CREATE TABLE, ALTER TABLE, UPDATE, INSERT sau DELETE.

Preview-ul prezintă tabelele lipsă, coloanele lipsă, instrucțiunile SQL și structurile excluse.

Aplicarea necesită rang de administrator, token CSRF, confirmarea existenței unui backup și introducerea exactă a ID-ului clientului.

Tabelele existente nu sunt șterse, golite sau redenumite. Coloanele existente nu sunt modificate. Indexurile tabelelor existente nu sunt rescrise. Tabelele nou create primesc indexurile definite în exportul de referință.

Constrângerile FOREIGN KEY adăugate separat în exporturi nu sunt aplicate automat. Introducerea lor ar necesita verificarea înregistrărilor orfane din fiecare bază activă.

Coloanele obligatorii fără DEFAULT primesc o valoare inițială explicită atunci când sunt adăugate într-un tabel existent. cod_locatie și locatie primesc 1. Valorile numerice primesc 0. Textele primesc șir gol. Coloanele JSON primesc {}. Valorile sunt aplicate numai coloanelor nou create.

Execuția este idempotentă. La o reluare se omit tabelele și coloanele deja prezente.

Instrucțiunile DDL produc commit implicit în MySQL și MariaDB. Sarcina se oprește la prima eroare și afișează instrucțiunea nereușită. Nu există revenire automată pentru modificările structurale.
