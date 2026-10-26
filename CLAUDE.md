# atelier — règles de travail

Back-office de devis d'un atelier d'ébénisterie. Symfony 7.4, PHP 8.4, PostgreSQL 16.
Lire `README.md` pour le quoi, `JOURNAL.md` pour le pourquoi des décisions passées.

## Commits

**Un commit = un sujet, annulable seul.** C'est la règle la plus importante de ce
dépôt, et c'est celle qui compte le plus quand le code est écrit par un agent : un
agent produit 900 lignes en un tour, et sans découpage la seule granularité de retour
arrière devient « toute la séance ».

- le code et ses tests vont **dans le même commit**, jamais séparés ;
- la CI doit passer **à chaque commit**, pas seulement au dernier ;
- viser **1 à 5 fichiers** et moins de 300 lignes ; au-delà, se demander ce qui peut
  partir dans un commit séparé ;
- **signal d'alerte** : si le message a besoin d'un « et », ou d'une liste de plus de
  trois puces, le commit fait trop de choses ;
- l'installation d'un outil, le recâblage d'une configuration et l'ajout d'une
  fonctionnalité sont **trois commits**, même s'ils ont été faits dans la même minute.

Message : une ligne de sujet à l'impératif ou au nominal, en français, puis un corps
qui explique **pourquoi**, pas quoi — le diff dit déjà quoi.

## Invariants à ne jamais casser

1. **Aucun modèle de langage ne touche à un montant.** L'IA extrait des données ; le
   prix est calculé en PHP, sur des entiers, et testé. Deptrac vérifie mécaniquement
   que `Extraction` et `Devis` ne se connaissent pas.
2. **Les montants sont des centimes entiers.** Aucun flottant dans le domaine.
3. **L'absence d'information ne se comble jamais par une valeur par défaut.** On
   refuse de chiffrer et on pose la question.
4. **Une facture émise ne change pas.** Correction par avoir.
5. **Les outils exposés à un modèle restent en lecture seule.**

## Entités : immuables ou à état, jamais entre les deux

- **Une entité jamais modifiée après sa création** (`Demande`, `Facture`, `AppelLlm`)
  expose des **propriétés publiques `readonly`**, promues dans un constructeur privé
  avec une fabrique statique. Aucun accesseur : `readonly` garantit l'immuabilité au
  niveau du langage, PHP refusant l'écriture même par réflexion. Doctrine hydrate sans
  passer par le constructeur, donc il écrit une propriété encore non initialisée — ce
  qui est autorisé.
- **Une entité à état** (`Devis`, `CompteurFactures`) garde des propriétés **privées**
  et expose des **méthodes qui nomment les transitions** (`envoyer()`, `accepter()`),
  jamais de mutateurs. Un objet qui laisse écrire n'importe quel état n'est pas une
  entité, c'est une structure de données.

Exception unique : un montant stocké en centimes reste derrière un accesseur qui rend
un `Montant`. Exposer l'entier inviterait à faire de l'arithmétique dessus.

## Nommage

Le vocabulaire du métier en français (`Devis`, `Facture`, `Bareme`, `Montant`), la
technique en anglais (`Shared`, `Http`, `Controller`). **Aucun chemin à cheval sur les
deux langues.**

## Commandes

```bash
make up        # démarre tout
make test      # la suite
make qa        # PHPStan niveau 10, style, Deptrac, vulnérabilités
make mutation  # Infection — seuil MSI 90, MSI couvert 95
```

Rien ne s'installe en local : tout tourne dans les conteneurs.

## Avant de proposer une dépendance

Ce dépôt en a déjà retiré une (`symfony/workflow`, installée puis jamais utilisée).
Une dépendance se justifie par ce qu'elle apporte **aujourd'hui**, pas par ce qu'elle
pourrait servir. Si une interface de vingt lignes suffit, écrire l'interface.
