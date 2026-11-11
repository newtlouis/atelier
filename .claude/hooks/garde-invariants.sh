#!/usr/bin/env bash
# Avant chaque écriture de fichier : refuse le code qui casse un invariant de
# CLAUDE.md ou qui fait taire un outil de vérification.
#
# Deptrac, PHPStan et Infection attrapent beaucoup, mais après coup et sous une
# forme générique. Ici, l'agent est arrêté avant d'écrire, avec la règle métier
# en clair — il corrige du premier coup au lieu de tourner autour d'une erreur.
#
# Code de sortie 2 : l'écriture est bloquée et le message revient à l'agent.
set -euo pipefail

entree=$(cat)
chemin=$(jq -r '.tool_input.file_path // ""' <<<"$entree")
# Write fournit `content`, Edit fournit `new_string` : on ne juge que le texte ajouté.
ajout=$(jq -r '.tool_input.content // .tool_input.new_string // ""' <<<"$entree")
relatif=${chemin#"${CLAUDE_PROJECT_DIR:-$PWD}/"}

refuser() {
    echo "Bloqué par .claude/hooks/garde-invariants.sh ($relatif) : $1" >&2
    exit 2
}

case "$relatif" in
    *.php) ;;
    *) exit 0 ;;
esac

# On ne juge pas les commentaires : « aucun flottant » a le droit d'être écrit.
code=$(sed -E 's@//.*$@@; s@^[[:space:]]*\*.*$@@; s@/\*.*\*/@@g' <<<"$ajout")

# Invariant 2 — les montants sont des centimes entiers.
case "$relatif" in
    src/Devis/*|src/Shared/*)
        if grep -Eq '(:|\()[[:space:]]*\??float\b|\bfloat[[:space:]]+\$|\(float\)|floatval\(|round\(' <<<"$code"; then
            refuser "invariant 2 — aucun flottant dans Devis ni Shared. Les montants sont des centimes entiers (Montant), les taux des centièmes de point (2000 = 20 %)."
        fi
        ;;
esac

# Invariant 1 — aucun modèle de langage ne touche à un montant.
case "$relatif" in
    src/Devis/*)
        if grep -Eq 'Anthropic|App\\Extraction|Extracteur' <<<"$code"; then
            refuser "invariant 1 — Devis ne connaît ni Extraction ni le SDK Anthropic. Le prix se calcule en PHP ; l'orchestration passe par src/Application."
        fi
        ;;
esac

# Faire taire un outil plutôt que corriger ce qu'il signale.
if grep -Eq '@phpstan-ignore|@infection-ignore-all|@codeCoverageIgnore|@psalm-suppress' <<<"$ajout"; then
    refuser "pas d'annotation qui fait taire PHPStan ou Infection. Corrige le type ou écris le test ; si c'est réellement impossible, explique pourquoi à Louis et laisse-le décider."
fi

case "$relatif" in
    tests/*)
        if grep -Eq 'markTestSkipped|markTestIncomplete|#\[Skip|->markAsRisky' <<<"$code"; then
            refuser "un test désactivé est un test supprimé en silence. Fais-le passer ou supprime-le explicitement, avec la raison dans le commit."
        fi
        ;;
esac

exit 0
