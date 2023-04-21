import argparse
import os
import mimetypes
from pydub import AudioSegment

def get_audio_details(filepath):
    # Extract file details using pydub
    audio_file = AudioSegment.from_file(filepath)
    duration = audio_file.duration_seconds
    channels = audio_file.channels
    sample_rate = audio_file.frame_rate
    bit_rate = audio_file.frame_rate * audio_file.frame_width * 8

    # Extract file details using os and mimetypes
    dirpath = os.path.dirname(filepath)
    basename = os.path.basename(filepath)
    filename, extension = os.path.splitext(basename)
    mime_type, _ = mimetypes.guess_type(filepath)
    file_size = os.path.getsize(filepath) / 1024

    # Return the extracted file details as a dictionary
    file_details = {
        'filepath': filepath,
        'dirpath': dirpath,
        'basename': basename,
        'filename': filename,
        'extension': extension.lstrip("."),
        'mime_type': mime_type,
        'filesize': file_size,
        'duration': duration,
        'bit_rate': bit_rate,
        'sample_rate': sample_rate,
        'channels': channels,
    }
    return file_details


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument("--infile", type=str, required=True)
    args = parser.parse_args()

    try:
        audio_details = get_audio_details(args.infile)
        for key, value in audio_details.items():
            print(f"{key}:{value}")
        exit(0)
    except Exception as e:
        print(e)
    
    exit(1)


