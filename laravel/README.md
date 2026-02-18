# Fase 1: Laravel 11 como producer e consumer (Kafka + SASL/ACL)

Laravel 11 produz e consome mensagens no Kafka usando **SASL** (autenticação) e **ACLs** (autorização). O broker fica na raiz do repositório; esta aplicação conecta-se a ele pela rede Docker.

## Pré-requisitos

- Docker e Docker Compose
- Kafka na raiz já no ar com SASL (ver [../README.md](../README.md))
- ACLs e tópico criados (script na raiz)

## Credenciais e ACLs

- **Principal Laravel:** `User:laravel` (usuário SASL)
- **Senha:** `laravel-secret` (definida em `kafka-config/kafka_server_jaas.conf` na raiz)
- **Tópico:** `laravel-events`
- **Permissões:** READ, WRITE, DESCRIBE no tópico; READ em consumer groups; DESCRIBE no cluster (conforme script)

## 1. Subir o Kafka (raiz) e configurar ACLs

Na **raiz** do repositório:

```bash
docker compose up -d
# Aguardar o Kafka ficar healthy (~45s), depois:
chmod +x scripts/setup-acls-laravel.sh
./scripts/setup-acls-laravel.sh
```

## 2. Subir o Laravel

Nesta pasta (`laravel/`):

```bash
# Primeira vez: build da imagem (instala dependências + ext rdkafka)
docker compose build

# Se .env não tiver APP_KEY, gerar uma vez:
docker compose run --rm app php artisan key:generate

# Subir o app (conecta ao Kafka na rede kafka-network)
docker compose up -d
```

A API fica em **http://localhost:8000**.

## 3. Producer (enviar mensagem)

- **GET** (teste rápido):  
  `curl http://localhost:8000/kafka/produce`
- **POST** (com corpo):  
  `curl -X POST http://localhost:8000/kafka/produce -H "Content-Type: application/json" -d '{"message":"Minha mensagem"}'`

A resposta inclui o `topic` e o `body` enviado ao Kafka.

## 4. Consumer (receber mensagens)

Rodar em **outro terminal** (processo contínuo). O consumer usa **consumer group** (subscribe) por padrão; o Kafka na raiz está configurado com `KAFKA_OFFSETS_TOPIC_REPLICATION_FACTOR=1` para o coordinator funcionar em single-broker KRaft.

```bash
docker compose run --rm app php artisan kafka:consume-laravel-events
```

Alternativa com **partição fixa** (sem consumer group): `--assign-partition=0`.

Outras opções: `--topic=laravel-events`, `--group=meu-grupo`. Ao disparar o producer (passo 3), as mensagens devem aparecer no consumer.

## Variáveis de ambiente (Kafka)

Usadas pelo `config/kafka.php` e pelo container:

| Variável | Descrição | Exemplo |
|----------|-----------|---------|
| `KAFKA_BROKERS` | Endereço do broker | `kafka:9092` |
| `KAFKA_SASL_USERNAME` | Usuário SASL | `laravel` |
| `KAFKA_SASL_PASSWORD` | Senha SASL | `laravel-secret` |
| `KAFKA_CONSUMER_GROUP_ID` | Consumer group | `laravel-consumer-group` |
| `KAFKA_TOPIC` | Tópico padrão | `laravel-events` |
| `KAFKA_SECURITY_PROTOCOL` | Protocolo | `SASL_PLAINTEXT` |
| `KAFKA_SASL_MECHANISMS` | Mecanismo SASL | `PLAIN` |

O `docker-compose.yml` já define essas variáveis; para rodar fora do Docker, copie `.env.example` para `.env` e ajuste (por exemplo `KAFKA_BROKERS=localhost:9092` se o Kafka estiver no host).

## Estrutura (Fase 1)

- **Producer:** `App\Http\Controllers\KafkaProducerController` — rota `GET/POST /kafka/produce`
- **Consumer:** `App\Console\Commands\ConsumeLaravelEventsCommand` — comando `php artisan kafka:consume-laravel-events`
- **Config:** `config/kafka.php` (brokers, SASL, consumer group, tópico)

## Troubleshooting: mensagens não aparecem no consumer

1. **"Group authorization failed" no log**  
   O usuário `laravel` precisa de permissão **READ no consumer group**. O script `scripts/setup-acls-laravel.sh` na raiz já concede READ em `laravel-consumer-group` e em `*`. Rode de novo após subir/recriar o Kafka:
   ```bash
   cd .. && ./scripts/setup-acls-laravel.sh
   ```

2. **Consumer inicia mas nunca mostra "[timestamp] Recebido" (subscribe / consumer group)**  
   Pode aparecer `COORDINATOR_NOT_AVAILABLE` ou "Waiting for coordinator". O broker precisa de `KAFKA_OFFSETS_TOPIC_REPLICATION_FACTOR=1` no `docker-compose.yml` da raiz (ver **[CONSUMER-GROUP-ISSUE.md](CONSUMER-GROUP-ISSUE.md)**). Após adicionar e reiniciar o Kafka, rode de novo o consumer. **Workaround:** usar partição fixa:
   ```bash
   docker compose run --rm app php artisan kafka:consume-laravel-events --assign-partition=0
   ```

3. **Outro consumer group**  
   Se usar `--group=meu-grupo`, é preciso conceder READ nesse grupo (ou manter o grupo padrão `laravel-consumer-group`).

## Critério de sucesso (PLANO.md)

- Kafka (com SASL e ACLs) na raiz + Laravel no docker-compose do Laravel, conectando com SASL
- Producer envia mensagem; consumer (em outro terminal/processo) recebe e processa
- Acesso aos tópicos apenas pelo principal `laravel` autorizado
