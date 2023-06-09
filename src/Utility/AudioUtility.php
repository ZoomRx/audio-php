<?php
namespace ZoomRx\Audio\Utility;

use Exception;

class AudioUtility
{
    const EXTENSIONS = [
        'FLAC' => 'flac',
        'MP3' => 'mp3',
        'MP4' => 'mp4',
        'MPEG' => 'mpeg',
        'MPGA' => 'mpga',
        'M4A' => 'm4a',
        'WAV' => 'wav',
        'WEBM' => 'webm',
    ];

    /**
     * Returns an array containing the details of an audio file given its path
     *
     * @param string $infile the path to the audio file
     * @return array an array containing details of the audio file
     */
    public static function getAudioDetails(string $infile): array
    {
        if (!file_exists($infile) || !is_file($infile)) {
            throw new Exception('Invalid path provided to get the audio details');
        }
        
        list($resultCode, $output) = self::executePythonScript('audio_details.py', [
            'infile' => $infile,
        ]);

        if ($resultCode != 0) {
            self::throwException("Unable to get audio details", $resultCode, $output);
        }
        
        $audioDetails = [];
        foreach ($output as $details) {
            $extractedDetail = [];
            preg_match('/([a-z_]+):(.+)/', $details, $extractedDetail);
            $audioDetails[$extractedDetail[1]] = $extractedDetail[2];
        }
        
        return $audioDetails;
    }

    /**
     * Splits an audio file into smaller chunks of a specified size in kilobytes.
     * 
     * @param string $audioFile - The path to the audio file that needs to be split into smaller chunks.
     * @param int $chunkSize - The size of each chunk in kilobytes.
     * @return array - An array containing the paths to the newly created output files.
     */
    public static function splitAudioBySize(string $infile, int $chunkSize): array
    {
        if (!file_exists($infile) || !is_file($infile)) {
            throw new Exception('Invalid path provided to get the audio details');
        }

        if (pathinfo($infile, PATHINFO_EXTENSION) != AudioUtility::EXTENSIONS['MP3']) {
            $tempFile = self::getTempFileName($infile, AudioUtility::EXTENSIONS['MP3']);
            self::convertAudio($infile, $tempFile, AudioUtility::EXTENSIONS['MP3']);
            $infile = $tempFile;
        }

        list($resultCode, $output) = self::executePythonScript('audio_splitter.py', [
            'infile' => $infile,
            'tmp_dir' => TMP,
            'chunk_size' => $chunkSize,
        ]);
        
        if (!empty($tempFile)) {
            unlink($tempFile);
        }
        if ($resultCode != 0) {
            self::throwException("Unable to split audio", $resultCode, $output);
        }
        
        return $output;
    }

    /**
     * Convert audio file using ffmpeg
     *
     * @param string $infile Input file path
     * @param string $outfile Output file path
     * @param string $outfileFormat Format of the output file
     * @param array $options audio properties to apply during conversion on outfile.
     * This has to be compatible with audio_convertor.py
     * Example: [
     *      'channels' => 1
     * ]
     * @return void
     * @throws Exception
     */
    public static function convertAudio($infile, $outfile, $outfileFormat, $options = [])
    {
        if (!file_exists($infile) || !is_file($infile)) {
            throw new Exception('Invalid path provided to get the audio details');
        }

        $args = array_merge(
            [
                'infile' => $infile,
                'outfile' => $outfile,
                'outfile_format' => $outfileFormat,
            ],
            $options
        );
        list($resultCode, $output) = self::executePythonScript('audio_convertor.py', $args);
        
        if ($resultCode != 0) {
            self::throwException("Unable to convert audio", $resultCode, $output);
        }
    }

    /**
     * Generates a unique temporary filename based on the given file path and extension (optional).
     *
     * @param string $filePath The file path to use as the base of the temporary filename.
     * @param string|null $extension The extension to use for the temporary filename. Defaults to null if not provided.
     * @return string A unique temporary filename based on the given file path and extension.
     */
    public static function getTempFileName($filePath, $extension = null)
    {
        $timestamp = time();
        $fileName = pathinfo($filePath, PATHINFO_FILENAME);

        if (empty($extension)) {
            $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        }
        $extension = trim($extension, " \t\n\r\0\x0B.");

        $tmpFileName = TMP . $timestamp . '_' . $fileName . '.' . $extension;

        return $tmpFileName;
    }

    /**
     * Throw exception with formatted error message
     * 
     * @param string $message Error message
     * @param int $resultCode Result code of the script
     * @param array $output Output of the script
     * @throws Exception
     */
    private static function throwException($message, $resultCode, $output = [])
    {
        if (!empty($output)) {
            $output = implode("\n", $output);
        } else {
            $output = '';
        }

        $formattedMessage = trim("[{$resultCode}] {$message}. {$output}");

        throw new Exception($formattedMessage);
    }

    /**
     * Computes word error rate between reference and hypothesis text
     * 
     * @param string $reference Text to be referred
     * @param string $hypothesis Text to be matched
     * 
     * @return float
     * @throws Exception
     */
    public static function computeWER($reference, $hypothesis)
    {
        list($resultCode, $output) = self::executePythonScript('stt_error_rate.py', [
            'reference' => $reference,
            'hypothesis' => $hypothesis,
        ]);

        if ($resultCode != 0) {
            self::throwException("Unable to compute word error rate", $resultCode, $output);
        }

        return round(floatval($output[0] ?? 100), 2);
    }

    /**
     * Runs python script
     * 
     * @param string $script Name of the script to run
     * @param array $args Key-Value pairs of arguments to pass to the script
     * 
     * @return array [resultCode, output]
     */
    private static function executePythonScript($script, $args = [])
    {
        $cmdArgs = [];
        foreach ($args as $key => $value) {
            $value = str_replace('"', '', $value);
            $cmdArgs[] = "--{$key}=\"{$value}\"";
        }

        if (!empty($cmdArgs)) {
            $cmdArgs = implode(" ", $cmdArgs);
        } else {
            $cmdArgs = '';
        }

        $script = realpath(__DIR__) . '/' . trim($script);
        $command = escapeshellcmd(trim("python {$script} " . $cmdArgs));
        exec($command, $output, $resultCode);

        return [$resultCode, $output];
    }
}
