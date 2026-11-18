<?php

declare(strict_types=1);

namespace App\Shared\Contrat;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Un envoi d'un formulaire du site, tel que Netlify le reçoit.
 *
 * Les demandes réelles n'arrivent pas en texte libre : le formulaire pose déjà
 * des questions (type de projet, surface, support, ville…) et laisse un message,
 * souvent réduit à « 1600*800 mm ». Ce contrat garde cette structure au lieu de
 * tout aplatir en une phrase, pour que la chaîne lise d'abord les champs et
 * n'appelle le modèle que pour le reste.
 *
 * Les valeurs sont gardées telles que le formulaire les envoie (« table-custom »,
 * pas « Table — Sur mesure ») : les interpréter est le travail de l'aiguillage.
 *
 * Données personnelles : seul l'e-mail est gardé, parce qu'il faut répondre.
 * Le nom, le téléphone et l'adresse IP restent dans Netlify.
 */
final readonly class EnvoiFormulaire
{
    /** Identifiant Netlify de l'envoi : rend l'import idempotent. */
    public string $idNetlify;
    /** Nom du formulaire : devis, devis-mur, devis-piece, contact-pro. */
    public string $formulaire;
    public DateTimeImmutable $recuLe;
    public ?string $emailClient;
    public string $message;
    public ?string $projet;
    public ?string $surface;
    public ?string $support;
    public ?string $ville;
    public ?string $piece;
    public ?string $taille;
    public ?string $besoin;

    public function __construct(
        string $idNetlify,
        string $formulaire,
        DateTimeImmutable $recuLe,
        ?string $emailClient,
        string $message,
        ?string $projet = null,
        ?string $surface = null,
        ?string $support = null,
        ?string $ville = null,
        ?string $piece = null,
        ?string $taille = null,
        ?string $besoin = null,
    ) {
        $this->idNetlify = self::obligatoire($idNetlify, 'identifiant Netlify');
        $this->formulaire = self::obligatoire($formulaire, 'nom du formulaire');
        $this->recuLe = $recuLe;
        $this->emailClient = self::nettoye($emailClient);
        $this->message = trim($message);
        $this->projet = self::nettoye($projet);
        $this->surface = self::nettoye($surface);
        $this->support = self::nettoye($support);
        $this->ville = self::nettoye($ville);
        $this->piece = self::nettoye($piece);
        $this->taille = self::nettoye($taille);
        $this->besoin = self::nettoye($besoin);
    }

    /**
     * Les champs renseignés puis le message, une ligne chacun, dans un ordre fixe.
     *
     * C'est ce qui part vers le modèle quand le message ne se lit pas seul, et
     * ce qui est gardé comme texte de la demande. L'e-mail n'y figure jamais.
     */
    public function texteClient(): string
    {
        $lignes = [];
        foreach ([
            'Projet' => $this->projet,
            'Surface' => $this->surface,
            'Support' => $this->support,
            'Ville' => $this->ville,
            'Pièce' => $this->piece,
            'Taille' => $this->taille,
            'Besoin' => $this->besoin,
            'Message' => '' !== $this->message ? $this->message : null,
        ] as $libelle => $valeur) {
            if (null !== $valeur) {
                $lignes[] = $libelle . ' : ' . $valeur;
            }
        }

        return implode("\n", $lignes);
    }

    private static function nettoye(?string $valeur): ?string
    {
        $valeur = trim($valeur ?? '');

        return '' !== $valeur ? $valeur : null;
    }

    private static function obligatoire(string $valeur, string $nom): string
    {
        $valeur = trim($valeur);
        if ('' === $valeur) {
            throw new InvalidArgumentException(\sprintf('Un envoi de formulaire sans %s ne peut pas être importé.', $nom));
        }

        return $valeur;
    }
}
