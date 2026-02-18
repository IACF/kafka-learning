# Plano de Aprendizado e Implementação: Kafka com Laravel 11 e NestJS

## Estrutura final do repositório

```
kafka-learning/
├── docker-compose.yml          # Kafka (e opcionalmente Kafka UI) na raiz
├── laravel/                    # Projeto Laravel 11
│   ├── docker-compose.yml      # App PHP + serviços Laravel
│   └── ...
├── nest/                       # Projeto NestJS
│   ├── docker-compose.yml      # App Node + serviços Nest
│   └── ...
└── docs/                       # Teoria e anotações (opcional)
```

Cada aplicação sobe com seu próprio `docker-compose` e se conecta ao Kafka exposto na rede (host ou rede Docker compartilhada). O Kafka fica na raiz para ser o "hub" único entre as duas stacks.

---

## Teoria base (ler antes de qualquer fase)

### O que é Kafka e por que mensageria?

- **Problema**: sistemas precisam se comunicar de forma assíncrona, durável e escalável (ex.: pedido criado → notificar estoque, email, analytics).
- **Kafka**: plataforma de **event streaming** (log de eventos distribuído). Diferente de filas "tradicionais", as mensagens ficam persistidas por um tempo; vários consumidores podem ler o mesmo tópico; e o modelo é publish/subscribe com partições para paralelismo.

### Conceitos essenciais

| Conceito           | Explicação                                                                                                                             |
| ------------------ | -------------------------------------------------------------------------------------------------------------------------------------- |
| **Broker**         | Servidor Kafka que armazena e serve os dados. Um cluster tem vários brokers.                                                           |
| **Topic**          | Nome lógico do stream de mensagens (ex.: `pedidos-criados`).                                                                           |
| **Partition**      | Tópico é dividido em partições; cada mensagem vai para uma partição (por chave ou round-robin). Ordem garantida só dentro da partição. |
| **Producer**       | Aplicação que envia mensagens para um tópico.                                                                                          |
| **Consumer**       | Aplicação que lê mensagens de um tópico.                                                                                               |
| **Consumer Group** | Conjunto de consumidores que dividem as partições entre si (cada partição é consumida por um único membro do grupo).                   |
| **Offset**         | Posição da última mensagem lida em uma partição. Kafka guarda isso por consumer group.                                                 |

Fluxo resumido: **Producer** → **Topic (partitions)** → **Consumer(s)**. Laravel e NestJS serão producers e/ou consumers em diferentes fases.

---

## Fase 0: Ambiente Kafka na raiz (só infraestrutura)

**Objetivo:** Subir Kafka localmente na raiz do repositório, sem Laravel/NestJS, e validar que está acessível.

**Teoria breve:**

- **KRaft**: versões recentes do Kafka podem rodar sem Zookeeper (modo KRaft), um broker standalone é suficiente para aprendizado.
- **Porta 9092**: é a porta padrão onde os clientes (Laravel, NestJS) se conectam.

**Implementação:**

- Criar na **raiz** do projeto um `docker-compose.yml` com:
  - Serviço **Kafka** (imagem oficial `apache/kafka:latest` ou Confluent) em modo standalone/KRaft.
  - Expor a porta `9092` para o host.
  - (Opcional) Serviço **Kafka UI** (ex.: `provectuslabs/kafka-ui`) para inspecionar tópicos e mensagens.

**Critério de sucesso:**  
`docker compose up -d` na raiz; criar um tópico de teste via CLI ou UI; enviar e consumir uma mensagem de teste (script ou UI). Nenhuma aplicação Laravel/Nest ainda.

**Entrega:** Pasta raiz com `docker-compose.yml` do Kafka (+ README ou comentários no arquivo com comandos úteis).

---

## Fase 1: Laravel 11 como producer e consumer (standalone)

