FROM python:3.11-slim

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    APP_ENV=production \
    AUTO_CREATE_SCHEMA=0

WORKDIR /app
COPY flask_app/requirements.txt ./requirements.txt
RUN pip install --no-cache-dir -r requirements.txt

COPY flask_app/ ./
COPY deploy/flask-entrypoint.sh /usr/local/bin/flask-entrypoint
RUN chmod +x /usr/local/bin/flask-entrypoint \
    && useradd --create-home --uid 10001 nali \
    && chown -R nali:nali /app

USER nali
EXPOSE 5000
ENTRYPOINT ["/usr/local/bin/flask-entrypoint"]
