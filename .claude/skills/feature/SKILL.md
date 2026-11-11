---
name: feature
description: Mène une fonctionnalité d'atelier de bout en bout — du problème observé jusqu'au commit vérifié, puis à la date où l'on mesurera si elle a servi. À utiliser quand Louis décrit un problème ou une envie, pas pour une correction d'une ligne.
argument-hint: "[le problème, en une phrase ou en vrac]"
disable-model-invocation: true
---

# Une fonctionnalité, du problème à la preuve

Point de départ donné par Louis : **$ARGUMENTS**

Personne ne te donne de spécification : c'est toi qui la rédiges, à partir du problème.
Le déroulé a **deux arrêts obligatoires** où Louis valide avant que tu continues ; entre
les deux, tu avances seul. Ne saute aucune étape, même si la demande paraît simple : c'est
sur les demandes simples qu'on oublie le critère de succès.

## 1. Comprendre le problème avant la solution

- Relis `CLAUDE.md`, les trois dernières entrées de `JOURNAL.md` et les fiches existantes
  dans `docs/fiches/` : le problème a peut-être déjà été posé, ou écarté pour une raison écrite.
- **Cherche la preuve que le problème existe** : données en base (`AppelLlm` pour les
  coûts, les échecs d'extraction et la latence ; les devis et leur état), journaux,
  demandes réelles. Si Louis a fourni des retours clients, des échanges ou des chiffres,
  synthétise-les : ce qui revient, ce qui est anecdotique, ce qui contredit l'intuition de
  départ.
- Si la preuve manque, **dis-le** plutôt que de l'inventer : « je n'ai pas de donnée sur X,
  voici comment l'obtenir » est une réponse acceptable ; « les clients veulent X » sans
  source ne l'est pas.

## 2. Rédiger la fiche — premier arrêt

Copie `${CLAUDE_SKILL_DIR}/modele-fiche.md` vers `docs/fiches/AAAA-MM-JJ-sujet.md` et
remplis-la. Les trois rubriques qui comptent le plus :

- **Critère de succès mesurable**, avec la donnée qui le mesurera et la date de mesure.
  « Les devis sont plus rapides » ne se mesure pas ; « la médiane entre réception de la
  demande et devis envoyé passe sous 24 h, mesurée sur les devis du mois suivant » oui.
- **Appétit** : le temps maximum que le problème mérite. Au-delà, on réduit le périmètre,
  on ne prolonge pas.
- **Hors périmètre**, écrit explicitement.

Si deux approches se valent vraiment et que la différence ne se tranche pas sur papier,
propose un **prototype jetable** (une heure au plus, dans un bac à sable — voir étape 4) et
dis ce qu'il doit départager.

**Arrête-toi et présente la fiche à Louis.** Ne code rien avant son accord : une mauvaise
fiche coûte plus cher qu'un mauvais code, parce qu'elle produit du bon code inutile.

## 3. Plan et découpage — second arrêt

Ajoute à la fiche le plan d'implémentation :

- la liste **ordonnée** des commits, chacun conforme à CLAUDE.md (un sujet, 1 à 5 fichiers,
  code et tests ensemble, vert seul) avec son message prévu ;
- pour chacun, les invariants qu'il approche (montants, absence d'information, factures
  immuables, outils en lecture seule) et comment on prouvera qu'il ne les casse pas ;
- **ce qui est parallélisable** : deux lots qui ne touchent pas les mêmes fichiers peuvent
  être menés en même temps dans deux bacs à sable (étape 4) ;
- ce qui change dans `deptrac.yaml`, la base (migration) ou `composer.json` — trois
  fichiers dont la modification demande l'accord de Louis.

**Arrête-toi et présente le plan.** Ensuite, avance sans redemander jusqu'à l'étape 6.

## 4. Implémenter, commit par commit

Pour chaque commit du plan :

1. **Le test d'abord**, et vérifie qu'il échoue pour la bonne raison (pas une erreur de
   syntaxe). Nomme-le d'après le cas métier.
2. Le code minimal qui le fait passer, dans le style des fichiers voisins.
3. `make test` et `make qa`. Les hooks corrigent le style et lancent PHPStan à chaque
   écriture ; ne lutte pas contre eux — s'ils refusent, la règle est dans leur message.
4. Commit local avec le message prévu (corps : **pourquoi**). Jamais de push.

**Travail en parallèle.** Pour un lot indépendant, propose à Louis
`make bac-a-sable NOM=<lot>` : un worktree Git sur sa propre branche, avec sa propre pile
Docker (base comprise) sur un port libre. Il y ouvre une seconde session Claude, qui suit
ce même déroulé à partir de l'étape 4. Les branches se rejoignent par `git merge` une fois
chacune verte ; `make bac-a-sable-fin NOM=<lot>` détruit la pile et le worktree.

## 5. Vérifier avant de déclarer fini

Lance **en parallèle**, dans un seul message :

- l'agent `relecteur-securite` sur les commits de la fonctionnalité ;
- l'agent `relecteur-architecture` sur les mêmes commits ;
- l'agent `chasseur-de-mutants` si `src/Devis` ou `src/Shared` a changé.

Puis **juge leurs constats** au lieu de tous les appliquer : vérifie chacun dans le code,
corrige les fondés dans un commit séparé (« Corriger … relevé en relecture »), et écris en
une ligne pourquoi tu écartes les autres. Tu restes responsable de ce qui part, quel que
soit l'agent qui l'a écrit ou relu.

Enfin `make mutation` sur l'ensemble : le seuil de la CI doit tenir.

## 6. Clore

- Entrée datée dans `JOURNAL.md`, dans le ton des précédentes : ce qui a été décidé, ce qui
  a cassé, ce qui a été écarté et pourquoi. Pas un résumé du diff.
- Dans la fiche : statut « livré », liste des commits, et **date de mesure** du critère de
  succès. Ce jour-là, `/bilan docs/fiches/<fiche>.md` dira si ça a marché.
- Compte rendu à Louis en cinq lignes au plus : ce qui est prêt à pousser, ce qui a été
  écarté, ce qu'il doit décider.
