FROM python:3.6

RUN apt-get update && apt-get install -y mysql-client

ADD . /app
WORKDIR /app
RUN pip install -r requirements.txt

ADD ./worker.py ./config.ini /app/
CMD ["python", "worker.py"]