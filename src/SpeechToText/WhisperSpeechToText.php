<?php
namespace ZoomRx\Audio\SpeechToText;

use CURLFile;
use Exception;
use Locale;
use ZoomRx\Audio\Exceptions\FileNotFoundException;
use ZoomRx\Audio\Utility\AudioUtility;
use ZoomRx\Audio\Utility\CurlRequest;

/**
 * Class GoogleSpeechToText
 *
 * Implementation of SpeechToTextInterface that transcribes audio files using the OpenAI Whisper API.
 */
class WhisperSpeechToText implements SpeechToTextInterface
{
    /**
     * The API credential key.
     */
    const API_CREDENTIAL = 'api_credential';

    /**
     * Translate option for Whisper API
     */
    const TRANSLATE = 'translate';

    /**
     * The endpoint for submitting a transcription request.
     */
    const TRANSCRIBE_ENDPOINT = 'https://api.openai.com/v1/audio/transcriptions';

    /**
     * The endpoint for submitting a translation request.
     */
    const TRANSLATE_ENDPOINT = 'https://api.openai.com/v1/audio/translations';

    /**
     * The default Whisper model to use for transcription/translation.
     */
    const DEFAULT_MODEL = 'whisper-1';

    /**
     * The default response format of the Whisper API
     */
    const DEFAULT_RESPONSE_FORMAT = 'verbose_json';

    /**
     * The default language code to use if not specified in the configurations.
     */
    const DEFAULT_LANGUAGE_CODE = 'en';

    /**
     * The maximum file size allowed by the API in KiloBytes
     */
    const MAX_FILE_SIZE = 23 * 1024;

    /**
     * A common array mapping configuration keys to API parameter keys.
     */
    const CONFIG_MAP = [
        'word_boost' => 'prompt',
    ];

    /**
     * Array of valid configuration keys.
     */
    const VALID_ZRX_CONFIG = [
        'model',
        'response_format',
        'language',
        'word_boost',
    ];

