#!/usr/bin/env bash
# Consome mensagens de um tópico usando kcat (funciona na Fase 0).
# Uso: ./scripts/consume.sh [TOPIC] [OPÇÃO]
#   TOPIC: nome do tópico (padrão: teste)
#   -e: encerra após ler as mensagens já existentes (padrão)
#   -f: fica ouvindo novas mensagens (não encerra; Ctrl+C para sair)
#
# Requer: Kafka no ar (docker compose up -d) e rede kafka-network.

set -e
TOPIC="${1:-teste}"
MODE="${2:--e}"

BROKER="kafka:9092"
NETWORK="kafka-network"
IMAGE="edenhill/kcat:1.7.1"

case "$MODE" in
  -e) EXTRA="-o beginning -e -c 100" ;;   # do início, sai ao chegar no fim (até 100 msgs)
  -f) EXTRA="-o beginning" ;;               # do início e fica ouvindo
  *)  EXTRA="-o beginning -e -c 100"; TOPIC="$1";;
esac

echo "Consumindo tópico: $TOPIC (broker: $BROKER)"
echo "---"

if [ "$MODE" = "-f" ]; then
  # Modo -f: TTY (se disponível) para flush das mensagens; filtra "% Reached end of topic" (só mostra o conteúdo)
  DOCKER_TTY=""
  [ -t 1 ] && DOCKER_TTY="-t"
  docker run --rm $DOCKER_TTY --network "$NETWORK" "$IMAGE" -C -b "$BROKER" -t "$TOPIC" $EXTRA 2>&1 | grep -v '^%'
else
  docker run --rm --network "$NETWORK" "$IMAGE" -C -b "$BROKER" -t "$TOPIC" $EXTRA
fi
