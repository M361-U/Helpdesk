<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Générateur de PDF minimal, sans bibliothèque externe.
 * Police Helvetica (intégrée à tous les lecteurs PDF), texte seulement, tableaux avec retour à la ligne.
 * Les coordonnées sont exprimées en points (1 pt = 1/72 pouce), mesurées depuis le HAUT de la page.
 */
final class Pdf
{
    private const MARGE = 36.0;

    /** Largeurs des caractères ASCII 32 à 126 (en 1/1000 de la taille de police). */
    private const LARGEURS = [
        'N' => [278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584],
        'B' => [278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,611,611,389,556,333,611,556,778,556,556,500,389,280,389,584],
    ];

    private float $largeur;
    private float $hauteur;
    private array $pages = [];       // contenu (flux) de chaque page
    private float $y = 0.0;          // position verticale courante (depuis le haut)
    private string $piedDePage = '';

    public function __construct(bool $paysage = false)
    {
        [$this->largeur, $this->hauteur] = $paysage ? [841.89, 595.28] : [595.28, 841.89];
        $this->nouvellePage();
    }

    // ---------- API de mise en page ----------

    public function titre(string $texte): void
    {
        $this->ecrire(self::MARGE, $this->y + 18, $texte, 18, true);
        $this->y += 26;
    }

    public function sousTitre(string $texte): void
    {
        $this->y += 6;
        $this->verifierPlace(24);
        $this->ecrire(self::MARGE, $this->y + 12, $texte, 12, true);
        $this->ligne(self::MARGE, $this->y + 17, $this->largeur - self::MARGE, $this->y + 17, 0.6);
        $this->y += 24;
    }

    public function paragraphe(string $texte, float $taille = 10.0, bool $gras = false): void
    {
        foreach ($this->decouper($texte, $this->largeur - 2 * self::MARGE, $taille, $gras) as $ligne) {
            $this->verifierPlace($taille + 4);
            $this->ecrire(self::MARGE, $this->y + $taille, $ligne, $taille, $gras);
            $this->y += $taille + 3.5;
        }
    }

    public function espace(float $points): void
    {
        $this->y += $points;
    }

    /** Ligne « Libellé : valeur » (le libellé en gras, valeur éventuellement sur plusieurs lignes). */
    public function cleValeur(string $cle, string $valeur): void
    {
        $xValeur = self::MARGE + 110;
        $lignes = $this->decouper($valeur === '' ? '—' : $valeur, $this->largeur - self::MARGE - $xValeur, 10, false);
        $this->verifierPlace(count($lignes) * 13.5);
        $this->ecrire(self::MARGE, $this->y + 10, $cle, 10, true);
        foreach ($lignes as $ligne) {
            $this->ecrire($xValeur, $this->y + 10, $ligne, 10, false);
            $this->y += 13.5;
        }
    }

    public function piedDePage(string $texte): void
    {
        $this->piedDePage = $texte;
    }

    /**
     * Tableau avec en-tête répété à chaque page.
     * @param array<int, array{0: string, 1: float}> $colonnes [libellé, largeur en points]
     * @param array<int, array<int, string>> $lignes
     */
    public function tableau(array $colonnes, array $lignes): void
    {
        $taille = 8.5;
        $interligne = 11.0;
        $marge = 4.0;

        $entete = function () use ($colonnes, $taille, $marge): void {
            $this->verifierPlace(22);
            $largeurTotale = array_sum(array_column($colonnes, 1));
            $this->rectangle(self::MARGE, $this->y, $largeurTotale, 18, 0.89);
            $x = self::MARGE;
            foreach ($colonnes as [$libelle, $largeur]) {
                $this->ecrire($x + $marge, $this->y + 12.5, $libelle, $taille, true);
                $x += $largeur;
            }
            $this->y += 18;
        };

        $entete();
        foreach ($lignes as $cellules) {
            $decoupes = [];
            $nbLignes = 1;
            foreach ($cellules as $i => $texte) {
                $decoupes[$i] = $this->decouper($texte, $colonnes[$i][1] - 2 * $marge, $taille, false);
                $nbLignes = max($nbLignes, count($decoupes[$i]));
            }
            $hauteurLigne = $nbLignes * $interligne + 6;

            if ($this->y + $hauteurLigne > $this->hauteur - self::MARGE - 14) {
                $this->nouvellePage();
                $entete();
            }
            $x = self::MARGE;
            foreach ($decoupes as $i => $lignesCellule) {
                foreach ($lignesCellule as $k => $ligne) {
                    $this->ecrire($x + $marge, $this->y + 3 + $taille + $k * $interligne, $ligne, $taille, false);
                }
                $x += $colonnes[$i][1];
            }
            $this->y += $hauteurLigne;
            $this->ligne(self::MARGE, $this->y, self::MARGE + array_sum(array_column($colonnes, 1)), $this->y, 0.3);
        }
    }

