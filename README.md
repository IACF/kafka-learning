# Kafka Learning

Projeto de aprendizado de Kafka com Laravel 11 e NestJS. O plano completo está em [PLANO.md](PLANO.md).

## Fase 0: Kafka (implementada) + SASL/ACL

Ambiente Kafka na raiz para uso pelas fases seguintes.

### Subir o ambiente

Na raiz do projeto:

```bash
docker compose up -d
```

- **Kafka:** porta `9092`
- **Kafka UI:** http://localhost:8080 (inspecionar tópicos e mensagens)

### Comandos úteis

Usa a imagem oficial **apache/kafka**; os scripts ficam em `/opt/kafka/bin/`.

Criar um tópico de teste:

```bash
docker exec -it kafka-learning-broker /opt/kafka/bin/kafka-topics.sh --create --topic teste --bootstrap-server localhost:9092 --partitions 1 --replication-factor 1
```

Listar tópicos:

```bash
docker exec -it kafka-learning-broker /opt/kafka/bin/kafka-topics.sh --list --bootstrap-server localhost:9092
```

Produzir mensagem (console):

```bash
docker exec -it kafka-learning-broker /opt/kafka/bin/kafka-console-producer.sh --topic teste --bootstrap-server localhost:9092
```

Depois digite as mensagens (uma por linha) e use **Ctrl+C** para sair.

Consumir mensagens (console):

```bash
docker exec -it kafka-learning-broker /opt/kafka/bin/kafka-console-consumer.sh --topic teste --from-beginning --bootstrap-server localhost:9092 --group meu-grupo
```

**Observação sobre o consumer no terminal:** Com a imagem oficial **apache/kafka** (Kafka 4.x), o `kafka-console-consumer.sh` costuma não exibir mensagens no terminal. Use os **scripts com kcat** abaixo para testar o consumer na Fase 0.

### Como funciona o kcat

**kcat** (antes **kafkacat**) é um cliente de linha de comando para Kafka: lê e escreve mensagens em tópicos, sem precisar do Java nem dos scripts oficiais do Kafka. Por isso ele costuma funcionar bem em ambientes onde o `kafka-console-consumer.sh` falha (como na imagem apache/kafka 4.x).

- **Modos principais:**  
  - **Producer (-P):** envia mensagens para um tópico (cada linha da entrada vira uma mensagem).  
  - **Consumer (-C):** lê mensagens de um tópico e imprime na saída.

- **Opções que usamos nos scripts:**  
  - `-b kafka:9092` — endereço do broker (bootstrap).  
  - `-t saitama` — tópico.  
  - `-o beginning` — no consumer, começar do início do tópico (equivalente a `--from-beginning`).  
  - `-e` — no consumer, encerrar ao chegar ao fim do tópico (não ficar esperando mensagens novas).  
  - `-c 100` — no consumer, ler no máximo 100 mensagens (evita loop infinito quando usamos `-e`).

- **Por que em container?** O kcat roda em um container Docker na rede `kafka-network` para enxergar o broker pelo hostname `kafka:9092`, igual às aplicações Laravel/NestJS nas fases seguintes.

- **Offset na UI vs no terminal:** A UI costuma mostrar o offset da **última mensagem** (ex.: 12). O kcat em "Reached end of topic at offset 13" mostra o **próximo** offset (onde a próxima mensagem será escrita). Ou seja: já foram lidas as mensagens 0 a 12; 13 é só a posição seguinte. A diferença de 1 é esperada.

- **Modo `-f` (ouvir):** O script filtra as linhas que começam com `%` (mensagens de status do kcat como "Reached end of topic") para que só o **conteúdo** das mensagens apareça no terminal.

### Testar consumer com script (kcat)

Os scripts em `scripts/` usam **kcat** nesse container e funcionam para consumir e produzir no terminal.

**Consumir mensagens** (lê do início do tópico e encerra):

```bash
./scripts/consume.sh saitama
```

Ou para um tópico qualquer (padrão: `teste`):

```bash
./scripts/consume.sh meu-topico
```

**Ficar ouvindo** novas mensagens (não encerra; Ctrl+C para sair):

```bash
./scripts/consume.sh saitama -f
```

**Produzir uma mensagem** via script:

```bash
./scripts/produce.sh saitama "Minha mensagem"
```

Requer que o Kafka esteja no ar (`docker compose up -d`).

### Parar

```bash
docker compose down
```

A rede `kafka-network` fica disponível para os projetos Laravel e NestJS (Fases 1 e 2) se conectarem ao broker pelo hostname `kafka:9092`.

### SASL e ACL (Fases 1 e 2)

O broker está configurado com **SASL PLAIN** na porta 9092. Credenciais (em `kafka-config/kafka_server_jaas.conf`):

- **admin:** usuário `admin`, senha `admin-secret` (super user; Kafka UI e scripts de ACL).
- **Laravel (Fase 1):** usuário `laravel`, senha `laravel-secret`.

Após subir o Kafka, rode **uma vez** o script de ACLs para o Laravel:

```bash
chmod +x scripts/setup-acls-laravel.sh
./scripts/setup-acls-laravel.sh
```

---

## Fase 1: Laravel 11 (implementada)

Laravel 11 em `laravel/` como **producer** e **consumer** com SASL. Ver [laravel/README.md](laravel/README.md) para:

- Como subir o Laravel (docker-compose em `laravel/`)
- Credenciais/ACLs usadas
- Como rodar o consumer e disparar o producer
