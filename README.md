**SEO Analysis Platform**  

A web application for crawling, analyzing, and visualizing SEO data.  
It is designed to help businesses (e.g., domain registration & hosting services) track their search visibility.  
  
***✅ Features Implemented***  
SERP API Integration: crawl Google SERP (News & Search) → thu thập PAA, Headings, FAQ schema.  
Dashboard Visualizations:  
- PAA → Subheadings conversion rate (bar chart).  
- Subheadings → FAQ conversion rate (bar chart).  
- PAA by intent (bar chart).  
- Top URLs with most subheadings (bar chart).  
- FAQ Schema Analysis: coverage
Relevance Scoring:   
- Embedding PAA + product descriptions (Sentence Transformers).  
- Cosine similarity → Heatmap PAA ↔ Product relevance.  
- Sorting + per-PAA heatmaps.  
Word Cloud (PAA):  
- Built Python service (FastAPI + KeyBERT).  
- Extracted representative keyphrases.  
- Visualized with D3.js Word Cloud, tooltip shows importance score.

***📊 Tech Stack***  
Laravel + Blade: backend, dashboard.  
Python FastAPI + KeyBERT: keyphrase extraction.  
Sentence Transformers: embeddings (paraphrase-multilingual-MiniLM-L12-v2).  
D3.js: charts + Word Cloud visualization.  
  
***⚙️ Setup Instructions***  
**1. Laravel Backend**  
git clone <repo-url>  
cd project  
composer install  
cp .env.example .env  
php artisan key:generate  
php artisan migrate  
php artisan serve

Configure SERP API and Hugging face key in .env.  

**2. Python KeyBERT Service**  
cd keybert-service  
conda create -n keybert python=3.10  
conda activate keybert  
pip install -r requirements.txt  


requirements.txt:  
  
fastapi  
uvicorn  
keybert  
sentence-transformers  


Run service:  

uvicorn main:app --reload --port 8088  


Laravel calls:  
POST http://127.0.0.1:8088/keyphrases
