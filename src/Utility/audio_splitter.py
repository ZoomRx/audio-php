import argparse
from math import ceil
import os
from pydub import AudioSegment
import time

def split_file_by_size(infile: str, directory: str, chunk_size: int) -> list:
    """
    Split a multimedia file into smaller chunks of a specified size.

    Args:
        infile (str): The path of the multimedia file to split.
        directory (str): The directory path for the output files.
        chunk_size (int): The maximum size of each chunk, in KB.
    
    Returns:
        List[str]: A list of paths to the split chunks.
    """

    # Load the audio file
    audio = AudioSegment.from_file(infile)

    # Calculate the number of chunks needed
    file_size_kb = os.path.getsize(infile) / 1024
    duration = audio.duration_seconds * 1000
    ms_per_chunk = int((ceil(duration / file_size_kb)) * chunk_size)
    num_chunks = int(ceil(duration / ms_per_chunk))

    file_name, extension = os.path.splitext(os.path.basename(infile))
    extension = extension.lstrip('.')
    timestamp = int(time.time())

    chunks = []
    for i in range(num_chunks):
        start = i * ms_per_chunk
        end = (i + 1) * ms_per_chunk
        chunk = audio[start:end]
        chunk_path = f"{directory}{timestamp}_{file_name}_chunk_{i+1}.{extension}"
        chunk.export(chunk_path, format=extension)
        chunks.append(chunk_path)
    
    return chunks

if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument("--infile", type=str, required=True)
    parser.add_argument("--tmp_dir", type=str, required=True)
    parser.add_argument("--chunk_size", type=str, required=True)
    args = parser.parse_args()

    try:
        chunks = split_file_by_size(args.infile, args.tmp_dir, args.chunk_size)
        for chunk in chunks:
            print(chunk)
        exit(0)
    except Exception as e:
        print(e)
        exit(1)
