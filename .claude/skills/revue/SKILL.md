---
name: revue
description: Relit les changements en cours d'atelier avec les deux relecteurs (sécurité, architecture) en parallèle, vérifie chaque constat et rend une liste triée. À utiliser avant un commit ou sur une plage de commits.
argument-hint: "[plage git, ex. main~3..HEAD — par défaut : le travail non commité]"
disable-model-invocation: true
allowed-tools: Bash(git diff *) Bash(git status *) Bash(git log *)
---

# Revue croisée

Périmètre : **$ARGUMENTS** — si vide, le travail non commité (`git diff HEAD` et les
fichiers non suivis).

État du dépôt au lancement :

!`git status --short`

!`git diff --stat HEAD`

1. **Lance les deux relecteurs en parallèle**, dans un seul message : l'agent
   `relecteur-securite` et l'agent `relecteur-architecture`, chacun avec le même périmètre.
   Si `src/Devis` ou `src/Shared` figure dans le diff, ajoute `chasseur-de-mutants`.
2. **Vérifie chaque constat toi-même** dans le code avant de le retenir. Un relecteur peut
   se tromper de ligne, mal lire un flux ou signaler un cas que le code traite déjà
   ailleurs. Écarte ce qui ne résiste pas, en une ligne de justification.
3. **Fusionne** les doublons et trie : bloquant, à corriger, à surveiller ou suggestion.
4. Rends à Louis :
   - les constats retenus, chacun avec `fichier:ligne`, le scénario et la correction ;
   - les constats écartés et pourquoi, en une ligne chacun ;
   - le découpage en commits proposé par le relecteur d'architecture, s'il y en a un.

Ne corrige rien dans cette skill : la revue rend un avis, la correction est un autre commit.
