import argparse
import os
from pydub import AudioSegment

def convert_audio_file(infile, outfile, outfile_format, channels=None):
    """
    Convert an audio file from one format to another

    Args:
        infile (str): Path to the input audio file.
        outfile (str): Path to save the output converted audio file.
        outfile_format (str): Format of the output file
        channels (int, optional): Number of channels for the output audio file. Defaults to None.

    Returns:
        None
    """
    audio = AudioSegment.from_file(infile)

    if (channels is not None):
        audio = audio.set_channels(channels)

    audio.export(outfile, format=outfile_format)

if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument("--infile", type=str, required=True)
    parser.add_argument("--outfile", type=str, required=True)
    parser.add_argument("--outfile_format", type=str, required=True)
    parser.add_argument("--channels", type=int, required=False, default=None)
    args = parser.parse_args()

    try:
        convert_audio_file(args.infile, args.outfile, args.outfile_format, args.channels)
        exit(0)
    except Exception as e:
        print(e)
        exit(1)
