<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Facades\Kafka;

class ConsumeLaravelEventsCommand extends Command
{
    protected $signature = 'kafka:consume-laravel-events
                            {--topic= : Tópico (default: config kafka.topic)}
                            {--offset=earliest : Offset inicial: earliest ou latest}
                            {--group= : Consumer group (default: config). Use outro para ler tudo de novo.}';

    protected $description = 'Consome mensagens do tópico laravel-events (Fase 1). Rodar em processo separado.';

    public function handle(): int
    {
        $topic = $this->option('topic') ?: config('kafka.topic', 'laravel-events');
        $brokers = config('kafka.brokers');
        $groupId = $this->option('group') ?: config('kafka.consumer_group_id');
        $offsetReset = $this->option('offset') ?: 'earliest';

        $this->info("Consumindo tópico: {$topic} (brokers: {$brokers}, group: {$groupId}, offset: {$offsetReset})");

        $consumer = Kafka::consumer([$topic], $groupId, $brokers)
            ->withSasl(
                username: config('kafka.sasl.username'),
                password: config('kafka.sasl.password'),
                mechanisms: config('kafka.sasl.mechanisms'),
                securityProtocol: config('kafka.sasl.security_protocol'),
            )
            ->withOptions(['auto.offset.reset' => $offsetReset])
            ->withAutoCommit()
            ->withHandler(function (ConsumerMessage $message): void {
                $body = $message->getBody();
                try {
                    $bodyStr = is_string($body)
                        ? $body
                        : json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                } catch (\Throwable) {
                    $bodyStr = (string) $body;
                }
                $this->getOutput()->writeln(sprintf(
                    '[%s] Recebido: %s',
                    now()->toDateTimeString(),
                    $bodyStr,
                ));
            })
            ->build();

        $consumer->consume();

        return self::SUCCESS;
    }
}
