<?php
namespace ZoomRx\Audio\SpeechToText;

use Exception;
use Locale;
use ZoomRx\Audio\Exceptions\FileNotFoundException;
use ZoomRx\Audio\Utility\CurlRequest;

/**
 * This class is an implementation of the `SpeechToTextInterface` interface and provides a way to transcribe speech to text using the AssemblyAI API.
 * 
 * @implements SpeechToTextInterface
 */
class AssemblyAISpeechToText implements SpeechToTextInterface
{
    /**
     * The API credential key.
     */
    const API_CREDENTIAL = 'api_credential';

    /**
     * The endpoint for submitting a transcription request.
     */
    const TRANSCRIPTION_ENDPOINT = 'https://api.assemblyai.com/v2/transcript';

    /**
     * The endpoint for getting the result of a transcription request.
     */
    const TRANSCRIPTION_RESULT_ENDPOINT = 'https://api.assemblyai.com/v2/transcript/';

    /**
     * The endpoint for uploading an audio file to AssemblyAI.
     */
    const UPLOAD_ENDPOINT = 'https://api.assemblyai.com/v2/upload';

    /**
     * The endpoint for deleting a completed transcription.
     */
    const DELETE_ENDPOINT = 'https://api.assemblyai.com/v2/transcript/';

    /**
     * The default language code to use if not specified in the configurations.
     */
    const DEFAULT_LANGUAGE_CODE = 'en_us';

    /**
     * A common array mapping configuration keys to API parameter keys.
     */
    const CONFIG_MAP = [
        'language' => 'language_code',
    ];

    /**
     * Array of valid configuration keys.
     */
    const VALID_ZRX_CONFIG = [
        'language',
        'speaker_labels',
        'speakers_expected',
        'word_boost',
        'boost_param',
        'language_detection',
        'punctuate',
        'format_text',
        'redact_pii',
        'redact_pii_policies',
        'redact_pii_sub',
        'auto_highlights',
        'content_safety',
        'summary_model',
        'summary_type',
        'entity_detection',
    ];

    /**
     * An array containing the API credentials.
     *
     * @var array
     */
    private $credentials = [];

    /**
     * An array containing the configuration options for the transcription request.
     *
     * @var array
     */
    private $configurations = [];

    /**
     * Sets the API credentials.
     * 
     * @param array $credentials An array containing the API credentials.
     * @return void
     */
    public function setCredentials(array $credentials): void
    {
        $this->credentials = $credentials;
    }

    /**
     * Sets the configurations for the transcription request.
     * 
     * @param array $configurations An array containing the configuration options for the transcription request.
     * @return void
     */
    public function setConfigurations(array $configurations): void
    {
        $this->configurations = $configurations;
    }

    /**
     * Validates the audio file and API credentials before submitting a transcription request.
     * 
     * @param string $audioFile The path to the audio file or URL of the audio file.
     * 
     * @throws Exception If the API credentials or audio file path are invalid.
     * @return void
     */
    private function _validateRequest(string $audioFile): void
    {
        if (!isset($this->credentials[self::API_CREDENTIAL])) {
            throw new Exception('API credentials are not set');
        }

        if (strpos($audioFile, 'http') !== 0 && !file_exists($audioFile)) {
            throw new FileNotFoundException('Invalid file path provided for transcription');
        }
    }

    /**
     * Transcribes the audio in the specified file and returns the transcription result.
     *
     * @param string $audioFile The path to the audio file or URL of the audio file to transcribe.
     *
     * @throws Exception If there is an error during the transcription process.
     * @return SpeechToTextResult A result object containing the transcription and raw transcription text.
     */
    public function transcribe(string $audioFile): SpeechToTextResult
    {
        $this->_validateRequest($audioFile);

        $this->configurations['service'] = SpeechToTextFactory::ASSEMBLYAI;

        $requestHeaders = [
            'Authorization: ' . $this->credentials[self::API_CREDENTIAL],
        ];

        $requestData = $this->_getRequestData();

        try {
            if (strpos($audioFile, 'http') !== 0) {
                $audioUrl = $this->_uploadAudio($audioFile);
            } else {
                $audioUrl = $audioFile;
            }

            $requestData['audio_url'] = $audioUrl;

            $response = CurlRequest::send(
                self::TRANSCRIPTION_ENDPOINT,
                CurlRequest::POST,
                $requestHeaders,
                json_encode($requestData)
            );

            $queueOperation = json_decode($response, true);
            if (!empty($queueOperation['error'])) {
                throw new Exception($queueOperation['error']);
            }

            if (empty($queueOperation['id'])) {
                throw new Exception("Transcription queue ID not found - " . json_encode($queueOperation));
            }
            
            $response = $this->_pollUntilFinished($queueOperation['id']);

            $this->_deleteTranscript($queueOperation['id']);

            if ($response['status'] == 'error') {
                throw new Exception("{$response['error']} for the transcription id {$queueOperation['id']}");
            }

            $speechToTextResult = new SpeechToTextResult();
            $speechToTextResult->setResponse($response);
            $speechToTextResult->setConfigurations($this->configurations);
            $this->_setTranscription($speechToTextResult, $response);
            
            return $speechToTextResult;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Parses the configuration options into an array of API parameters for the transcription request.
     * 
     * @return array An array of API parameters.
     */
    private function _getRequestData(): array
    {
        $requestData = [];
        $requestData['language_code'] = self::DEFAULT_LANGUAGE_CODE;

        foreach ($this->configurations as $key => $value) {
            if (!in_array($key, self::VALID_ZRX_CONFIG)) {
                continue;
            }
            $config = self::CONFIG_MAP[$key] ?? $key;
            $method = '_parse' . str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));

            if (method_exists($this, $method)) {
                $requestData[$config] = $this->{$method}($value);
            } else {
                $requestData[$config] = $value;
            }
        }

        return $requestData;
    }