**Objetivo:** Projeto Laravel 11 em `laravel/`, com seu próprio `docker-compose`, que produza e consuma mensagens no Kafka. Tudo deve funcionar só com Laravel + Kafka (NestJS não entra nesta fase). Laravel 11 foi escolhido para garantir compatibilidade com o pacote [mateusjunges/laravel-kafka](https://github.com/mateusjunges/laravel-kafka).

**Teoria antes de codar:**

- **Producer no Laravel:** Um comando, job ou controller chama a lib (ex.: [mateusjunges/laravel-kafka](https://github.com/mateusjunges/laravel-kafka)) para enviar uma mensagem para um tópico (ex.: `laravel-events`). Você verá no Kafka UI a mensagem aparecer.
- **Consumer no Laravel:** Um comando Artisan que fica "ouvindo" o tópico (loop de consume). Ao receber uma mensagem, executa um handler (log, salvar no DB, etc.). Consumer groups no Laravel seguem o mesmo conceito do Kafka (groupId na configuração).

**SASL e ACL (obrigatório na Fase 1):**

- A conexão do Laravel com o Kafka deve usar **SASL** (autenticação): o broker precisa saber a identidade do cliente (ex.: usuário/senha, mecanismo PLAIN).
- O broker deve ter **ACLs** configurados: apenas o principal (usuário) usado pelo Laravel deve ter permissão **READ** e **WRITE** (e **DESCRIBE**, se necessário) nos tópicos que a aplicação usa. Assim só a aplicação autorizada produz e consome nesses tópicos.
- Configurar no Laravel (via `config/kafka.php` ou variáveis de ambiente): `security.protocol=SASL_PLAINTEXT` (ou SASL_SSL em produção), `sasl.mechanism=PLAIN`, `sasl.username` e `sasl.password` para o principal que tiver as ACLs no broker.

**Implementação:**

- Criar pasta `laravel/` e projeto Laravel 11 (composer ou container Docker com `laravel new`).
- Adicionar `docker-compose.yml` em `laravel/` com:
  - Serviço da aplicação (PHP-FPM + Laravel).
  - Variáveis de ambiente para Kafka: `KAFKA_BROKERS`, `KAFKA_SASL_USERNAME`, `KAFKA_SASL_PASSWORD`, e demais configs SASL necessárias (o broker na raiz ou usado na fase 1 deve estar configurado com SASL e ACLs).
- Instalar e configurar `mateusjunges/laravel-kafka`: brokers, grupo do consumer, e **configurações SASL** (security.protocol, sasl.mechanism, sasl.username, sasl.password).
- Implementar:
  - **Producer:** rota ou comando que envia uma mensagem para um tópico (ex.: `laravel-events`).
  - **Consumer:** comando Artisan que consome desse tópico e faz log ou ação simples.
- Documentar no README da fase: como subir Kafka (com SASL/ACL), como subir Laravel, credenciais/ACLs usadas, como rodar o consumer e como disparar o producer.

**Critério de sucesso:**  
Kafka (com SASL e ACLs) na raiz + Laravel no docker-compose do Laravel, conectando com SASL. Producer envia mensagem; consumer (rodando em outro terminal/processo) recebe e processa. Acesso aos tópicos apenas pelo principal autorizado. Fase fechada e estável, sem NestJS.

---

## Fase 2: NestJS como producer e consumer (standalone)

**Objetivo:** Projeto NestJS em `nest/`, com seu próprio `docker-compose`, que produza e consuma mensagens no mesmo Kafka. Funcionamento independente do Laravel.

**Teoria antes de codar:**

- **NestJS + Kafka:** Nest usa o transporte `Transport.KAFKA` (`@nestjs/microservices`) e internamente usa KafkaJS. Você configura `brokers`, `clientId` e `consumer.groupId`. Um microservice pode ser apenas consumer, apenas producer (via `ClientKafka`), ou ambos.
- **Pattern request-response vs apenas eventos:** Para aprendizado, começar com envio de eventos (fire-and-forget) e consumo com handler é o mais simples; request-response sobre Kafka é possível mas mais avançado.

**SASL e ACL (obrigatório na Fase 2):**

- A conexão do NestJS com o Kafka deve usar **SASL** (autenticação): o broker identifica o cliente (ex.: usuário/senha, mecanismo PLAIN).
- O broker deve ter **ACLs** configurados: apenas o principal usado pelo NestJS deve ter **READ** e **WRITE** (e **DESCRIBE** quando necessário) nos tópicos da aplicação.
- Configurar no NestJS (opções do `Transport.KAFKA` ou variáveis de ambiente): `securityProtocol: 'sasl_plaintext'` (ou `sasl_ssl` em produção), `sasl.mechanism`, `sasl.username` e `sasl.password` para o principal que possui as ACLs no broker.

**Implementação:**

- Criar pasta `nest/` e projeto NestJS (nest cli ou imagem Node no Docker).
- Adicionar `docker-compose.yml` em `nest/` com:
  - Serviço da aplicação Node.
  - Variáveis de ambiente para Kafka: `KAFKA_BROKERS`, `KAFKA_SASL_USERNAME`, `KAFKA_SASL_PASSWORD`, e demais configs SASL (o broker usado na fase 2 deve estar com SASL e ACLs).
- Configurar `Transport.KAFKA` no `main.ts` (microservice) ou `ClientKafka` em um módulo, incluindo as **opções SASL** (securityProtocol, sasl.mechanism, sasl.username, sasl.password).
- Implementar:
  - **Producer:** endpoint ou método que envia mensagem para um tópico (ex.: `nest-events`).
  - **Consumer:** controller/message pattern que subscreve esse tópico e processa a mensagem (log ou ação simples).
- README da fase: como subir Kafka (com SASL/ACL), como subir Nest, credenciais/ACLs usadas, como testar producer e consumer.

**Critério de sucesso:**  
Kafka (com SASL e ACLs) na raiz + Nest no docker-compose do Nest, conectando com SASL. Producer envia; consumer Nest recebe e processa. Acesso aos tópicos apenas pelo principal autorizado. Fase fechada e estável, sem Laravel.

---

## Fase 3: Integração Laravel ↔ NestJS via Kafka

**Objetivo:** Um sistema produz eventos e o outro consome. Ex.: Laravel produz em `pedidos-criados`, NestJS consome e reage (ex.: log, "notificação"). Ou o contrário. Garantir que a comunicação seja apenas via Kafka. **SASL e ACL** continuam em uso: cada aplicação conecta com seu principal; as ACLs no broker devem permitir ao Laravel WRITE (e READ, se consumir) e ao NestJS READ (e WRITE, se produzir) nos tópicos compartilhados.

**Teoria antes de codar:**

- **Contrato do tópico:** Formato da mensagem (ex.: JSON com `event`, `payload`, `timestamp`). Definir um contrato simples e documentar (no README ou em `docs/`).
- **Consumer group:** NestJS deve usar um `groupId` próprio; Laravel outro, se ambos consumirem o mesmo tópico (comportamento esperado em event-driven).

**Implementação:**

- Definir um tópico compartilhado (ex.: `integracao-pedidos`) e um schema mínimo da mensagem (ex.: `{ "event": "PedidoCriado", "data": { ... } }`).
- **ACLs:** No broker, configurar ACLs para que o principal do Laravel possa WRITE (e READ onde aplicável) e o principal do NestJS possa READ (e WRITE onde aplicável) nesse tópico. Manter SASL em ambas as aplicações.
- **Cenário A – Laravel producer, Nest consumer:**  
  - Laravel: ao "criar pedido" (rota ou comando), publicar mensagem no tópico (conexão SASL).  
  - NestJS: consumer inscrito no tópico (conexão SASL); ao receber, processa e responde (log/DB/outro serviço).
- **Cenário B (opcional na mesma fase):** Nest producer, Laravel consumer no mesmo tópico ou em outro; ACLs ajustados para os papéis de cada um.
- Ajustes de rede: garantir que, ao subir os dois docker-compose, ambos resolvam o Kafka (rede externa, hostname, etc.) e usem as credenciais SASL corretas.

**Critério de sucesso:**  
Laravel envia evento; NestJS (ou o outro caminho) recebe e processa. Nenhuma chamada HTTP direta entre Laravel e Nest; só Kafka, com SASL e ACLs. Fase estável e reproduzível.

---

## Fase 4 (opcional): Aprofundamento

**Objetivo:** Consolidar conceitos com um passo a mais, sem quebrar as fases anteriores.

- **Tópicos e partições:** Criar tópico com mais de uma partição; explicar ordem por chave; testar com chaves diferentes.
- **Consumer groups:** Dois consumidores Nest (ou Laravel) no mesmo grupo e ver partições sendo distribuídas; ou dois grupos consumindo o mesmo tópico.
- **Dead letter / retry:** Tratamento de erro no consumer (retry, tópico de "dead letter") em um dos lados (Laravel ou Nest).

Cada item pode ser um mini-doc de teoria + pequena implementação, mantendo as fases 0–3 intactas.

---

## Ordem de execução e dependências

- **Fase 0** é pré-requisito para 1 e 2.
- **Fases 1 e 2** podem ser feitas em paralelo (apenas dependem do Kafka).
- **Fase 3** depende de 1 e 2 estarem funcionando.
- **Fase 4** é opcional e construída em cima da 3.

---

## Resumo de entregas por fase

| Fase | O que sobe               | O que você aprende                                                                 |
| ---- | ------------------------ | ---------------------------------------------------------------------------------- |
| 0    | Kafka (raiz)             | Broker, tópico, porta, CLI/UI (sem SASL/ACL)                                       |
| 1    | Laravel + Kafka          | Producer/consumer no Laravel, **SASL e ACL**, docker-compose da app                |
| 2    | NestJS + Kafka           | Producer/consumer no Nest, **SASL e ACL**, Transport.KAFKA                         |
| 3    | Laravel + Nest + Kafka   | Contrato de evento, integração entre stacks, **SASL e ACL** nos dois lados         |
| 4    | Mesmo + partições/grupos | Partitions, consumer groups, resiliência                                            |

---

## Recomendações técnicas

- **Kafka na raiz:** Usar rede Docker nomeada (ex.: `kafka-network`) e nos compose do Laravel e Nest usar `networks: external: kafka-network` para todos acessarem o mesmo broker. **Para as Fases 1 e 2:** o broker deve ser configurado com **SASL** (ex.: SASL_PLAINTEXT, mecanismo PLAIN) e **ACLs** (ex.: StandardAuthorizer em KRaft), com usuários/principais distintos para Laravel e NestJS e permissões READ/WRITE/DESCRIBE apenas nos tópicos que cada um usa.
- **Laravel:** Usar **Laravel 11** com o pacote [mateusjunges/laravel-kafka](https://github.com/mateusjunges/laravel-kafka), configurando **SASL** (security.protocol, sasl.mechanism, sasl.username, sasl.password) conforme as ACLs do broker.
- **NestJS:** `@nestjs/microservices` com `Transport.KAFKA` e KafkaJS, configurando **SASL** nas opções do cliente (securityProtocol, sasl.mechanism, sasl.username, sasl.password) conforme as ACLs do broker. Documentação: [Microservices - Kafka](https://docs.nestjs.com/microservices/kafka).
- **Versões:** Laravel 11 (para garantir o Junges) e NestJS na versão estável mais recente no momento da implementação.

Seguindo essa ordem e garantindo que cada fase funcione sozinha (sem bugs e com passos documentados), o aprendizado fica progressivo e o ambiente reproduzível em qualquer máquina com Docker. As Fases 1, 2 e 3 utilizam **SASL e ACL** nas implementações.
