<?php
namespace ZoomRx\Audio\SpeechToText;

use Exception;

/**
 *  A factory for creating instances of SpeechToTextInterface based on the provided service name.
 */
class SpeechToTextFactory
{
    const GOOGLE = 'google';
    const WHISPER = 'whisper';
    const ASSEMBLYAI = 'assemblyai';

    /**
     * Creates and returns an instance of SpeechToTextInterface based on the provided service name.
     * @param {string} $service - The name of the service for which to create the instance.
     * @throws {Exception} If an invalid service name is provided.
     * @return {SpeechToTextInterface} An instance of the SpeechToTextInterface implementation for the provided service.
     */
    public static function create(string $service): SpeechToTextInterface
    {
        switch ($service) {
            case self::GOOGLE:
                return new GoogleSpeechToText();
            case self::WHISPER:
                return new WhisperSpeechToText();
            case self::ASSEMBLYAI:
                return new AssemblyAISpeechToText();
            default:
                throw new Exception('Invalid service');
        }
    }
}