    /**
     * Uploads the audio file to the AssemblyAI server for transcription.
     *
     * @param string $audioFile The path of the audio file.
     *
     * @return string Returns the upload URL to be used in the transcription request.
     * @throws Exception If upload fails.
     */
    private function _uploadAudio(string $audioFile): string
    {
        $requestHeaders = [
            'Authorization: ' . $this->credentials[self::API_CREDENTIAL],
        ];

        $response = CurlRequest::send(
            self::UPLOAD_ENDPOINT,
            CurlRequest::POST,
            $requestHeaders,
            file_get_contents($audioFile)
        );

        $responseDecoded = json_decode($response, true);

        if (empty($responseDecoded['upload_url'])) {
            throw new Exception('Failed to upload audio to AssemblyAI server. Response: ' . $response);
        }

        return $responseDecoded['upload_url'];
    }

    /**
     * Polls the AssemblyAI server until the transcription is finished.
     *
     * @param string $transcriptionId The ID of the transcription task.
     * @return array The response from the server with the transcription details.
     */
    private function _pollUntilFinished(string $transcriptionId)
    {
        $requestHeaders = [
            'Authorization: ' . $this->credentials[self::API_CREDENTIAL],
        ];

        $sleepTime = 5;
        $multiplier = 1.5;
        $maxSleepTime = 30;

        while (true) {
            $response = CurlRequest::send(
                self::TRANSCRIPTION_RESULT_ENDPOINT . $transcriptionId,
                CurlRequest::GET,
                $requestHeaders
            );

            $response = json_decode($response, true);
            if ($response['status'] == 'error') {
                return $response;
            } elseif ($response['status'] == 'completed') {
                return $response;
            } else {
                sleep($sleepTime);

                $sleepTime = ceil($sleepTime * $multiplier);
                if ($sleepTime > $maxSleepTime) {
                    $sleepTime = $maxSleepTime;
                }
            }
        }
    }

    /**
     * Deletes a transcription task from the AssemblyAI server.
     *
     * @param string $transcriptionId The ID of the transcription task to delete.
     *
     * @return void
     */
    private function _deleteTranscript($transcriptionId)
    {
        $requestHeaders = [
            'Authorization: ' . $this->credentials[self::API_CREDENTIAL],
        ];

        $response = CurlRequest::send(
            self::DELETE_ENDPOINT . $transcriptionId,
            CurlRequest::DELETE,
            $requestHeaders
        );

        return;
    }

    /**
     * Sets the transcription and raw transcription on the given SpeechToTextResult object.
     *
     * @param SpeechToTextResult $speechToTextResult The SpeechToTextResult object to set the transcription on.
     * @param array $response The response array from the AssemblyAI server.
     *
     * @return void
     */
    private function _setTranscription(SpeechToTextResult $speechToTextResult, $response): void
    {
        $transcription = '';
        $speaker = -1;
        $wordCount = 0;
        $addPcrTimeStamp = !empty($this->configurations['pcr_time_stamp']) && !empty($this->configurations['word_time']);
        $sentenceWordLimit = 10;
        foreach ($response['words'] as $word) {
            if (
                !empty($this->configurations['speaker_labels'])
                && $speaker != $word['speaker']
            ) {
                if (!empty($this->configurations['word_time']) && empty($this->configurations['pcr_time_stamp'])) {
                    $milliseconds = $word['start'];
                    $seconds = floor($milliseconds / 1000);
        
                    $formattedTime = gmdate('H:i:s', $seconds);
        
                    $transcription .= PHP_EOL . PHP_EOL;
                    $transcription .= $formattedTime;
                }
        
                if ($addPcrTimeStamp) {
                    if ($wordCount != 0) {
                        $transcription .= ' #' . $word['start'];
                        $wordCount = 0;
                    }

                    if ($speaker != -1) {
                        $transcription .= ' $' . $word['start'];
                    }
                }

                $speaker = $word['speaker'];
                $transcription .= PHP_EOL;

                if ($addPcrTimeStamp) {
                    $transcription .= '$' . $word['start'] . ' ';
                }
                $transcription .= "SPEAKER_{$speaker}:";
            }

            if ($addPcrTimeStamp && $wordCount == 0) {
                $transcription .= ' #' . $word['start'];
            }

            $transcription .= ' ' . $word['text'];
            $wordCount +=1;

            if ($addPcrTimeStamp && $wordCount == $sentenceWordLimit) { 
                $wordCount = 0;
                $transcription .= ' #' . $word['end'];
            }
        }
        if ($addPcrTimeStamp) {
            $transcription .= ' #' . $word['end'];
            $transcription .= ' $' . $word['end'];
        }
        $speechToTextResult->setRawTranscription(trim($response['text']));
        $speechToTextResult->setTranscription(trim($transcription));
    }

    /**
     * Parses the language from the given locale/BCP-47 value.
     *
     * @param string $value The locale value to parse.
     *
     * @return string The language parsed from the locale value, or the default language code if the language cannot be parsed.
     */
    private function _parseLanguage(string $value): string
    {
        $locale = Locale::parseLocale($value);

        return $locale['language'] ?? self::DEFAULT_LANGUAGE_CODE;
    }
}
