#!/usr/bin/env bash
# Envia uma mensagem para um tópico usando kcat (modo producer).
# Uso: ./scripts/produce.sh TOPIC "mensagem"
#   ou: echo "mensagem" | ./scripts/produce.sh TOPIC
#
# Requer: Kafka no ar (docker compose up -d) e rede kafka-network.

set -e
TOPIC="${1:-teste}"
BROKER="kafka:9092"
NETWORK="kafka-network"
IMAGE="edenhill/kcat:1.7.1"

if [ -n "$2" ]; then
  echo "$2" | docker run --rm -i --network "$NETWORK" "$IMAGE" -P -b "$BROKER" -t "$TOPIC"
else
  docker run --rm -i --network "$NETWORK" "$IMAGE" -P -b "$BROKER" -t "$TOPIC"
fi
