---
name: bilan
description: À la date de mesure d'une fiche livrée, vérifie dans les données si la fonctionnalité a atteint son critère de succès, et propose de la garder, l'ajuster ou la retirer.
argument-hint: "[docs/fiches/AAAA-MM-JJ-sujet.md — par défaut : les fiches livrées dont la date de mesure est passée]"
disable-model-invocation: true
---

# A-t-elle servi ?

Fiche : **$ARGUMENTS** — si vide, liste les fiches de `docs/fiches/` au statut « livrée »
dont la date de mesure est passée, et demande laquelle traiter.

Livrer n'est pas finir. Une fonctionnalité qui ne déplace pas sa mesure est une dette :
du code à maintenir, à tester et à comprendre, sans contrepartie.

1. Relis la fiche : problème, critère de succès, source de la donnée, valeur de départ.
2. **Va chercher la mesure réelle** à la source indiquée (requête en base, journaux,
   `AppelLlm`). Montre la requête et son résultat brut avant toute interprétation.
3. Compare à la cible, et **cherche ce qui pourrait fausser la lecture** : trop peu de cas
   pour conclure, saisonnalité, autre changement livré entre-temps, mesure qui a changé de
   définition. Dis-le s'il est trop tôt pour trancher.
4. Propose une décision argumentée :
   - **garder** — la cible est atteinte ;
   - **ajuster** — l'effet existe mais rate la cible ; quelle hypothèse était fausse ;
   - **retirer** — aucun effet mesurable ; ce que le retrait coûte et rapporte.
5. Remplis la rubrique « Bilan » de la fiche, passe son statut à « mesurée », et ajoute
   une entrée courte au `JOURNAL.md` — surtout si le résultat est décevant : c'est
   l'entrée la plus utile du journal.
