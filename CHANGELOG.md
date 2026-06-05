# Changelog

## 1.0.0 (2023-04-24)
* Initial release with major features and functionality

## 1.1.0 (2023-05-10)
* Extended Whisper API support for all file formats

## 1.1.1 (2023-05-17)
* Bug fix (Pydub error: Invalid file format - m4a)
* Added timestamp support in Whipser API
* Added WER utility

## 1.1.2 (2023-07-05)
* Bug fix (Updated python utility commands to handle strings with quotes)

## 1.1.3 (2023-07-05)
* Added custom exceptions for file not found

## 1.1.4 (2023-07-05)
* Bug fix (Throw exception in AssemblyAI if queue ID not found)

## 1.1.5 (2025-07-08)
* Added support for AssemblyAI speech model

## 1.1.6 (2026-05-28)
* Added `prompt` and `domain` to AssemblyAI valid configurations
* Mapped the `speech_model` config to AssemblyAI's plural `speech_models` API parameter, wrapping a single string or array value via the new parser (supports Universal-3 Pro model selection)