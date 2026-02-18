# Problema: Consumer group (subscribe) não funciona

**Status:** ✅ **resolvido** (2026-02-18).  
**Solução:** Configurar `KAFKA_OFFSETS_TOPIC_REPLICATION_FACTOR=1` no broker (ver [Solução aplicada](#solução-aplicada) abaixo).

---

## Solução aplicada

Em cluster **KRaft com um único broker**, o tópico interno `__consumer_offsets` (usado pelo group coordinator) é criado com um *replication factor* que por padrão pode ser maior que 1. Com apenas um broker, o coordinator não consegue atender às requisições e retorna **COORDINATOR_NOT_AVAILABLE**.

**Correção:** No `docker-compose.yml` da raiz do repositório, foi adicionada a variável de ambiente no serviço `kafka`:

```yaml
KAFKA_OFFSETS_TOPIC_REPLICATION_FACTOR: "1"
```

Assim o broker cria `__consumer_offsets` com replication factor 1 e o group coordinator fica disponível. Após **reiniciar o container Kafka** (`docker compose up -d --force-recreate kafka`), o consumer em modo subscribe passa a receber mensagens normalmente.

- **Arquivo alterado:** `../docker-compose.yml`
- **Workaround com partição fixa** (`--assign-partition=0`) continua disponível para cenários em que não se queira usar consumer group.

---

## Sintomas (antes da correção)

Quando o comando `kafka:consume-laravel-events` é executado **sem** `--assign-partition` (ou seja, usando **subscribe** no tópico com consumer group):

1. O consumer inicia e exibe algo como:  
   `Consumindo tópico: laravel-events (brokers: kafka:9092, group: laravel-consumer-group, offset: earliest)`.
2. Nenhuma mensagem é recebida; o handler nunca é chamado.
3. No log do rdkafka (com debug) ou em exceções do pacote aparece:
   - **`COORDINATOR_NOT_AVAILABLE`** / **"The coordinator is not available"**
   - Ou **"Local: Waiting for coordinator"** quando o pacote tenta fazer auto-commit.

Ou seja: o **group coordinator** do broker não fica disponível para o consumer, então o subscribe (consumer group) não funciona.

---

## Ambiente onde o problema ocorre

- **Kafka:** modo KRaft (sem Zookeeper), **um único broker**.
- **Imagem:** `apache/kafka:latest` (4.x) na raiz do repositório (`docker-compose.yml`).
- **SASL/ACL:** configurados; o usuário `laravel` tem permissão READ no tópico e READ nos consumer groups (incl. `laravel-consumer-group` e `*`).
- **Laravel:** pacote `mateusjunges/laravel-kafka`, consumer com `Kafka::consumer([$topic], $groupId, $brokers)->withSasl(...)->withAutoCommit()->...->build()` e `subscribe()` implícito (sem `assignPartitions()`).

O problema foi observado tanto com Kafka 3.7.0 quanto com 4.x (latest).

---

## O que já foi verificado / tentado

| Ação | Resultado |
|------|-----------|
| ACLs para o consumer group | Configuradas (READ para `laravel-consumer-group` e `*`). Sem isso ocorre "Group authorization failed"; com ACLs o erro passa a ser coordinator. |
| Kafka 3.7.0 em vez de 4.x | Mesmo erro `COORDINATOR_NOT_AVAILABLE` em single-broker KRaft. |
| Restart do broker | Não resolve. |
| Script de ACLs após recriar o Kafka | ACLs aplicadas; coordinator continua indisponível. |

Conclusão: o problema está na **disponibilidade do group coordinator** em cluster KRaft com um único nó, não na falta de ACL ou na versão 3.7 vs 4.x em si.

---

## Workaround alternativo (partição fixa)

Se em algum ambiente o coordinator não estiver disponível, ainda é possível consumir usando **partição fixa** (assign), sem consumer group:

```bash
php artisan kafka:consume-laravel-events --assign-partition=0
```

- **Limitações:** não há consumer group, não há commit de offset por grupo, não escala com múltiplos consumers no mesmo grupo.

---

## Referências no projeto

- **Comando consumer:** `app/Console/Commands/ConsumeLaravelEventsCommand.php`  
  - Modo subscribe: sem `--assign-partition`.  
  - Modo workaround: com `--assign-partition=0` e `withAutoCommit(false)`.
- **Config Kafka (raiz):** `../docker-compose.yml` (serviço `kafka`), `../kafka-config/`.
- **ACLs:** `../scripts/setup-acls-laravel.sh`.
- **README consumer:** `README.md` neste diretório (seção 4 e Troubleshooting).

---

*Documento criado para rastrear a resolução do uso de consumer group (subscribe) com Kafka neste repositório.*
