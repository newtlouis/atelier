<?php

declare(strict_types=1);

namespace App\Devis\Infrastructure;

use App\Devis\SourceDesEnvois;
use App\Shared\Contrat\EnvoiFormulaire;
use DateTimeImmutable;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use UnexpectedValueException;

/**
 * Lit les envois des formulaires du site dans l'API Netlify Forms.
 *
 * Seuls les formulaires de demande sont repris ; « commande » (achat en ligne)
 * est ignoré. Les envois que Netlify a classés en spam ne sont pas renvoyés par
 * cette route ; ceux qui passent son filtre sont lus par la chaîne comme les
 * autres, qui les classe hors sujet.
 *
 * Données personnelles : seul l'e-mail est repris. Le nom, le téléphone,
 * l'adresse IP et le navigateur restent dans Netlify.
 */
final readonly class EnvoisNetlify implements SourceDesEnvois
{
    private const string API = 'https://api.netlify.com/api/v1';
    private const int PAR_PAGE = 100;
    private const array FORMULAIRES_DE_DEMANDE = ['devis', 'devis-mur', 'devis-piece', 'contact-pro'];

    public function __construct(
        private HttpClientInterface $http,
        private string $jeton,
        private string $idSite,
    ) {}

    public function envois(): array
    {
        $envois = [];
        $page = 1;
        do {
            $soumissions = $this->http->request('GET', self::API . '/sites/' . rawurlencode($this->idSite) . '/submissions', [
                'auth_bearer' => $this->jeton,
                'query' => ['page' => $page, 'per_page' => self::PAR_PAGE],
            ])->toArray();

            foreach ($soumissions as $soumission) {
                if (!\is_array($soumission)) {
                    throw new UnexpectedValueException('Réponse Netlify inattendue : un envoi n\'est pas un objet.');
                }
                $envoi = self::envoiDepuis($soumission);
                if (null !== $envoi) {
                    $envois[] = $envoi;
                }
            }
            ++$page;
        } while (self::PAR_PAGE === \count($soumissions));

        return $envois;
    }

    /**
     * Un envoi Netlify en envoi de formulaire, ou null s'il vient d'un
     * formulaire qui n'est pas une demande.
     *
     * @param array<mixed> $soumission
     */
    public static function envoiDepuis(array $soumission): ?EnvoiFormulaire
    {
        $formulaire = self::texte($soumission, 'form_name');
        if (!\in_array($formulaire, self::FORMULAIRES_DE_DEMANDE, true)) {
            return null;
        }

        $champs = \is_array($soumission['data'] ?? null) ? $soumission['data'] : [];
        $recuLe = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.uP', self::texte($soumission, 'created_at'));
        if (false === $recuLe) {
            throw new UnexpectedValueException('Réponse Netlify inattendue : date d\'envoi illisible.');
        }

        return new EnvoiFormulaire(
            idNetlify: self::texte($soumission, 'id'),
            formulaire: $formulaire,
            recuLe: $recuLe,
            emailClient: self::champ($champs, 'email'),
            message: self::champ($champs, 'message') ?? '',
            projet: self::champ($champs, 'project'),
            surface: self::champ($champs, 'surface'),
            support: self::champ($champs, 'support'),
            ville: self::champ($champs, 'ville'),
            piece: self::champ($champs, 'piece'),
            taille: self::champ($champs, 'taille'),
            besoin: self::champ($champs, 'besoin'),
        );
    }

    /** @param array<mixed> $soumission */
    private static function texte(array $soumission, string $cle): string
    {
        $valeur = $soumission[$cle] ?? null;
        if (!\is_string($valeur)) {
            throw new UnexpectedValueException("Réponse Netlify inattendue : « {$cle} » absent ou mal typé.");
        }

        return $valeur;
    }

    /** @param array<mixed> $champs */
    private static function champ(array $champs, string $cle): ?string
    {
        $valeur = $champs[$cle] ?? null;

        return \is_string($valeur) ? $valeur : null;
    }
}
