# Gateway CAN <-> MQTT (con emulatore) e "Set devices": build context = cartella vue/canbus
FROM python:3.12-slim

WORKDIR /app
ENV PYTHONUNBUFFERED=1
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt
COPY *.py ./

CMD ["python", "gateway.py"]
