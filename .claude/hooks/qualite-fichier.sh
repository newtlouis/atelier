#!/usr/bin/env bash
# Après chaque écriture d'un fichier PHP : style corrigé en place, puis PHPStan
# niveau 10 sur ce seul fichier.
#
# Le retour arrive pendant que l'agent a encore le fichier en tête, pas vingt
# fichiers plus tard au moment de la CI. Le style est corrigé sans discussion ;
# une erreur de typage est renvoyée à l'agent (code 2), qui doit la corriger.
#
# Si le conteneur ne tourne pas — un worktree sans pile démarrée, par exemple —
# le contrôle est sauté sans bloquer : la barrière de fin de tour et la CI restent.
set -euo pipefail

chemin=$(jq -r '.tool_input.file_path // ""')
racine=${CLAUDE_PROJECT_DIR:-$PWD}
relatif=${chemin#"$racine/"}

case "$relatif" in
    src/*.php|tests/*.php) ;;
    *) exit 0 ;;
esac
[[ -f "$chemin" ]] || exit 0

cd "$racine"
if ! docker compose ps --status running --services 2>/dev/null | grep -qx app; then
    echo "qualite-fichier : conteneur arrêté, contrôle sauté (make up pour le réactiver)." >&2
    exit 0
fi

docker compose exec -T app vendor/bin/php-cs-fixer fix --quiet "$relatif" >/dev/null 2>&1 || true

if ! sortie=$(docker compose exec -T app vendor/bin/phpstan analyse --no-progress \
        --error-format=raw --memory-limit=512M "$relatif" 2>&1); then
    {
        echo "PHPStan niveau 10 refuse $relatif :"
        grep -v '^ *$' <<<"$sortie" | head -30
        echo "Corrige le typage ; n'ajoute ni @phpstan-ignore ni entrée de baseline."
    } >&2
    exit 2
fi

exit 0
