<?php
namespace ZoomRx\Audio\SpeechToText;

use Exception;
use Google\Cloud\Speech\V1p1beta1\RecognitionAudio;
use Google\Cloud\Speech\V1p1beta1\RecognitionConfig;
use Google\Cloud\Speech\V1p1beta1\RecognitionConfig\AudioEncoding;
use Google\Cloud\Speech\V1p1beta1\RecognitionMetadata;
use Google\Cloud\Speech\V1p1beta1\SpeechClient;
use Google\Cloud\Speech\V1p1beta1\SpeechContext;
use Google\Cloud\Storage\StorageClient;
use Throwable;
use ZoomRx\Audio\Exceptions\FileNotFoundException;
use ZoomRx\Audio\Utility\AudioUtility;

/**
 * Class GoogleSpeechToText
 *
 * Implementation of SpeechToTextInterface that transcribes audio files using the Google Speech-to-Text API.
 */
class GoogleSpeechToText implements SpeechToTextInterface
{
    /**
     * The API credential key.
     */
    const API_CREDENTIAL = 'api_credential';

    /**
     * Google Cloud Storage bucket key
     */
    const GC_STORAGE_BUCKET = 'gc_storage_bucket';

    /**
     * The default language code to use if not specified in the configurations.
     */
    const DEFAULT_LANGUAGE_CODE = 'en-US';

    /**
     * A common array mapping configuration keys to API parameter keys.
     */
    const CONFIG_MAP = [
        'language' => 'language_code',
        'alternate_languages' => 'alternative_language_codes',
        'speaker_labels' => 'enable_speaker_diarization',
        'speakers_expected' => 'diarization_speaker_count',
        'punctuate' => 'enable_automatic_punctuation',
        'word_time' => 'enable_word_time_offsets',
        'word_confidence' => 'enable_word_confidence'
    ];
    
    /**
     * Array of valid configuration keys.
     */
    const VALID_ZRX_CONFIG = [
        'language',
        'alternate_languages',
        'speaker_labels',
        'speakers_expected',
        'punctuate',
        'word_time',
        'word_confidence',
        'word_boost',
        'max_alternatives',
        'profanity_filter',
        'model',
    ];

    /**
     * An array that stores the Google Speech-to-Text API credentials and the Google Cloud Storage bucket name.
     *
     * @var array
     */
    private $credentials = [];

    /**
     * An array that stores configuration data that will be passed to the Google Speech-to-Text API.
     *
     * @var array
     */
    private $configurations = [];

    /**
     * An instance of the RecognitionConfig.
     *
     * @var RecognitionConfig
     */
    private $configObj;

    /**
     * Sets the Google Speech-to-Text API credentials and the Google Cloud Storage bucket name.
     *
     * @param array $credentials
     */
    public function setCredentials(array $credentials): void
    {
        foreach ($credentials as $key => $value) {
            switch ($key) {
                case self::API_CREDENTIAL:
                    if (!file_exists($value)) {
                        throw new Exception('Invalid file path for API credential');
                    }
                    $this->credentials[self::API_CREDENTIAL] = $value;
                    break;
                
                default:
                    $this->credentials[$key] = $value;
            }
        }
    }

    /**
     * Sets the configuration data that will be passed to the Google Speech-to-Text API.
     *
     * @param array $configurations
     */
    public function setConfigurations(array $configurations): void
    {
        $this->configurations = $configurations;
    }

    /**
     * A private method that retrieves the configuration object that will be passed to the Google Speech-to-Text API.
     *
     * @return RecognitionConfig
     */
    private function _getConfigObject(): RecognitionConfig
    {
        $configObj = new RecognitionConfig();

        $configObj->setLanguageCode(self::DEFAULT_LANGUAGE_CODE);
        $configObj->setUseEnhanced(true);

        foreach ($this->configurations as $key => $values) {
            if (!in_array($key, self::VALID_ZRX_CONFIG)) {
                continue;
            }
            
            switch ($key) {
                case 'metadata':
                    $metadataObj = new RecognitionMetadata();
                    foreach ($values as $option => $value) {
                        $method = 'set' . self::_underscoreToCamelCase($option);
                        if (method_exists($metadataObj, $method) && !is_null($value)) {
                            $metadataObj->{$method}($value);
                        }
                    }
                    $configObj->setMetadata($metadataObj);
                    $this->configurations[$key] = $values;
                    break;

                case 'word_boost':
                    $speechContextObjects = [];
                    foreach ($values as $phrases) {
                        $speechContextObjects[] = (new SpeechContext())->setPhrases($phrases);
                    }
                    if (!empty($speechContextObjects)) {
                        $configObj->setSpeechContexts($speechContextObjects);
                    }
                    break;

                default:
                    $config = self::CONFIG_MAP[$key] ?? $key;
                    $method = 'set' . self::_underscoreToCamelCase($config);
                    if (method_exists($configObj, $method) && isset($values)) {
                        $configObj->{$method}($values);
                    }
                    break;
            }
        }

        return $configObj;
    }

