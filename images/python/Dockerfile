FROM python:3.6

RUN apt-get update \
    && apt-get install -y build-essential python-dev libmysqlclient-dev python-redis python-mysqldb python-pip
RUN  pip install redis \
    && pip install mysqlclient
RUN whoami
ADD worker.py /
ADD config.template /
ADD run.sh /

CMD [ "./run.sh" ]