    /** Assemble et retourne le fichier PDF complet. */
    public function contenu(): string
    {
        $total = count($this->pages);
        $objets = [];
        $objets[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objets[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objets[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $references = [];
        foreach ($this->pages as $i => $flux) {
            // Pied de page : texte à gauche, « Page n / N » à droite
            $pied = $this->ecritureBrute(self::MARGE, $this->hauteur - 22, $this->piedDePage, 8, false)
                  . $this->ecritureBrute($this->largeur - self::MARGE - 60, $this->hauteur - 22, 'Page ' . ($i + 1) . ' / ' . $total, 8, false);
            $flux .= $pied;

            $numeroPage = 5 + 2 * $i;
            $numeroFlux = $numeroPage + 1;
            $references[] = $numeroPage . ' 0 R';
            $objets[$numeroPage] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
                $this->largeur, $this->hauteur, $numeroFlux
            );
            $objets[$numeroFlux] = '<< /Length ' . strlen($flux) . " >>\nstream\n" . $flux . "\nendstream";
        }
        $objets[2] = '<< /Type /Pages /Kids [' . implode(' ', $references) . '] /Count ' . $total . ' >>';

        ksort($objets);
        $pdf = "%PDF-1.4\n";
        $positions = [];
        foreach ($objets as $numero => $corps) {
            $positions[$numero] = strlen($pdf);
            $pdf .= $numero . " 0 obj\n" . $corps . "\nendobj\n";
        }
        $debutXref = strlen($pdf);
        $pdf .= 'xref' . "\n" . '0 ' . (count($objets) + 1) . "\n0000000000 65535 f \n";
        foreach ($positions as $position) {
            $pdf .= sprintf("%010d 00000 n \n", $position);
        }
        $pdf .= 'trailer << /Size ' . (count($objets) + 1) . ' /Root 1 0 R >>' . "\nstartxref\n" . $debutXref . "\n%%EOF";
        return $pdf;
    }

    /** Envoie le PDF au navigateur (téléchargement). */
    public function telecharger(string $nomFichier): never
    {
        $contenu = $this->contenu();
        header('Content-Type: application/pdf');
        header('Content-Length: ' . strlen($contenu));
        header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($nomFichier));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        echo $contenu;
        exit;
    }

    // ---------- outils internes ----------

    private function nouvellePage(): void
    {
        $this->pages[] = '';
        $this->y = self::MARGE;
    }

    private function verifierPlace(float $hauteurNecessaire): void
    {
        if ($this->y + $hauteurNecessaire > $this->hauteur - self::MARGE - 14) {
            $this->nouvellePage();
        }
    }

    private function ecrire(float $x, float $yDepuisHaut, string $texte, float $taille, bool $gras): void
    {
        $this->pages[count($this->pages) - 1] .= $this->ecritureBrute($x, $yDepuisHaut, $texte, $taille, $gras);
    }

    private function ecritureBrute(float $x, float $yDepuisHaut, string $texte, float $taille, bool $gras): string
    {
        return sprintf("BT /%s %.1F Tf %.2F %.2F Td (%s) Tj ET\n",
            $gras ? 'F2' : 'F1', $taille, $x, $this->hauteur - $yDepuisHaut, $this->echapper($texte));
    }

    private function ligne(float $x1, float $y1, float $x2, float $y2, float $epaisseur): void
    {
        $this->pages[count($this->pages) - 1] .= sprintf("%.2F w %.2F %.2F m %.2F %.2F l S\n",
            $epaisseur, $x1, $this->hauteur - $y1, $x2, $this->hauteur - $y2);
    }

    private function rectangle(float $x, float $yDepuisHaut, float $l, float $h, float $gris): void
    {
        $this->pages[count($this->pages) - 1] .= sprintf("%.2F g %.2F %.2F %.2F %.2F re f 0 g\n",
            $gris, $x, $this->hauteur - $yDepuisHaut - $h, $l, $h);
    }

    /** UTF-8 -> Windows-1252, puis échappement des caractères spéciaux du format PDF. */
    private function echapper(string $texte): string
    {
        $texte = mb_convert_encoding($texte, 'Windows-1252', 'UTF-8');
        return strtr($texte, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '', "\n" => ' ']);
    }

    /** Largeur d'un texte en points. Les lettres accentuées se mesurent comme leur lettre de base. */
    private function mesurer(string $texte, float $taille, bool $gras): float
    {
        $table = self::LARGEURS[$gras ? 'B' : 'N'];
        $simple = strtr($texte, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c',
            'À' => 'A', 'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ô' => 'O', 'Ç' => 'C',
        ]);
        $somme = 0;
        foreach (mb_str_split($simple) as $caractere) {
            $code = ord($caractere);
            $somme += ($code >= 32 && $code <= 126 && strlen($caractere) === 1) ? $table[$code - 32] : 556;
        }
        return $somme * $taille / 1000;
    }

    /** Coupe un texte en lignes qui tiennent dans la largeur donnée (retour à la ligne aux espaces). */
    private function decouper(string $texte, float $largeurMax, float $taille, bool $gras): array
    {
        $lignes = [];
        foreach (preg_split('/\R/u', $texte) ?: [''] as $paragraphe) {
            $courante = '';
            foreach (preg_split('/\s+/u', trim($paragraphe)) ?: [''] as $mot) {
                // Mot plus long que la ligne : on le coupe
                while ($this->mesurer($mot, $taille, $gras) > $largeurMax && mb_strlen($mot) > 1) {
                    $morceau = $mot;
                    while ($this->mesurer($morceau, $taille, $gras) > $largeurMax && mb_strlen($morceau) > 1) {
                        $morceau = mb_substr($morceau, 0, -1);
                    }
                    if ($courante !== '') { $lignes[] = $courante; $courante = ''; }
                    $lignes[] = $morceau;
                    $mot = mb_substr($mot, mb_strlen($morceau));
                }
                $essai = $courante === '' ? $mot : $courante . ' ' . $mot;
                if ($courante !== '' && $this->mesurer($essai, $taille, $gras) > $largeurMax) {
                    $lignes[] = $courante;
                    $courante = $mot;
                } else {
                    $courante = $essai;
                }
            }
            $lignes[] = $courante;
        }
        return $lignes;
    }
}