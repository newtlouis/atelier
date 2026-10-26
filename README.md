# atelier

[![CI](https://github.com/newtlouis/atelier/actions/workflows/ci.yml/badge.svg)](https://github.com/newtlouis/atelier/actions/workflows/ci.yml)

**Une demande client en texte libre devient un devis chiffré, traçable et auditable.**

Back-office d'un atelier d'ébénisterie : un client décrit ce qu'il veut en quelques
phrases, l'application en extrait une demande structurée, la chiffre, et produit un
devis puis une facture.

> **En construction.** Ce dépôt est publié depuis son premier jour : le squelette
> démarre et la CI passe. Les fonctionnalités arrivent lot par lot — voir
> [le journal](JOURNAL.md).

## Démarrer

```bash
make up     # construit, démarre, installe les dépendances → http://localhost:8080
make test   # la suite de tests
```

`make` seul liste les commandes disponibles. Il n'y a rien à installer en local
hormis un moteur de conteneurs (Docker ou OrbStack) : PHP 8.4 et PostgreSQL 16
tournent dans les conteneurs.

## Ce qui est déjà en place

- Symfony 7.4 LTS sur PHP 8.4, servi par FrankenPHP
- PostgreSQL 16
- PHPStan **niveau 10**
- Deptrac : un domaine qui en appelle un autre fait échouer la CI
- PHP CS Fixer, `composer audit`
- une CI qui lance **exactement** les commandes ci-dessus

## L'architecture, en une ligne

Trois domaines — `Catalogue`, `Extraction`, `Devis` — qui ne communiquent pas
directement : chacun ne connaît que `Shared`. La règle est vérifiée mécaniquement,
pas documentée dans un wiki.

## Le nommage : français ou anglais ?

Les deux, selon une règle simple : **le vocabulaire du métier reste dans la langue du
métier, l'ossature technique est en anglais.**

`Devis`, `Facture`, `Bareme`, `Montant`, `Catalogue` sont français parce que ce sont des
concepts de droit français : un devis n'est pas un *quote*, et la numérotation des factures
obéit à l'article 242 nonies A du CGI. Les traduire ajouterait une couche de traduction
entre ce que dit le comptable et ce que dit le code.

`Shared`, `Http`, `Controller`, `Kernel` sont anglais parce qu'ils ne désignent rien du
métier : ce sont des termes d'architecture et de framework.

Corollaire : **pas de chemin à cheval sur les deux langues.** `Shared/Argent/Montant` a
existé une demi-journée avant d'être corrigé en `Shared/Montant`.

## Licence

MIT.
