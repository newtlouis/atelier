# Journal de bord

Ce que j'ai décidé, ce qui a cassé, ce sur quoi je suis revenu. Tenu au fil de l'eau,
dans l'ordre chronologique inverse.

---

## 2026-10-26 — Lot 1 (fin) : ce qu'Infection a trouvé

Première passe : **MSI 96 %**, 7 mutants survivants sur 201. Le chiffre n'est pas
l'intéressant — les 7 survivants le sont. Six étaient de vrais trous :

- les libellés des lignes d'un devis de mur n'étaient **jamais vérifiés**. On pouvait
  écrire « 400 km » au lieu de « 200 km » sans qu'un test bronche, alors que ce texte
  part chez le client ;
- la TVA n'était testée que sur les tables, jamais sur les murs ;
- une surface de 0 m² n'était pas éprouvée ;
- **on pouvait refuser un devis jamais envoyé** : la garde existait, aucun test ne la
  tenait ;
- le point de santé ne testait pas son propre cas dégradé — supprimer l'appel à la base
  le laissait répondre « ok » indéfiniment.

Le septième était le plus instructif : inverser un ternaire sur la mention de TVA ne
cassait rien, parce que le même ternaire existait **à l'identique dans les deux
calculateurs**. Il est remonté dans `Bareme::mentionAPorter()`. Une duplication trouvée
par un outil de test, pas par une relecture.

Après correction : **203 mutants sur 203 tués, MSI 100 %**. Les seuils de la CI sont
posés à 90 / 95 et non à 100 : exiger la perfection rendrait toute ligne défensive non
testable bloquante, ce qui pousse à écrire des tests pour la machine plutôt que pour le
métier.

**Une dépendance retirée.** `symfony/workflow` avait été installé pour la machine à états,
puis n'a jamais servi : les transitions sont gardées par les méthodes de l'entité
(`envoyer()`, `accepter()`…), ce qui est plus fort qu'une configuration YAML — l'invariant
vit avec la donnée qu'il protège, et aucun appelant ne peut l'ignorer. Garder le composant
« au cas où » aurait ajouté une dépendance, une configuration et une façon concurrente de
faire la même chose.

---

## 2026-10-26 — Lot 1 (suite) : entités, numérotation, immuabilité

**La numérotation des factures n'utilise pas de séquence PostgreSQL.** Une séquence ne
revient jamais en arrière : si la transaction qui consomme le numéro 42 échoue, la suivante
prend 43 et le 42 n'existe nulle part. C'est le comportement voulu pour une clé technique,
et interdit pour une facture. À la place, une ligne par année dans `compteur_factures`,
verrouillée en `SELECT … FOR UPDATE` dans la transaction qui écrit la facture : les deux
réussissent ou échouent ensemble. Un test le prouve — une tentative de facturation refusée
ne brûle pas le numéro 1.

**L'immuabilité de la facture est gardée deux fois, et la première surprise est bonne :**
toutes les propriétés sont `readonly`, et PHP 8.4 refuse la modification *même par
réflexion*. L'écouteur Doctrine `FactureImmuable` devient donc une seconde barrière, celle
qui tiendra le jour où quelqu'un retirera un `readonly` sans savoir pourquoi il était là.
Il est testé séparément, puisqu'aucun test d'intégration ne peut plus l'atteindre.

**Un refus métier ne doit pas fermer l'EntityManager.** Première version : le Facturier
vérifiait l'état du devis *dans* la transaction. Un devis non accepté — cas courant — levait
donc une exception à l'intérieur d'un `wrapInTransaction`, ce qui ferme l'EntityManager et
rend la suite de la requête inutilisable. Corrigé : `verifieFacturable()` est appelé avant
d'ouvrir la transaction, qui ne contient plus que ce qui doit être atomique. C'est le test
qui a révélé le défaut, pas la relecture.

**Nommage : règle posée** (voir README). Le métier en français, la technique en anglais,
et aucun chemin à cheval — `Shared/Argent/Montant` est devenu `Shared/Montant`.

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

### Valeurs du barème — confirmées par Louis le 26/10/2026

Les sept points ci-dessous étaient des hypothèses à la rédaction du chiffrage ; ils ont été
validés tels quels le jour même. Ils restent listés parce que trois d'entre eux sont des
valeurs que j'ai proposées faute d'information, et qu'il faudra les revoir au premier vrai
chantier :

| Point | Valeur retenue | Origine |
|---|---|---|
| Nuitées, 7 à 14 m² | 8 nuitées | interprétation de « +8 min », confirmée |
| Nuitées, au-delà de 14 m² | 10 nuitées | déduit du maximum annoncé, confirmé |
| Coût d'une nuitée | 90 € | **proposée par défaut** — à revoir sur facture réelle |
| Location utilitaire | 100 € la journée | **proposée par défaut** — à revoir sur facture réelle |
| Tarif kilométrique | 0,70 €/km sur l'aller-retour | proposée par défaut |
| Kilomètres sur un mur | facturés **en plus** de l'utilitaire | confirmée |
| Minimum de commande | sur le produit seul (table), sur le total (mur) | confirmée |
| Hauteur d'une table | n'influence pas le prix | confirmée : la hauteur n'entre pas dans le prix |

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
