# Traiter les demandes reçues par le formulaire du site

- **Date** : 2026-11-17
- **Statut** : à valider
- **Appétit** : 2 jours. Au-delà, on retire l'import Netlify et on garde une commande qui
  prend un envoi collé à la main.

## Le problème

La chaîne actuelle part d'un « texte client » qui n'existe pas dans la réalité. Les demandes
de devis arrivent par les formulaires Netlify du site (`devis`, `devis-mur`, `devis-piece`,
`contact-pro`) : des **champs déjà structurés** (type de projet, surface, ville, support du
mur, pièce et taille) et un **message libre**, souvent télégraphique. Aujourd'hui, rien ne
fait entrer un envoi de formulaire dans l'application : la seule route est `/health`, et la
chaîne n'a jamais été déclenchée par une vraie demande.

Deuxième écart : le contrat d'extraction parle de « tables » au sens large et demande une
hauteur. Or l'atelier ne fait **que des tables basses**, et la hauteur ne change pas le prix.

## La preuve

- Une seule demande reçue par formulaire à ce jour : projet « Table — Sur mesure », message
  « 160 × 80 cm », rien d'autre. C'est le cas réel de référence.
- Les deux autres demandes connues sont arrivées par Instagram, en conversation, et ne
  passeront jamais par cette chaîne.
- Règles métier données par Louis le 17/11/2026 : tables basses uniquement, hauteur standard
  35 cm ; un mur se pose sur un support inorganique (placo, maçonné, béton), **jamais sur du
  bois** ; le type de support ne change pas le prix.
- Ce qui manque : du volume. Une demande ne mesure rien ; c'est le jeu d'évaluation (fiche
  suivante) qui portera la mesure.

## Critère de succès

| Mesure | Aujourd'hui | Cible | Source de la donnée | Date de mesure |
|---|---|---|---|---|
| Envois de formulaire qui produisent un devis brouillon **ou** des questions à poser | aucun envoi traité | 100 % | `Demande` + `Devis` en base après import | 2026-12-15 |
| Envois traités sans appel au modèle (champs + message au format simple) | — | mesuré, pas de cible | `AppelLlm` rapporté aux demandes | 2026-12-15 |
| Dimension ou surface inventée sur le jeu d'évaluation | non mesuré | 0 | `evals/RESULTATS.md` (fiche suivante) | 2026-12-15 |

## Approches envisagées

1. **Retenue — lire d'abord, appeler le modèle seulement si nécessaire.** Les champs du
   formulaire sont repris tels quels. Un message au format simple (« 160 × 80 cm »,
   « 1m60 x 80 ») est lu par un analyseur PHP déterministe. Le modèle n'est appelé que pour
   un message rédigé en phrases. Raison : pour « 160 × 80 cm », un appel au modèle coûte de
   l'argent et du temps pour un résultat qu'une expression régulière donne sans erreur
   possible.
2. **Écartée — tout envoyer au modèle.** Plus simple à écrire, mais paie un appel pour la
   majorité des cas réels, et rend le comportement moins prévisible sur des entrées triviales.
3. **Écartée pour l'instant — webhook Netlify.** Il faut héberger l'application. La commande
   d'import couvre le besoin réel (quelques envois par mois) ; le webhook viendra avec un
   hébergement.

## Hors périmètre

- Les messages Instagram (l'API Meta est fermée, et ce sont des conversations, pas des demandes).
- Le webhook Netlify et l'hébergement.
- Le calcul automatique de la distance depuis la ville (voir décision 2).
- L'envoi du devis ou de la réponse au client : Louis relit et envoie lui-même.
- Le jeu d'évaluation : fiche suivante, il réutilise ce travail.

## Décisions à prendre par Louis

Décision 1 **validée le 17/11/2026** : 35 cm sauf si le client demande une autre hauteur.
Migration de base **accordée**. Source des demandes : import depuis Netlify (le formulaire
continue d'y arriver ; `atelier` importe), le webhook viendra avec un hébergement.

1. **La hauteur standard contredit l'invariant 3** (« l'absence d'information ne se comble
   jamais par une valeur par défaut »). Proposition : une exception **écrite et étroite** —
   une hauteur absente sur une table basse vaut 35 cm, et le devis l'affiche (« hauteur
   standard 35 cm, ajustable »). C'est une règle de Louis appliquée par le code, jamais une
   devinette du modèle, et la hauteur ne change pas le prix. L'invariant est reformulé dans
   CLAUDE.md pour le dire.
2. **La distance.** Le formulaire donne une ville, le chiffrage veut des kilomètres. Proposition :
   l'extraction ne devine jamais de distance ; le devis reste « en attente de distance » et
   Louis la saisit en relisant le brouillon (une commande). Le calcul automatique
   (géocodage) est reporté.

## Invariants approchés

- **1 (le modèle ne touche pas aux montants)** : inchangé — l'analyseur et le modèle
  produisent un `DemandeExtraite`, le chiffrage reste dans `Devis`. Deptrac le vérifie.
- **3 (pas de valeur par défaut)** : modifié par la décision 1, avec un test qui prouve que
  l'exception ne s'applique qu'à la hauteur d'une table basse.
- Un support en bois produit « irréalisable », jamais un devis : test dédié.

## Plan

| # | Commit prévu | Fichiers | Parallélisable avec |
|---|---|---|---|
| 1 | Passer aux modèles Claude actuels (`claude-opus-5-5`, grille tarifaire à jour) | `ExtracteurClaude`, `GrilleTarifaire` + test | 2, 3 |
| 2 | Lire un message au format simple sans appeler le modèle | `Extraction/LecteurDeDimensions` + test | 1 |
| 3 | Un envoi de formulaire : champs structurés + message | `Shared/Contrat/EnvoiFormulaire` + test | 1 |
| 4 | Tables basses : hauteur standard 35 cm, exception écrite à l'invariant 3 | `DemandeExtraite`, `CLAUDE.md` + test | — |
| 5 | Un mur sur support en bois est irréalisable | contrat + chiffrage + test | — |
| 6 | Garder l'envoi complet dans `Demande` | entité + **migration** + test | — |
| 7 | Choisir la voie : champs, analyseur, puis modèle | `Application/` + test | — |
| 8 | Importer les envois depuis Netlify Forms | commande + client HTTP + test (réponse enregistrée) | — |
| 9 | Saisir la distance d'un devis en attente | commande + transition + test | — |

Changements soumis à accord : **une migration** (commit 6). Ni `deptrac.yaml` ni
`composer.json` (le client HTTP de Symfony est déjà installé).

Le jeton Netlify va dans `.env.local`, jamais dans le dépôt.

## Livraison

## Bilan
