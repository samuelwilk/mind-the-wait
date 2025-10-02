FROM python:3.12-slim

# system deps
RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates curl && \
    rm -rf /var/lib/apt/lists/*

# app dir
WORKDIR /app

# python deps (keep this layer stable)
COPY pyparser/requirements.txt /app/requirements.txt
RUN pip install --no-cache-dir -r /app/requirements.txt

# copy app code
COPY pyparser/ /app/

# runtime env
ENV PYTHONUNBUFFERED=1

# simple healthcheck script
HEALTHCHECK --interval=30s --timeout=5s --retries=5 CMD python /app/healthcheck.py
CMD ["python", "/app/parser.py"]