    /**
     * A private method that validates that the audio file exists and that the Google Speech-to-Text API credentials and the Google Cloud Storage bucket name have been set.
     *
     * @param string $audioFile
     * @throws Exception If invalid request
     */
    private function _validateRequest(string $audioFile): void
    {
        if (!isset($this->credentials[self::API_CREDENTIAL])) {
            throw new Exception('API credentials are not set');
        }

        if (!isset($this->credentials[self::GC_STORAGE_BUCKET])) {
            throw new Exception('Google Cloud Storage bucket name is not set');
        }

        if (!file_exists($audioFile)) {
            throw new FileNotFoundException('Invalid file path provided for transcription');
        }
    }

    /**
     * Transcribes the given audio file using the Google Cloud Speech API. If the audio file duration is
     * greater than or equal to 1 minute, the long audio transcription method is used. Otherwise, the 
     * short audio transcription method is used.
     * 
     * @param string $audioFile The file path of the audio file to be transcribed.
     * @return SpeechToTextResult The transcribed text and metadata returned by the Google Cloud Speech API.
     * @throws Exception If invalid request or any error occured
     */
    public function transcribe(string $audioFile): SpeechToTextResult
    {
        $this->_validateRequest($audioFile);
        $this->configurations['service'] = SpeechToTextFactory::GOOGLE;

        $audioDetails = AudioUtility::getAudioDetails($audioFile);

        if ($audioDetails['duration'] >= 60) {
            $speechToTextResult = $this->_longAudioTranscribe($audioFile);
        } else {
            $speechToTextResult = $this->_shortAudioTranscribe($audioFile);
        }

        return $speechToTextResult;
    }

    /**
     * Synchronously transcribes a short audio(less than 60 seconds) file using the Google Speech-to-Text API.
     *
     * @param string $filePath The path to the audio file to transcribe.
     * @param array $additionalData Additional data to pass to the Google Speech-to-Text API.
     * @return SpeechToTextResult A SpeechToTextResult object representing the result of the transcription.
     * @throws Exception If an error occurs during the transcription process.
     */
    private function _shortAudioTranscribe(string $filePath): SpeechToTextResult
    {
        try {
            $options = ['channels' => 1];
            $flacFile = AudioUtility::getTempFileName($filePath, AudioUtility::EXTENSIONS['FLAC']);
            AudioUtility::convertAudio($filePath, $flacFile, AudioUtility::EXTENSIONS['FLAC'], $options);
            $audioDetails = AudioUtility::getAudioDetails($flacFile);

            $configObj = $this->_getConfigObject();
            $configObj->setEncoding(AudioEncoding::FLAC);
            $configObj->setSampleRateHertz($audioDetails['sample_rate']);

            $audio = file_get_contents($flacFile);
            $audio = (new RecognitionAudio())->setContent($audio);

            $speechClient = new SpeechClient([
                'credentials' => $this->credentials[self::API_CREDENTIAL],
            ]);
            $response = $speechClient->recognize($this->configObj, $audio);

            $speechToTextResult = new SpeechToTextResult();
            $speechToTextResult->setConfigurations($this->configurations);
            $this->_setTranscription($speechToTextResult, $response);
        } catch (Throwable $e) {
            throw $e;
        } finally {
            if (file_exists($flacFile)) {
                unlink($flacFile);
            }
            if (!empty($speechClient)) {
                $speechClient->close();
            }
        }

        return $speechToTextResult;
    }

