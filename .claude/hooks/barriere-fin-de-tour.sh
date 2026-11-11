#!/usr/bin/env bash
# Quand l'agent pense avoir fini : la suite de tests, PHPStan, Deptrac et le style
# doivent passer. Sinon il ne peut pas rendre la main (code 2) et reçoit la sortie.
#
# C'est la version locale de la règle « la CI passe à chaque commit » : l'agent
# n'annonce pas « c'est fait » sur un dépôt rouge. Huit secondes environ, donc
# lancé à chaque fin de tour qui a touché du PHP ou de la configuration.
#
# Le test de mutation n'est pas lancé ici (une minute) : la skill /feature le
# demande avant le commit, et la CI l'exige.
set -euo pipefail

entree=$(cat)

# Deuxième passage après un blocage : on laisse rendre la main pour ne pas boucler.
# L'agent a déjà reçu la sortie ; s'il n'a pas su corriger, c'est à Louis de voir.
if [[ $(jq -r '.stop_hook_active // false' <<<"$entree") == "true" ]]; then
    exit 0
fi

cd "${CLAUDE_PROJECT_DIR:-$PWD}"

# Rien de vérifiable n'a changé : une conversation, une relecture, du Markdown.
if ! git status --porcelain -- src tests config migrations composer.json \
        deptrac.yaml phpstan.neon | grep -q .; then
    exit 0
fi

if ! docker compose ps --status running --services 2>/dev/null | grep -qx app; then
    echo "Le conteneur ne tourne pas : lance « make up » puis vérifie make test et make qa avant de conclure." >&2
    exit 2
fi

echecs=""
for cible in test stan deptrac cs; do
    if ! sortie=$(make --no-print-directory "$cible" 2>&1); then
        echecs+=$'\n'"── make $cible ──"$'\n'"$(tail -40 <<<"$sortie")"
    fi
done

if [[ -n "$echecs" ]]; then
    {
        echo "Le dépôt n'est pas vert : ne conclus pas, corrige d'abord."
        echo "$echecs"
    } >&2
    exit 2
fi

exit 0
