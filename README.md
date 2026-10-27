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
- **Infection : MSI 100 %** — la suite détecte 203 mutations sur 203
- PHP CS Fixer, `composer audit`
- une CI qui lance **exactement** les commandes ci-dessus

## Pourquoi le test de mutation plutôt que la couverture

La couverture dit quelles lignes ont été exécutées, pas si quelqu'un regardait.
Une suite peut couvrir 100 % du code sans rien affirmer.

Infection modifie le code — remplace un `<=` par un `<`, inverse un ternaire,
supprime un appel — et vérifie qu'un test proteste. Sur ce dépôt, il a trouvé
sept trous invisibles à la couverture, dont une règle de TVA dupliquée entre
deux calculateurs qui aurait fini par diverger. `make mutation` pour le relancer.

## L'architecture, en une ligne

Deux domaines — `Extraction` et `Devis` — qui ne communiquent pas directement :
chacun ne connaît que `Shared`, et seul `Application` a le droit d'en connaître
deux à la fois. La règle est vérifiée mécaniquement par Deptrac, pas documentée
dans un wiki.

## Le métier déclare, l'infrastructure implémente

```
src/Extraction/
├── Extracteur.php            ← l'interface : une méthode, aucune dépendance technique
├── Cout/                     ← ce que le domaine sait de lui-même : jetons, grille, journal
└── Infrastructure/
    ├── Claude/               ← le SDK Anthropic ne se trouve que là
    ├── JournalDoctrine.php
    └── ExtracteurFactice.php
```

`Extracteur` ne connaît pas `ExtracteurClaude`. Ce n'est pas une convention : Deptrac
traite `Extraction` et `Extraction/Infrastructure` comme deux couches, et la seconde
seule a le droit de connaître la première. Nommer l'adaptateur depuis le port casse la
CI — vérifié en provoquant la violation avant d'écrire cette ligne.

Le choix de l'implémentation est fait une seule fois, dans `config/services.yaml` ;
en test, `config/services_test.yaml` bascule sur une doublure, ce qui garantit qu'aucune
suite ne peut déclencher un appel facturé.

**Ce qui n'est délibérément pas séparé :** les entités de `Devis` portent leurs attributs
Doctrine, et `DevisRepository` est un dépôt Doctrine. Un modèle de persistance distinct
doublerait le code pour un gain nul ici. La règle appliquée est plus étroite, et c'est
celle qui tient : **un port là où l'implémentation peut changer ou coûter de l'argent**
— le fournisseur de modèle — pas partout par principe.

## Le nommage : français ou anglais ?

Les deux, selon une règle simple : **le vocabulaire du métier reste dans la langue du
métier, l'ossature technique est en anglais.**

`Devis`, `Facture`, `Bareme`, `Montant`, `Chiffrage` sont français parce que ce sont des
concepts de droit français : un devis n'est pas un *quote*, et la numérotation des factures
obéit à l'article 242 nonies A du CGI. Les traduire ajouterait une couche de traduction
entre ce que dit le comptable et ce que dit le code.

`Shared`, `Http`, `Controller`, `Kernel` sont anglais parce qu'ils ne désignent rien du
métier : ce sont des termes d'architecture et de framework.

Corollaire : **pas de chemin à cheval sur les deux langues.** `Shared/Argent/Montant` a
existé une demi-journée avant d'être corrigé en `Shared/Montant`.

## Licence

MIT.
