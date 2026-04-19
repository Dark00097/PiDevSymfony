<?php

namespace App\Service;

final class BadWordDetectionService
{
    // Liste de mots inappropriés en français et arabe (tunisien)
    private const BAD_WORDS = [
        // Français
        'merde', 'putain', 'connard', 'salaud', 'enculé', 'fils de pute',
        'con', 'conne', 'connasse', 'pute', 'salope', 'bordel',
        'chier', 'foutre', 'bite', 'couille', 'cul', 'nique',
        'ta gueule', 'ferme ta gueule', 'va te faire', 'enfoiré',
        'batard', 'bâtard', 'crétin', 'débile', 'idiot', 'imbécile',
        
        // Arabe/Tunisien (translittération)
        'kahba', 'ka7ba', 'zebbi', 'zeb', 'kess', 'omek', 'bouk',
        'ya7chilek', 'nayek', 'nik', 'tfou', 'hmar', '7mar',
        'kelb', 'kalb', 'weld el kahba', 'ibn el kahba',
        
        // Variantes
        'p*tain', 'c*n', 'm*rde', 'sal*pe', 'enc*lé',
    ];

    /**
     * Détecte si le texte contient des mots inappropriés
     * 
     * @param string $text Texte à analyser
     * @return array{has_badwords: bool, detected_words: array, censored_text: string}
     */
    public function detectBadWords(string $text): array
    {
        $normalizedText = $this->normalizeText($text);
        $detectedWords = [];
        $censoredText = $text;

        foreach (self::BAD_WORDS as $badWord) {
            $normalizedBadWord = $this->normalizeText($badWord);
            
            // Recherche exacte et avec variations
            if (str_contains($normalizedText, $normalizedBadWord)) {
                $detectedWords[] = $badWord;
                
                // Censurer le mot dans le texte
                $pattern = '/' . preg_quote($badWord, '/') . '/iu';
                $replacement = $this->censorWord($badWord);
                $censoredText = preg_replace($pattern, $replacement, $censoredText);
            }
        }

        return [
            'has_badwords' => count($detectedWords) > 0,
            'detected_words' => array_unique($detectedWords),
            'censored_text' => $censoredText,
        ];
    }

    /**
     * Normalise le texte pour la détection (enlève accents, espaces, etc.)
     */
    private function normalizeText(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        
        // Remplacer les caractères spéciaux utilisés pour contourner les filtres
        $replacements = [
            '@' => 'a',
            '0' => 'o',
            '1' => 'i',
            '3' => 'e',
            '4' => 'a',
            '5' => 's',
            '7' => 'h',
            '8' => 'b',
            '$' => 's',
            '*' => '',
            '_' => '',
            '-' => '',
            '.' => '',
            ' ' => '',
        ];
        
        $text = strtr($text, $replacements);
        
        // Enlever les accents
        $text = strtr($text, [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
        ]);
        
        return $text;
    }

    /**
     * Censure un mot en le remplaçant par des astérisques
     */
    private function censorWord(string $word): string
    {
        $length = mb_strlen($word, 'UTF-8');
        
        if ($length <= 2) {
            return str_repeat('*', $length);
        }
        
        // Garder la première lettre, remplacer le reste par des *
        return mb_substr($word, 0, 1, 'UTF-8') . str_repeat('*', $length - 1);
    }

    /**
     * Floute complètement un texte
     */
    public function blurText(string $text): string
    {
        return '[CONTENU MODÉRÉ PAR L\'ADMINISTRATEUR]';
    }

    /**
     * Vérifie si un texte est approprié (pas de badwords)
     */
    public function isAppropriate(string $text): bool
    {
        $result = $this->detectBadWords($text);
        return !$result['has_badwords'];
    }
}
