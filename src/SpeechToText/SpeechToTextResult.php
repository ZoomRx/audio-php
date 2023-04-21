<?php
namespace ZoomRx\Audio\SpeechToText;

/**
 * The SpeechToTextResult class is a PHP class that holds the results of the speech to text operation.
 */
class SpeechToTextResult
{
    /**
     * Configurations provided by user for transcription
     * 
     * @var array
     */
    public $configurations = [];

    /**
     * Transcription of the audio
     * 
     * @var string
     */
    public $transcription = '';

    /**
     * Raw transcription of the audio without speaker diarization
     * 
     * @var string
     */
    public $rawTranscription = '';

    /**
     * Complete response of the transcription service.
     * 
     * @var mixed
     */
    public $response;
    
    /**
     * Get configurations
     *
     * @return array
     */
    public function getConfigurations()
    {
        return $this->configurations;
    }

    /**
     * Set configurations
     *
     * @param array $configurations
     * @return void
     */
    public function setConfigurations($configurations)
    {
        $this->configurations = $configurations;
    }

    /**
     * Get the transcription.
     *
     * @return string
     */
    public function getTranscription(): string
    {
        return $this->transcription;
    }

    /**
     * Set the transcription.
     *
     * @param string $transcription
     */
    public function setTranscription(string $transcription): void
    {
        $this->transcription = $transcription;
    }

    /**
     * Get the raw transcription.
     *
     * @return string
     */
    public function getRawTranscription(): string
    {
        return $this->rawTranscription;
    }

    /**
     * Set the raw transcription.
     *
     * @param string $rawTranscription
     */
    public function setRawTranscription(string $rawTranscription): void
    {
        $this->rawTranscription = $rawTranscription;
    }

    /**
     * Get the complete response of the transcription service.
     *
     * @return mixed
     */
    public function getResponse(): mixed
    {
        return $this->response;
    }

    /**
     * Set the complete response of the transcription service.
     *
     * @param mixed $response
     */
    public function setResponse(mixed $response): void
    {
        $this->response = $response;
    }
}
