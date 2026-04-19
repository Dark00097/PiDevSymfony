<?php

namespace App\Service;

final class SentimentAnalysisService
{
    // Mots-clés pour chaque sentiment
    private const SENTIMENT_KEYWORDS = [
        'angry' => [
            // Français
            'furieux', 'colère', 'énervé', 'inacceptable', 'scandaleux', 'honteux',
            'inadmissible', 'révoltant', 'insupportable', 'exaspérant', 'agacé',
            'mécontent', 'frustré', 'irrité', 'fâché', 'outré', 'choqué',
            // Arabe/Tunisien
            'mzawej', 'mte3seb', 'ghaltan', 'ma3andich sabr',
        ],
        'sad' => [
            // Français
            'triste', 'déçu', 'décevant', 'dommage', 'malheureusement', 'regret',
            'désespéré', 'abattu', 'découragé', 'peiné', 'affligé', 'malheureux',
            'navré', 'attristé', 'chagrin', 'désolé',
            // Arabe/Tunisien
            'mahzoun', 'mkhayeb', 'ya7sra',
        ],
        'worried' => [
            // Français
            'inquiet', 'préoccupé', 'soucieux', 'anxieux', 'stressé', 'nerveux',
            'angoissé', 'tracassé', 'tourmenté', 'problème', 'urgent', 'grave',
            'sérieux', 'critique', 'alarme', 'peur', 'crainte',
            // Arabe/Tunisien
            'khayef', 'mkallek', 'mhayer',
        ],
        'neutral' => [
            // Français
            'question', 'demande', 'information', 'renseignement', 'clarification',
            'explication', 'détail', 'précision', 'confirmation', 'vérification',
        ],
        'happy' => [
            // Français
            'merci', 'content', 'satisfait', 'excellent', 'parfait', 'super',
            'génial', 'formidable', 'magnifique', 'bravo', 'félicitations',
            'apprécié', 'ravi', 'enchanté', 'heureux', 'bien', 'bon',
            // Arabe/Tunisien
            'barcha', 'behi', 'mriguel', 'farhane',
        ],
    ];

    // Emojis correspondants
    private const SENTIMENT_EMOJIS = [
        'angry' => '😠',
        'sad' => '😢',
        'worried' => '😰',
        'neutral' => '😐',
        'happy' => '😊',
    ];

    // Labels en français
    private const SENTIMENT_LABELS = [
        'angry' => 'En colère',
        'sad' => 'Triste',
        'worried' => 'Inquiet',
        'neutral' => 'Neutre',
        'happy' => 'Content',
    ];

    /**
     * Analyse le sentiment d'un texte
     * 
     * @return array{sentiment: string, emoji: string, label: string, confidence: float}
     */
    public function analyzeSentiment(string $text): array
    {
        $text = $this->normalizeText($text);
        
        // Compter les occurrences de chaque sentiment
        $scores = [
            'angry' => 0,
            'sad' => 0,
            'worried' => 0,
            'neutral' => 0,
            'happy' => 0,
        ];

        foreach (self::SENTIMENT_KEYWORDS as $sentiment => $keywords) {
            foreach ($keywords as $keyword) {
                $normalizedKeyword = $this->normalizeText($keyword);
                // Compter le nombre d'occurrences
                $count = substr_count($text, $normalizedKeyword);
                $scores[$sentiment] += $count;
            }
        }

        // Détection de ponctuation émotionnelle
        $exclamationCount = substr_count($text, '!');
        $questionCount = substr_count($text, '?');
        $capsRatio = $this->calculateCapsRatio($text);

        // Ajuster les scores selon la ponctuation
        if ($exclamationCount >= 2) {
            $scores['angry'] += 2;
        }
        if ($exclamationCount >= 1) {
            $scores['worried'] += 1;
        }
        if ($questionCount >= 2) {
            $scores['worried'] += 1;
            $scores['neutral'] += 1;
        }
        if ($capsRatio > 0.5) {
            $scores['angry'] += 3;
        }

        // Trouver le sentiment dominant
        $maxScore = max($scores);
        $dominantSentiment = 'neutral';
        
        if ($maxScore > 0) {
            $dominantSentiment = array_search($maxScore, $scores);
        }

        // Calculer la confiance (0-1)
        $totalScore = array_sum($scores);
        $confidence = $totalScore > 0 ? $maxScore / $totalScore : 0.5;

        return [
            'sentiment' => $dominantSentiment,
            'emoji' => self::SENTIMENT_EMOJIS[$dominantSentiment],
            'label' => self::SENTIMENT_LABELS[$dominantSentiment],
            'confidence' => round($confidence, 2),
            'scores' => $scores,
        ];
    }

    /**
     * Normalise le texte pour l'analyse
     */
    private function normalizeText(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        
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
     * Calcule le ratio de majuscules dans le texte
     */
    private function calculateCapsRatio(string $text): float
    {
        $letters = preg_replace('/[^a-zA-Z]/', '', $text);
        if (strlen($letters) === 0) {
            return 0;
        }
        
        $uppercase = preg_replace('/[^A-Z]/', '', $text);
        return strlen($uppercase) / strlen($letters);
    }

    /**
     * Obtient l'emoji pour un sentiment donné
     */
    public function getEmojiForSentiment(string $sentiment): string
    {
        return self::SENTIMENT_EMOJIS[$sentiment] ?? self::SENTIMENT_EMOJIS['neutral'];
    }

    /**
     * Obtient le label pour un sentiment donné
     */
    public function getLabelForSentiment(string $sentiment): string
    {
        return self::SENTIMENT_LABELS[$sentiment] ?? self::SENTIMENT_LABELS['neutral'];
    }
}
