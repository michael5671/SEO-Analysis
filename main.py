from fastapi import FastAPI
from pydantic import BaseModel
from keybert import KeyBERT
# from underthesea import word_tokenize


# Load model 1 lần khi khởi động service
kw_model = KeyBERT("sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2")

app = FastAPI(title="Keyphrase API")

class Input(BaseModel):
    text: str
    top_n: int = 20

STOPWORDS_VI = [
    "là", "gì", "nào", "như", "thế", "cái", "có", "không", "và", "hay",
    "được", "của", "cho", "ra", "khi", "nếu", "cần", "với", "ở", "trong",
    "bao", "nhiêu", "thế_nào", "sao", "tại", "sao"
]

@app.post("/keyphrases")
def extract(inp: Input):
    if not inp.text.strip():
        return []
    # tokenized_word = word_tokenize(inp.text, format="text")
    # Extract keyphrases
    kws = kw_model.extract_keywords(
        inp.text,
        keyphrase_ngram_range=(2, 3),  # unigram → trigram
        stop_words=STOPWORDS_VI,   
        use_maxsum=False, 
        nr_candidates=max(50, inp.top_n * 2),           # có thể dùng 'english' hoặc list tiếng Việt
        top_n=inp.top_n
    )

    # Map thành {text, value}
    out = []
    for kw, score in kws:
        size = int(12 + (score * 52))  # scale 12 → 64 px
        out.append({"text": kw, "value": size})

    return out