    /**
     * Asynchronously transcribes a long audio file using the Google Speech-to-Text API.
     * 
     * @param string $filePath The path to the audio file to transcribe.
     * @param array $additionalData Additional data to pass to the Google Speech-to-Text API.
     * @return SpeechToTextResult A SpeechToTextResult object representing the result of the transcription.
     * @throws Exception If an error occurs during the transcription process.
     */
    private function _longAudioTranscribe(string $filePath): SpeechToTextResult
    {
        try {
            $options = ['channels' => 1];
            $flacFile = AudioUtility::getTempFileName($filePath, AudioUtility::EXTENSIONS['FLAC']);
            AudioUtility::convertAudio($filePath, $flacFile, AudioUtility::EXTENSIONS['FLAC'], $options);
            $audioDetails = AudioUtility::getAudioDetails($flacFile);
    
            $configObj = $this->_getConfigObject();
            $configObj->setEncoding(AudioEncoding::FLAC);
            $configObj->setSampleRateHertz($audioDetails['sample_rate']);

            $storageClient = new StorageClient([
                'keyFilePath' => $this->credentials[self::API_CREDENTIAL]
            ]);
            $bucket = $storageClient->bucket($this->credentials[self::GC_STORAGE_BUCKET]);
            $storageObject = $bucket->upload(fopen($flacFile, 'r'), [
                'name' => $audioDetails['basename']
            ]);
            $audio = (new RecognitionAudio())->setUri($storageObject->gcsUri());

            $speechClient = new SpeechClient([
                'credentials' => $this->credentials[self::API_CREDENTIAL],
            ]);
            $operation = $speechClient->longRunningRecognize($configObj, $audio);
            $operation->pollUntilComplete(['totalPollTimeoutMillis' => -1]);

            if (!$operation->isDone()) {
                throw new Exception("Unable to complete transcription.");
            }
            if (!$operation->operationSucceeded()) {
                throw new Exception("Failed to transcribe. " . json_encode($operation->getError()));
            }
            
            $response = $operation->getResult();

            $speechToTextResult = new SpeechToTextResult();
            $speechToTextResult->setConfigurations($this->configurations);
            $this->_setTranscription($speechToTextResult, $response);
        } catch (Throwable $e) {
            throw $e;
        } finally {
            if (file_exists($flacFile)) {
                unlink($flacFile);
            }
            if (!empty($storageObject)) {
                $storageObject->delete();
            }
            if (!empty($speechClient)) {
                $speechClient->close();
            }
        }
    
        return $speechToTextResult;
    }

    /**
     * Sets the properties of a SpeechToTextResult object using the response from the Google Cloud Speech API.
     * The method does not return any value, instead it updates the properties of $speechToTextResult directly.
     * 
     * @param SpeechToTextResult $speechToTextResult - an object that stores the transcription
     * @param mixed $response - the response from the Google Cloud Speech API
     * @return void
     */
    private function _setTranscription(SpeechToTextResult $speechToTextResult, $response): void
    {
        $rawTranscription = '';
        $transcription = '';
        $speaker = -1;

        foreach ($response->getResults() as $result) {
            $topAlternative = $result->getAlternatives()[0];

            if (empty($this->configurations['speaker_labels']) && empty($this->configurations['word_time'])) {
                $transcription .= ' ' . $topAlternative->getTranscript();
                $rawTranscription .= ' ' . $topAlternative->getTranscript();
            } else {
                $words = $topAlternative->getWords();
                foreach ($words as $word) {
                    if (
                        !empty($this->configurations['speaker_labels'])
                        && $speaker != $word->getSpeakerTag()
                    ) {
                        if ($word->getSpeakerTag() == 0) {
                            continue;
                        }
                        if (!empty($this->configurations['word_time'])) {
                            $duration = $word->getStartTime();
                            if (!empty($duration)) {
                                $milliseconds = round($duration->getNanos() / 1000000);
                                $milliseconds %= 1000;
                                $seconds = $duration->getSeconds();

                                $format = ($seconds >= 3600) ? 'H:i:s' : 'i:s';
                                $formattedTime = gmdate($format, $seconds) . ':' . sprintf('%03d', $milliseconds);

                                $transcription .= PHP_EOL;
                                $transcription .= '@' . $formattedTime;
                            }
                        }

                        $speaker = $word->getSpeakerTag();
                        $transcription .= PHP_EOL;
                        $transcription .= "SPEAKER_{$speaker}:";
                    }

                    $transcription .= ' ' . $word->getWord();
                    $rawTranscription .= ' ' . $word->getWord();
                }
            }
        }

        $speechToTextResult->setRawTranscription(trim($rawTranscription));
        $speechToTextResult->setTranscription(trim($transcription));
    }

    /**
     * Convert underscore separated string to camel case
     * @param string $underScoredWord Underscore separated word
     * @return string Upper camel cased word
     */
    private static function _underscoreToCamelCase($underScoredWord)
    {
        return str_replace('_', '', ucwords($underScoredWord, '_'));
    }
}
