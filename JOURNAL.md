# Journal de bord

Ce que j'ai décidé, ce qui a cassé, ce sur quoi je suis revenu. Tenu au fil de l'eau,
dans l'ordre chronologique inverse.

---

## 2026-10-27 — Le port, l'adaptateur, et une couche qui mentait

Deux remarques de relecture, deux vrais défauts.

**`src/Catalogue/` ne contenait qu'un `.gitkeep`** alors que `deptrac.yaml` le déclarait
et que le README annonçait « trois domaines ». Le chiffrage est paramétrique : il applique
un barème au m², il ne cherche pas dans un catalogue de pièces. La couche était une
anticipation du plan, pas un besoin. Supprimée. Elle reviendra avec le code de la
recherche de comparables, et `--fail-on-uncovered` obligera de toute façon à la déclarer.

**`Extracteur` et `ExtracteurClaude` vivaient dans le même dossier.** L'inversion de
dépendance était pourtant correcte — l'adaptateur dépendait du port, le câblage se faisait
dans `services.yaml` — mais rien ne l'empêchait de s'inverser à la prochaine fonctionnalité
pressée. Treize fichiers à plat mélangeaient l'interface, le suivi des coûts, le schéma
JSON d'Anthropic et le dépôt Doctrine.

Découpé en `Extracteur.php` (le port), `Cout/` (ce que le domaine sait de lui-même) et
`Infrastructure/` (le SDK Anthropic, Doctrine, la doublure). Deptrac traite les deux
derniers comme des couches distinctes, et j'ai **provoqué la violation pour vérifier
qu'elle casse** avant de considérer la règle comme acquise.

**Ce que le découpage a révélé :** `UsageAppel::echec()` lisait `SchemaExtraction::VERSION`.
Un objet de mesure dépendait du schéma de prompt d'un fournisseur précis — invisible tant
que les deux classes partageaient un dossier, impossible à ignorer une fois la frontière
tracée. La version est maintenant passée par l'appelant. C'est l'argument pour ce genre de
découpage : il ne range pas des fichiers, il rend visibles des dépendances qu'on ne voyait
plus.

**Ce qui n'a délibérément pas été séparé :** les entités de `Devis` gardent leurs attributs
Doctrine. Un modèle de persistance distinct doublerait le code sans rien prouver. Un port
se justifie là où l'implémentation peut changer ou coûter de l'argent — le fournisseur de
modèle — pas partout par principe.

---

## 2026-10-26 — Lot 2 (début) : le contrat d'extraction, sans appel réseau

Tout ce qui précède l'appel au modèle, et qui se teste sans dépenser un centime.

**L'absence d'information est une donnée de première classe.** `DemandeExtraite` porte un
champ `champsManquants`, et `estExploitable()` refuse le chiffrage tant qu'il reste quoi que
ce soit à deviner. Un modèle de langage répond *toujours* quelque chose ; le laisser inventer
une hauteur de table produirait des devis faux et plausibles — le pire des deux mondes.

**On croit les données, pas la déclaration du modèle.** `estExploitable()` ne se contente pas
de vérifier que `champsManquants` est vide : elle vérifie les champs eux-mêmes. Un modèle qui
affirme n'avoir rien oublié tout en laissant la largeur à null est contredit par le code, et
un test couvre exactement ce cas.

**La frontière IA / argent est maintenant matérialisée par Deptrac.** `Extraction` et `Devis`
ne se connaissent pas : elles ne partagent que `Shared\Contrat\DemandeExtraite`. Le jour où
quelqu'un voudra appeler un modèle depuis le calcul d'un prix, la CI l'en empêchera — ce n'est
plus une intention, c'est une règle mécanique.

**La lecture de la réponse est séparée de l'appel réseau.** `LecteurDeReponse` concentre tout
ce qui peut mal tourner : JSON illisible, famille inconnue, dimension négative, dimension
absurde. Une sortie structurée garantit la *forme* de la réponse, jamais sa *cohérence* : rien
n'empêche un modèle de renvoyer une longueur de 120 000 mm. Isolé, ce code s'éprouve sur des
dizaines de réponses malformées sans réseau ni dépense — huit cas pour l'instant.

**Un extracteur factice vit dans `src/`, pas dans `tests/`.** Il permet de faire tourner la
chaîne complète sans clé d'API, en développement comme en démonstration. Ce n'est pas un
accessoire de test, c'est une implémentation de l'interface.

### Infection : 99 %, et deux survivants assumés

244 mutants, 242 tués. Les deux rescapés sont des gardes inatteignables par construction : la
branche `null` d'un `match` — nécessaire à l'exhaustivité exigée par PHP — et un `throw`
défensif qui convertit un `?int` en `int` pour l'analyse statique. Les tuer demanderait des
tests artificiels pour du code qui ne peut pas s'exécuter. C'est précisément pour ces cas que
le seuil est fixé à 95 et non à 100.

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
