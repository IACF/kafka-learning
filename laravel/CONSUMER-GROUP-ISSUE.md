# Problema: Consumer group (subscribe) não funciona

**Status:** em aberto — precisa ser resolvido.  
**Impacto:** não é possível usar Kafka com consumer group (subscribe) neste ambiente; hoje o consumo só funciona com partição fixa (`--assign-partition=0`), o que não atende uso real com múltiplos consumers e offset por grupo.

---

## Sintomas

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

## Workaround atual (não substitui subscribe)

Enquanto o coordinator não funciona, o consumo foi possível usando **partição fixa** (assign), sem consumer group:

```bash
php artisan kafka:consume-laravel-events --assign-partition=0
```

- **Limitações:** não há consumer group, não há commit de offset por grupo, não escala com múltiplos consumers no mesmo grupo. Serve só para validar producer/consumer; **não atende uso real com subscribe**.

---

## O que precisa ser feito (resolver de fato)

Objetivo: **fazer o subscribe (consumer group) funcionar** para que:

- O consumer use `group.id` e receba mensagens via subscribe (sem `--assign-partition`).
- Auto-commit (ou commit manual) funcione, sem "Waiting for coordinator".
- Múltiplos consumers no mesmo grupo possam ser usados no futuro.

Possíveis direções (a investigar e implementar):

1. **Configuração do broker KRaft**
   - Verificar na documentação do Apache Kafka (4.x) se há parâmetros ou requisitos para o group coordinator em single-broker KRaft (ex.: algum flag, timeout, ou criação do tópico interno `__consumer_offsets`).
   - Checar logs do container Kafka ao subir o consumer (erros ou avisos relacionados a coordinator ou `__consumer_offsets`).

2. **Cluster com mais de um broker**
   - Testar com 2+ brokers em KRaft para ver se o coordinator passa a responder (muitos problemas de coordinator em single-broker deixam de ocorrer com mais nós).

3. **Bugs conhecidos / issues**
   - Pesquisar por `COORDINATOR_NOT_AVAILABLE` + KRaft + single broker (e variantes) em:
     - [Apache Kafka JIRA](https://issues.apache.org/jira/projects/KAFKA)
     - [Kafka documentation](https://kafka.apache.org/documentation/)
   - Ver se há workaround oficial ou fix em versão mais recente.

4. **Alternativa de stack**
   - Se for inviável resolver com KRaft single-broker, avaliar:
     - Kafka com Zookeeper (se ainda suportado na versão desejada), ou
     - Mais nós no cluster KRaft.

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
