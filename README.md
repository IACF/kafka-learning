# Kafka Learning

Projeto de aprendizado de Kafka com Laravel 11 e NestJS. O plano completo está em [PLANO.md](PLANO.md).

## Fase 0: Kafka (implementada)

Ambiente Kafka na raiz para uso pelas fases seguintes.

### Subir o ambiente

Na raiz do projeto:

```bash
docker compose up -d
```

- **Kafka:** porta `9092`
- **Kafka UI:** http://localhost:8080 (inspecionar tópicos e mensagens)

### Comandos úteis

Criar um tópico de teste:

```bash
docker exec -it kafka-learning-broker kafka-topics.sh --create --topic teste --bootstrap-server localhost:9092 --partitions 1 --replication-factor 1
```

Listar tópicos:

```bash
docker exec -it kafka-learning-broker kafka-topics.sh --list --bootstrap-server localhost:9092
```

Produzir mensagem (console):

```bash
docker exec -it kafka-learning-broker kafka-console-producer.sh --topic teste --bootstrap-server localhost:9092
```

Consumir mensagens (console):

```bash
docker exec -it kafka-learning-broker kafka-console-consumer.sh --topic teste --from-beginning --bootstrap-server localhost:9092
```

### Parar

```bash
docker compose down
```

A rede `kafka-network` fica disponível para os projetos Laravel e NestJS (Fases 1 e 2) se conectarem ao broker pelo hostname `kafka:9092`.
