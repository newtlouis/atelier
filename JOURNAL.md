# Journal de bord

Ce que j'ai décidé, ce qui a cassé, ce sur quoi je suis revenu. Tenu au fil de l'eau,
dans l'ordre chronologique inverse.

---

## 2026-10-26 — Lot 0 : le squelette qui démarre

Premier jour. Aucun code métier, un seul objectif : qu'un inconnu puisse cloner le dépôt
et le faire tourner avec deux commandes.

**Trois décisions prises d'emblée :**

- **FrankenPHP plutôt que PHP-FPM + Nginx.** Un seul processus à configurer, et l'image
  de production se construit à partir de la même base que celle de développement. Le coût :
  c'est moins répandu que la pile classique, donc moins de réponses toutes faites en cas
  de panne.
- **PHPStan au niveau 10 dès le premier commit.** Monter le niveau sur un projet existant
  coûte beaucoup plus cher que de partir en haut et de ne jamais redescendre.
- **Deptrac dès le premier commit, avec les domaines déclarés avant d'exister.** Un
  découpage par domaine qui n'est pas vérifié mécaniquement n'est pas une architecture,
  c'est une convention de nommage — et les conventions de nommage ne survivent pas à la
  troisième fonctionnalité pressée.

**La CI lance exactement les commandes du README** (`make up`, `make test`, `make qa`)
plutôt qu'une installation PHP séparée. C'est plus lent de deux minutes, mais ça rend
impossible le cas classique : un README qui promet `docker compose up` alors que plus
personne n'a essayé depuis six mois.
