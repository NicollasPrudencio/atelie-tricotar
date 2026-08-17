<?php
/**
 * Decide qual implementacao usar, lendo do .env. Nenhum outro lugar do
 * plugin deve instanciar as classes de IA diretamente — sempre por aqui.
 */

if (!defined('ABSPATH')) {
    exit;
}

use function Env\env;

class Atelie_Ai_Vision_Service_Factory
{
    public static function criar(): Atelie_Ai_Vision_Service_Interface
    {
        if (filter_var(env('AI_MOCK_MODE'), FILTER_VALIDATE_BOOLEAN)) {
            return new Atelie_Ai_Vision_Service_Mock();
        }

        $provider = env('AI_VISION_PROVIDER') ?: 'gemini';

        return match ($provider) {
            'gemini' => new Atelie_Ai_Vision_Service_Gemini((string) env('AI_VISION_API_KEY')),
            default => throw new RuntimeException("Provedor de IA de visão desconhecido: {$provider}"),
        };
    }
}
