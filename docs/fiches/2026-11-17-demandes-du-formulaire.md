# Traiter les demandes reçues par le formulaire du site

- **Date** : 2026-11-17
- **Statut** : livré (2026-11-18)
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

- Quatre envois au formulaire `devis` à ce jour, relus dans Netlify le 18/11/2026 :
  - **une seule vraie demande** (07/09/2026) : projet `table-custom`, message « 1600*800 mm »,
    rien d'autre. C'est le cas réel de référence. De mémoire, Louis la croyait écrite
    « 160 × 80 cm » : la donnée réelle a corrigé le souvenir ;
  - deux tests de Louis (29/06/2026), dont une commande d'une pièce du catalogue
    (« la table Travertin en taille XL, 2 700 € »), cas synthétique utile ;
  - un spam passé à travers le filtre de Netlify (12/09/2026) : prospection en anglais d'une
    agence d'applications. Le système doit le classer hors sujet, sans devis ni question.
- Les valeurs du champ projet sont des identifiants (`table-custom`, `table-standard`), pas les
  libellés affichés. Le formulaire `commande` (achat en ligne) est hors périmètre.
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
   formulaire sont repris tels quels. Un message au format simple (« 1600*800 mm »,
   « 1m60 x 80 cm ») est lu par un analyseur PHP déterministe. Le modèle n'est appelé que pour
   un message rédigé en phrases. Raison : pour « 1600*800 mm », un appel au modèle coûte de
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

Décisions **validées le 18/11/2026**, après lecture des envois réels et du formulaire :

- **Une commande d'une pièce du catalogue n'est jamais chiffrée.** Son prix est connu ; la
  chiffrer au m² donnerait un faux prix (Travertin XL : 2 450 € calculés pour 2 700 € réels).
  Issue « commande catalogue », traitée par Louis.
- **Supports de mur** (valeurs du formulaire) : maçonné, enduit ou peint, cloison (placo) et
  tapissé sont réalisables ; **le tapissé ajoute 300 € HT de retrait de la tapisserie**,
  ligne du devis tirée du barème. « Autre » et « je ne sais pas » deviennent une question au
  client. Le bois, qui ne peut venir que du message, rend le mur irréalisable.
- **Le spam est classé hors sujet** : ni devis, ni question.
- **Seconde migration accordée** : le résultat de chaque lecture est enregistré (en attente de
  distance, questions au client, commande catalogue, hors sujet, irréalisable, chiffrable),
  sans quoi une demande incomplète ne laisse aucune trace et `app:distance` n'a rien à lister.

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

Révision du 18/11/2026, après les décisions ci-dessus (les commits 1 à 4, 6 et 7 sont faits) :

| # | Commit prévu |
|---|---|
| 5a | Nature d'une demande (projet, commande catalogue, hors sujet) et issue de la lecture |
| 5b | Support du mur : le bois rend le mur irréalisable, un support inconnu se demande |
| 5c | Retrait de la tapisserie : 300 € HT au barème |
| 7b | Aiguillage : une commande catalogue sans modèle, le support lu dans le formulaire |
| 7c | Schéma v3 : le modèle signale le hors-sujet, la commande catalogue et le support |
| 7d | Enregistrer la lecture de chaque demande (**migration**) |
| 8 | Importer les envois depuis Netlify Forms |
| 9 | Saisir la distance d'une demande en attente |

Changements soumis à accord : **une migration** (commit 6). Ni `deptrac.yaml` ni
`composer.json` (le client HTTP de Symfony est déjà installé).

Le jeton Netlify va dans `.env.local`, jamais dans le dépôt.

## Livraison

Delivered on 2026-11-18, local commits only (nothing pushed). **Measurement date:
2026-12-15** — on that day, `/bilan docs/fiches/2026-11-17-demandes-du-formulaire.md`.

