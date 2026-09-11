# Fluxul facturării offline

Casa Luanna gestionează facturi locale și le sincronizează ulterior.

- Salvarea seriei și a numărului creează baza documentului.
- Documentul este marcat cu originea instalației.
- Modificările și finalizarea sunt transmise prin revizii persistente.
- Serverul verifică proprietarul, seria, numărul, încasările, stornările și starea ANAF.
- Confirmările sunt idempotente.
- Documentele istorice importate nu intră automat în coada de facturi noi.

Fluxul folosește sursele AGECS pentru facturi, dar rularea locală folosește SQLite și configurații externe proprii.