    /**
     * List of supported file formats
     */
    const SUPPORTED_FILE_FORMATS = [
        AudioUtility::EXTENSIONS['MP3'],
        AudioUtility::EXTENSIONS['MP4'],
        AudioUtility::EXTENSIONS['MPEG'],
        AudioUtility::EXTENSIONS['MPGA'],
        AudioUtility::EXTENSIONS['M4A'],
        AudioUtility::EXTENSIONS['WAV'],
        AudioUtility::EXTENSIONS['WEBM']
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
     * Temporary files that were generated during the transcription process
     * 
     * @var string[]
     */
    private $tempFiles = [];

    /**
     * Sets the credentials.
     * 
     * @param array $credentials: Array containing the credentials
     */
    public function setCredentials(array $credentials): void
    {
        $this->credentials = $credentials;
    }

    /**
     * Sets the configurations.
     * 
     * @param array $configurations: Array containing configuration options
     */
    public function setConfigurations(array $configurations): void
    {
        $this->configurations = $configurations;
    }

    /**
     * Validates the request.
     * 
     * @param string $audioFile: Path to the audio file to be transcribed
     * @throws Exception if the request is invalid
     */
    private function _validateRequest(string $audioFile): void
    {
        if (!isset($this->credentials[self::API_CREDENTIAL])) {
            throw new Exception('API Credential is required for this service');
        }

        if (!file_exists($audioFile)) {
            throw new FileNotFoundException('Invalid file path provided for transcription');
        }
    }

    /**
     * Transcribes the given audio file using the provided configurations and credentials
     * 
     * @param string $audioFile: Path to the audio file to be transcribed
     * @return SpeechToTextResult: An object that includes the response data
     * @throws Exception if API Credential is not set or the file path is invalid
     */
    public function transcribe(string $audioFile): SpeechToTextResult
    {
        $this->_validateRequest($audioFile);
        $this->configurations['service'] = SpeechToTextFactory::WHISPER;

        if (isset($this->configurations[self::TRANSLATE])) {
            $whiperEndpoint = self::TRANSLATE_ENDPOINT;
        } else {
            $whiperEndpoint = self::TRANSCRIBE_ENDPOINT;
        }

        $requestHeaders = [
            'Authorization: Bearer ' . $this->credentials[self::API_CREDENTIAL],
            'Content-Type: multipart/form-data'
        ];

        $requestData = $this->_getRequestData();

        $audioChunks = [];
        $responses = [];
        $rawTranscription = '';
        $transcription = '';

        try {
            $audioDetails = AudioUtility::getAudioDetails($audioFile);

            if (!in_array($audioDetails['extension'], self::SUPPORTED_FILE_FORMATS) || $audioDetails['filesize'] > self::MAX_FILE_SIZE) {
                $outfile = AudioUtility::getTempFileName($audioFile, AudioUtility::EXTENSIONS['MP3']);
                AudioUtility::convertAudio($audioFile, $outfile, AudioUtility::EXTENSIONS['MP3']);
                $this->tempFiles[] = $outfile;
                $audioFile = $outfile;
                $audioDetails = AudioUtility::getAudioDetails($audioFile);
            }

            if ($audioDetails['filesize'] > self::MAX_FILE_SIZE) {
                $audioChunks = AudioUtility::splitAudioBySize($audioFile, self::MAX_FILE_SIZE);
                $this->tempFiles = array_merge($this->tempFiles, $audioChunks);
            } else {
                $audioChunks[] = $audioFile;
            }

            $duration = 0;
            foreach ($audioChunks as $audioChunk) {
                $requestData['file'] = new CURLFile($audioChunk);
                $response = CurlRequest::send(
                    $whiperEndpoint,
                    CurlRequest::POST,
                    $requestHeaders,
                    $requestData
                );

                $response = json_decode($response, true);
                if (!empty($response['error'])) {
                    throw new Exception($response['error']['message']);
                }

                $responses[] = $response;
                $rawTranscription .= ' ' . $response['text'];
                $transcription .= ' ' . $this->_getTranscription($response, $duration);

                if (count($audioChunks) > 1) {
                    $duration = floor($duration + AudioUtility::getAudioDetails($audioChunk)['duration']);
                }
            }
        } catch (Exception $e) {
            throw $e;
        } finally {
            $this->_discardTempFiles();
        }

        $speechToTextResult = new SpeechToTextResult();
        $speechToTextResult->setConfigurations($this->configurations);
        $speechToTextResult->setResponse($responses);
        $speechToTextResult->setRawTranscription(trim($rawTranscription));
        $speechToTextResult->setTranscription(trim($transcription));

        return $speechToTextResult;
    }

    /**
     * Returns an array of request data to be sent in the API request
     * 
     * @return array: Array containing the infomration to be sent in the API request
     */
    private function _getRequestData(): array
    {
        $requestData = [];

        $requestData['model'] = self::DEFAULT_MODEL;
        if (!isset($this->configurations[self::TRANSLATE])) {
            $requestData['language'] = self::DEFAULT_LANGUAGE_CODE;
        }

        foreach ($this->configurations as $key => $value) {
            if (!in_array($key, self::VALID_ZRX_CONFIG)) {
                continue;
            }

            switch ($key) {
                case 'word_boost':
                    $value = implode(", ", $value);
                    if (!empty($value)) {
                        $config = self::CONFIG_MAP[$key] ?? $key;
                        $requestData[$config] = $value;
                    }
                    break;
                default:
                    $config = self::CONFIG_MAP[$key] ?? $key;
                    $method = '_parse' . str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));
        
                    if (method_exists($this, $method)) {
                        $requestData[$config] = $this->{$method}($value);
                    } else {
                        $requestData[$config] = $value;
                    }
                    break;
            }
        }

        $requestData['response_format'] = self::DEFAULT_RESPONSE_FORMAT;

        return $requestData;
    }

    /**
     * Removes all temporary files
     */
    private function _discardTempFiles()
    {
        foreach ($this->tempFiles as $tempFile) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Get the formatted transcription 
     *
     * @param array $response The response array from the Whisper API.
     * @param int $duration The previous audio duration
     *
     * @return string $transcription
     */
    private function _getTranscription(array $response, int $duration = 0): string
    {
        $transcription = '';

        foreach ($response['segments'] as $segment) {
            if (!empty($this->configurations['word_time'])) {
                $seconds = floor($segment['start']) + $duration;

                $format = ($seconds >= 3600) ? 'H:i:s' : 'i:s';
                $formattedTime = gmdate($format, $seconds) . ':' . sprintf('%03d', 0);

                $transcription .= PHP_EOL;
                $transcription .= '@' . $formattedTime;
                $transcription .= PHP_EOL;
            }

            $transcription .= $segment['text'];
        }

        return $transcription;
    }
    /**
     * Parses the given language value to get the language code
     * 
     * @param string $value: Language value to be parsed
     * @return string: Language code
     */
    private function _parseLanguage(string $value): string
    {
        $locale = Locale::parseLocale($value);

        return $locale['language'] ?? self::DEFAULT_LANGUAGE_CODE;
    }
}