Real run on the four Netlify submissions: 4 imported, 4 readings recorded, 1 model call (the
spam, 0.0136 $), 0 quotes — expected, since the only real request gives no city. It revealed
two defects, fixed in their own commits: the standard height stamped on catalogue pieces, and
"distance" asked of a client who cannot know it.

Reviews (security and architecture, run as the project's reviewers): every finding was checked
in the code and kept; none discarded. The mutation hunter was not run as an agent: `make
mutation` gives 100 % covered MSI, and one-off Infection runs on Extraction and Application
leave only the known equivalent survivors of the dimension reader. The exact HEAD passes style,
PHPStan level 10, Deptrac and the 253 tests in a clean worktree.

Approvals used: three migrations (submission in Demande, LectureDeDemande, Messenger table) and
the Devis/Infrastructure boundary in `deptrac.yaml`.

Left for Louis: create the Netlify token with a short expiry (a personal token has every right
on the account); the per-city distance memory and the webhook, when needed.

- `2cd6045` Fiche : traiter les demandes reçues par le formulaire du site
- `b2d6164` Passer aux modèles Claude de la génération 5.5
- `abe23f6` Lire la clé Claude dans .env.local, pas depuis compose
- `415dc39` Corriger le schéma d'extraction, refusé par l'API
- `608d5ba` Lire un message réduit à des dimensions sans appeler le modèle
- `e56cdcb` Fonder le cas réel sur l'envoi relu dans Netlify, pas sur un souvenir
- `90cce38` Représenter un envoi du formulaire du site, champs et message séparés
- `e3f801b` Hauteur standard des tables basses : la seule exception à l'invariant 3
- `2cde01d` Annoncer sur le devis une hauteur standard, ajustable sur demande
- `83fc305` Garder dans la demande l'envoi de formulaire dont elle vient
- `f9fb89e` Lire un envoi de formulaire sans le modèle quand c'est sûr
- `30b7dac` Distinguer un projet d'une commande catalogue ou d'un hors-sujet
- `2a191d9` Exiger le support d'un mur : le bois le rend irréalisable
- `77ab1fe` Inscrire au barème le retrait de la tapisserie : 300 € HT
- `df342d4` Facturer le retrait de la tapisserie sur le devis d'un mur tapissé
- `807918c` Vérifier qu'une clé de barème absente est dite absente
- `fda9b66` Reconnaître une commande catalogue sans le modèle, lire le support coché
- `c37a29c` Schéma v3 : le modèle signale le hors-sujet, le catalogue et le support
- `ba1f2dd` Enregistrer la lecture de chaque demande et sa suite à donner
- `36df414` Lire les envois des formulaires du site dans l'API Netlify Forms
- `76d2d82` Importer les envois du formulaire : app:importer-envois
- `a22ea38` Compléter une demande avec la distance mesurée par Louis
- `3496fe9` Saisir la distance des demandes en attente : app:distance
- `1750a68` Créer la table de la file Messenger par migration
- `24b37fe` Réserver la hauteur standard aux tables sur mesure
- `a340eab` Demander au client sa ville, pas une distance qu'il ignore
- `c068ed7` Draw the Devis/Infrastructure boundary in Deptrac
- `8f25628` Make the nature of a read request mandatory
- `debe565` Copy the read-request contract in one place only
- `3ee218e` Demande: private constructor, one factory per origin
- `fd0a1ba` Remove the SourceDesEnvois port
- `a530532` Name the LectureDeDemande factory depuis(), like its neighbours
- `e781f78` Move SaisieDeDistance into Devis/Chiffrage
- `080c6f4` File the dimension reader under Extraction/Lecture
- `ec86542` Move the form's support vocabulary into Aiguillage
- `04c8684` Never price a trip on a distance the client typed
- `2b608a5` Neutralise every angle bracket in the client's text
- `d21745a` Show Louis exactly what the client sent
- `672bb66` Fit the e-mail and the city into their columns
- `6d5c709` Cap the size of a text before paying for its reading


## Bilan
