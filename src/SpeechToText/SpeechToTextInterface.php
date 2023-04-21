<?php
namespace ZoomRx\Audio\SpeechToText;

/**
 * This is a PHP interface named SpeechToTextInterface for providing functionality for 
 * converting speech to text
 */
interface SpeechToTextInterface {

    /**
     * Sets credentials required for accessing the speech-to-text service
     * 
     * @param array $credentials
     */
    public function setCredentials(array $credentials): void;
    
    /**
     * Sets configurations required for the speech-to-text process
     * 
     * @param array $config
     */
    public function setConfigurations(array $config): void;

    /**
     * Transcribes an audio file to text and returns an instance of the SpeechToTextResult class 
     * containing the transcribed text and any additional information.
     * 
     * @param string $audioFile
     * @return SpeechToTextResult 
     */
    public function transcribe(string $audioFile): SpeechToTextResult;
}
