# ZoomRx Audio
ZoomRx Audio is a PHP wrapper package for audio manipulation and Speech-To-Text operations.

SpeechToText provides a simple and convenient way to utilize the Speech-to-Text services offered by Google, OpenAI, and AssemblyAI. This package serves as a wrapper for the respective APIs, allowing developers to easily transcribe speech audio files into text in their PHP applications.

## Features

- Supports three major Speech-to-Text service providers: Google, OpenAI, and AssemblyAI.
- Offers customizable settings for transcribing audio files, such as language, 
- Provides error handling and response parsing for easy integration into PHP applications.

## Requirements

- PHP 7.4 or above
- cURL extension enabled
- API key or credentials for the respective Speech-to-Text service providers (Google, OpenAI-Whisper, and AssemblyAI)
- Python for audio manipulation
- Python pydub module
- Python jiwer module
- ffmpeg CLI

## Usage

To use SpeechToText-PHP in your PHP application, follow these steps:

1. Instantiate the SpeechToText class with the desired provider (Google, OpenAI, or AssemblyAI), and pass in the required authentication credentials:
    ```php
    use ZoomRx\Audio\SpeechToText\SpeechToTextFactory;

    $speechToTextObj = SpeechToTextFactory::create(SpeechToTextFactory::ASSEMBLYAI);
    $speechToTextObj->setCredentials([
        'api_credential' => 'YOUR-API-KEY'
    ]);
    ```
2. Set configurations such as language, punctuate, etc,:
    ```php
    $speechToTextObj->setConfigurations([
        'language' => 'en-US',
        'speaker_labels' => true,
        'speakers_expected' => 2,
        'word_time' => true,
        'punctuate' => true
    ]);
    ```
3. Invoke transcribe function along with the file path and get results:
    ```php
    $speechToTextResult = $speechToTextObj->transcribe($infile);
    $transcription = $speechToTextResult->getTranscription();
    ```
