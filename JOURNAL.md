# Journal de bord

Ce que j'ai décidé, ce qui a cassé, ce sur quoi je suis revenu. Tenu au fil de l'eau,
dans l'ordre chronologique inverse.

---

## 2026-10-26 — Lot 1 (début) : l'argent et le chiffrage

Le calcul du prix avant toute autre chose, et avant la moindre ligne d'IA. C'est ce qui
permettra plus tard d'affirmer, preuve en main, que le modèle de langage ne touche jamais
à un montant.

**Les montants sont des entiers.** `Montant` encapsule des centimes ; aucun flottant
n'entre dans le domaine. Le seul arrondi du système est dans `pourcentage()`, au centime
le plus proche, demi-unité vers le haut. Les taux eux-mêmes sont en centièmes de point
(20 % s'écrit `2000`) pour qu'un taux à 5,5 % n'introduise pas de décimal non plus.

**Le barème est une donnée, pas du code.** `Bareme` rassemble les dix valeurs commerciales.
Changer un tarif ne demande ni modification de classe ni redéploiement. C'est aussi ce qui
rendrait l'outil transposable à un autre atelier si cela devenait un jour utile — sans
payer aujourd'hui le prix d'un produit multi-client.

**Deux calculateurs, pas un avec des `if`.** `ChiffreurTable` et `ChiffreurMur` ne
partagent rien, parce que les deux métiers ne se ressemblent pas : une table se livre,
un mur se pose. Le déplacement est offert dans un cas sous conditions, jamais dans l'autre.
Forcer une abstraction commune aurait produit un calculateur à drapeaux illisible.

### Hypothèses à confirmer avec Louis

Elles sont codées comme telles et vérifiables en un coup d'œil dans
`tests/Devis/Chiffrage/BaremeDeReference.php` :

| Point | Ce que j'ai retenu | Statut |
|---|---|---|
| Nuitées, 7 à 14 m² | 8 nuitées | **interprétation** de « +8 min » |
| Nuitées, au-delà de 14 m² | 10 nuitées | déduit du maximum annoncé |
| Coût d'une nuitée | 90 € | **jamais communiqué** |
| Location utilitaire | 100 € la journée | **jamais communiqué** |
| Tarif kilométrique | 0,70 €/km sur l'aller-retour | proposition |
| Kilomètres sur un mur | facturés **en plus** de l'utilitaire | hypothèse |
| Minimum de commande | sur le produit seul (table), sur le total (mur) | hypothèse |
| Hauteur d'une table | n'influence pas le prix | à trancher |

### Une question ouverte, pas technique

Une table se vend 2 500 €/m² de plateau, un mur 450 €/m². L'écart est défendable — un
objet fini contre une surface décorative — mais il méritait d'être écrit noir sur blanc.

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
