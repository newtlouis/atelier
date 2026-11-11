#!/usr/bin/env bash
# Avant chaque commande shell de l'agent : refuse ce qui détruit, contourne une
# vérification ou envoie quelque chose hors de la machine.
#
# Les règles `deny` de settings.json couvrent la forme habituelle d'une commande
# (`git push origin main`), pas ses variantes (`git -C . push`, `sh -c '…'`). Ce
# script les rattrape sur le texte complet. Ce n'est pas une frontière de sécurité
# absolue — un programme qui lance lui-même `git push` passerait — mais il ferme
# les chemins qu'un agent emprunte réellement.
#
# Code de sortie 2 : la commande est bloquée et le message revient à l'agent.
set -euo pipefail

commande=$(jq -r '.tool_input.command // ""')

refuser() {
    echo "Bloqué par .claude/hooks/garde-commandes.sh : $1" >&2
    exit 2
}

# Publier reste une décision humaine : la CI tourne sur main, et main part en ligne.
if grep -Eq '(^|[^[:alnum:]_-])git([[:space:]]+-[^[:space:]]+([[:space:]]+[^-[:space:]][^[:space:]]*)?)*[[:space:]]+push([^[:alnum:]_-]|$)' <<<"$commande"; then
    refuser "aucune publication depuis l'agent. Prépare les commits, Louis pousse."
fi

# Une vérification qu'on peut sauter n'en est pas une.
if grep -Eq -- '--no-verify|--no-gpg-sign|-c[[:space:]]+core\.hooksPath' <<<"$commande"; then
    refuser "contourner les hooks Git est interdit. Corrige ce qu'ils signalent."
fi

# Réécrire l'historique publié ou jeter du travail non commité.
if grep -Eq -- '--force([^-]|$)|--force-with-lease|push[[:space:]].*[[:space:]]-f([[:space:]]|$)|reset[[:space:]]+--hard' <<<"$commande"; then
    refuser "réécriture d'historique ou perte de travail. Demande à Louis."
fi

# Le volume Postgres contient les compteurs de factures : le détruire casse la
# numérotation sans trou, qui est une obligation légale.
if grep -Eq -- 'down[[:space:]].*-v|down[[:space:]]+--volumes|volume[[:space:]]+(rm|prune)|system[[:space:]]+prune' <<<"$commande"; then
    refuser "suppression de volumes Docker. Les données de facturation y vivent."
fi

# rm -r n'est toléré que dans var/ (cache, journaux, rapports).
if grep -Eq '(^|[;&|[:space:]])rm[[:space:]]+(-[a-zA-Z]*[rR][a-zA-Z]*|--recursive)' <<<"$commande"; then
    cibles=$(sed -E 's/.*rm[[:space:]]+(-[^[:space:]]+[[:space:]]+)*//' <<<"$commande")
    for cible in $cibles; do
        case "$cible" in
            var/*|./var/*) ;;
            *) refuser "rm récursif hors de var/ ($cible). Supprime les fichiers un par un, en les nommant." ;;
        esac
    done
fi

# Faire taire PHPStan au lieu de corriger : une baseline ne se régénère pas en douce.
if grep -Eq -- '--generate-baseline|--allow-empty-baseline' <<<"$commande"; then
    refuser "la baseline PHPStan est vide et doit le rester. Corrige les erreurs."
fi

# Les secrets ne s'affichent pas : ils finiraient dans la conversation.
# Chercher le nom de la variable dans le code reste permis ; lire sa valeur, non.
if grep -Eq '(^|[;&|[:space:]])(printenv|env)([[:space:]]*($|[;&|]))|\$\{?ANTHROPIC_API_KEY|printenv[[:space:]]+ANTHROPIC|\.env(\.[a-z]+)?\.local' <<<"$commande"; then
    refuser "lecture de secrets. La clé d'API n'a pas à transiter par l'agent."
fi

exit 0
