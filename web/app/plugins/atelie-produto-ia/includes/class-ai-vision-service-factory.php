<?php
/**
 * Decide qual implementacao usar, lendo do .env. Nenhum outro lugar do
 * plugin deve instanciar as classes de IA diretamente — sempre por aqui.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Atelie_Ai_Vision_Service_Factory
{
    public static function criar(): Atelie_Ai_Vision_Service_Interface
    {
        if (Atelie_Ai_Config::em_modo_mock()) {
            return new Atelie_Ai_Vision_Service_Mock();
        }

        $provider = Atelie_Ai_Config::obter_provedor();

        return match ($provider) {
            'gemini' => new Atelie_Ai_Vision_Service_Gemini(Atelie_Ai_Config::obter_chave()),
            default => throw new RuntimeException("Provedor de IA de visão desconhecido: {$provider}"),
        };
    }
}
