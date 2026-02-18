<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Contracts\MessageConsumer;
use Junges\Kafka\Facades\Kafka;
use RdKafka\TopicPartition;

class ConsumeLaravelEventsCommand extends Command
{
    protected $signature = 'kafka:consume-laravel-events
                            {--topic= : Tópico (default: config kafka.topic)}
                            {--offset=earliest : Offset inicial: earliest ou latest}
                            {--group= : Consumer group (default: config). Use outro para ler tudo de novo.}
                            {--assign-partition= : Partição fixa (ex: 0). Contorna coordinator quando KRaft retorna COORDINATOR_NOT_AVAILABLE.}';

    protected $description = 'Consome mensagens do tópico laravel-events (Fase 1). Rodar em processo separado.';

    public function handle(): int
    {
        $topic = $this->option('topic') ?: config('kafka.topic', 'laravel-events');
        $brokers = config('kafka.brokers');
        $groupId = $this->option('group') ?: config('kafka.consumer_group_id');
        $offsetReset = $this->option('offset') ?: 'earliest';
        $assignPartition = $this->option('assign-partition');

        $mode = $assignPartition !== null && $assignPartition !== ''
            ? "partição fixa {$assignPartition} (sem consumer group)"
            : "group: {$groupId}, offset: {$offsetReset}";
        $this->info("Consumindo tópico: {$topic} (brokers: {$brokers}, {$mode})");

        $output = $this->getOutput();

        $options = ['auto.offset.reset' => $offsetReset];
        $useAssignPartition = $assignPartition !== null && $assignPartition !== '';

        $builder = Kafka::consumer([$topic], $groupId, $brokers)
            ->withSasl(
                username: config('kafka.sasl.username'),
                password: config('kafka.sasl.password'),
                mechanisms: config('kafka.sasl.mechanisms'),
                securityProtocol: config('kafka.sasl.security_protocol'),
            )
            ->withOptions($options);

        if ($useAssignPartition) {
            // Partição fixa: sem consumer group, então não há coordinator — desliga auto-commit para evitar "Waiting for coordinator"
            $builder = $builder->withAutoCommit(false);
            $partition = (int) $assignPartition;
            $offset = $offsetReset === 'earliest' ? RD_KAFKA_OFFSET_BEGINNING : RD_KAFKA_OFFSET_END;
            $builder = $builder->assignPartitions([
                new TopicPartition($topic, $partition, $offset),
            ]);
        } else {
            $builder = $builder->withAutoCommit();
        }

        $consumer = $builder
            ->withHandler(function (ConsumerMessage $message, MessageConsumer $consumer) use ($output): void {
                $body = $message->getBody();
                try {
                    $bodyStr = is_string($body)
                        ? $body
                        : json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                } catch (\Throwable) {
                    $bodyStr = (string) $body;
                }
                $line = sprintf(
                    '[%s] Recebido: %s',
                    now()->toDateTimeString(),
                    $bodyStr,
                );
                $output->writeln($line);
            })
            ->build();

        $consumer->consume();

        return self::SUCCESS;
    }
}
