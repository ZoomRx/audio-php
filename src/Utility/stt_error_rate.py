from jiwer import wer
import argparse

def compute_wer(reference: str, hypothesis: str) -> float:
    """
    Computes word error rate between reference and hypothesis text

    Args:
        reference (str): Text to be referred
        hypothesis (str): Text to be matched
    
    Returns:
        float: WER percentage
    """

    if (len(reference) == 0 or len(hypothesis) == 0):
        return 100

    error_rate = wer(reference=reference, hypothesis=hypothesis)
    error_rate = error_rate * 100

    return error_rate

if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument("--reference", type=str, required=True)
    parser.add_argument("--hypothesis", type=str, required=True)
    args = parser.parse_args()

    try:
        error_rate = compute_wer(reference=args.reference, hypothesis=args.hypothesis)
        print(error_rate)
        exit(0)
    except Exception as e:
        print(e)
        exit(1)
