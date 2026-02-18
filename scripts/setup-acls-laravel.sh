#!/usr/bin/env bash
# Cria o tópico laravel-events e ACLs para o usuário laravel (Fase 1).
# Rodar após: docker compose up -d
# Uso: ./scripts/setup-acls-laravel.sh

set -e
KAFKA_CONTAINER="${KAFKA_CONTAINER:-kafka-learning-broker}"
KAFKA_OPTS="-Djava.security.auth.login.config=/opt/kafka/config/kafka_server_jaas.conf"
BIN="/opt/kafka/bin"
CMD_CONFIG="/opt/kafka/config/admin-client.conf"

echo "Criando tópico laravel-events..."
docker exec "$KAFKA_CONTAINER" env KAFKA_OPTS="$KAFKA_OPTS" \
  "$BIN/kafka-topics.sh" --create --topic laravel-events \
  --bootstrap-server localhost:9092 --command-config "$CMD_CONFIG" \
  --partitions 1 --replication-factor 1 2>/dev/null || echo "(tópico já existe)"

echo "Adicionando ACLs para User:laravel..."
docker exec "$KAFKA_CONTAINER" env KAFKA_OPTS="$KAFKA_OPTS" \
  "$BIN/kafka-acls.sh" --bootstrap-server localhost:9092 --command-config "$CMD_CONFIG" \
  --add --allow-principal User:laravel --operation Read --operation Write --operation Describe --topic laravel-events

# READ no consumer group: necessário para join no grupo e consumir.
# StandardAuthorizer trata --group '*' como grupo literal "*"; precisamos do grupo real.
docker exec "$KAFKA_CONTAINER" env KAFKA_OPTS="$KAFKA_OPTS" \
  "$BIN/kafka-acls.sh" --bootstrap-server localhost:9092 --command-config "$CMD_CONFIG" \
  --add --allow-principal User:laravel --operation Read --group 'laravel-consumer-group'

# Qualquer outro grupo usado (ex.: --group=test-xyz) precisa de ACL ou use este grupo.
docker exec "$KAFKA_CONTAINER" env KAFKA_OPTS="$KAFKA_OPTS" \
  "$BIN/kafka-acls.sh" --bootstrap-server localhost:9092 --command-config "$CMD_CONFIG" \
  --add --allow-principal User:laravel --operation Read --group '*' 2>/dev/null || true

echo "ACLs configuradas para laravel."
