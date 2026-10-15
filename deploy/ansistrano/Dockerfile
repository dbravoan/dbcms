FROM python:3.12-slim
RUN apt-get update && apt-get install -y --no-install-recommends openssh-client rsync git \
    && rm -rf /var/lib/apt/lists/* \
    && pip install --no-cache-dir ansible-core==2.19.3
ENV ANSIBLE_ROLES_PATH=/opt/ansible/roles \
    ANSIBLE_COLLECTIONS_PATH=/opt/ansible/collections \
    ANSIBLE_HOST_KEY_CHECKING=True \
    ANSIBLE_LOCAL_TEMP=/tmp/ansible
COPY requirements.yml /tmp/requirements.yml
RUN ansible-galaxy role install -r /tmp/requirements.yml -p /opt/ansible/roles \
    && ansible-galaxy collection install -r /tmp/requirements.yml -p /opt/ansible/collections
WORKDIR /deployment
ENTRYPOINT ["ansible-playbook"]
