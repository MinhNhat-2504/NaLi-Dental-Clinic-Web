#!/bin/sh
set -eu

# Mỗi lần khởi động đều nâng schema trước khi Gunicorn nhận traffic.
flask --app run.py db upgrade

exec gunicorn \
  --bind 0.0.0.0:5000 \
  --workers "${GUNICORN_WORKERS:-2}" \
  --threads "${GUNICORN_THREADS:-4}" \
  --access-logfile - \
  --error-logfile - \
  --capture-output \
  run:app
