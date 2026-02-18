<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\Message;

class KafkaProducerController extends Controller
{
    /**
     * Envia uma mensagem para o tópico laravel-events (Fase 1).
     */
    public function __invoke(Request $request): JsonResponse
    {
        $topic = config('kafka.topic', 'laravel-events');
        $brokers = config('kafka.brokers');
        $body = [
            'event' => 'LaravelEvent',
            'message' => $request->input('message', 'Hello from Laravel producer'),
            'timestamp' => now()->toIso8601String(),
        ];

        try {
            $message = new Message(
                body: $body,
                headers: ['source' => 'laravel'],
                key: (string) now()->timestamp,
            );

            Kafka::publish($brokers)
                ->onTopic($topic)
                ->withSasl(
                    username: config('kafka.sasl.username'),
                    password: config('kafka.sasl.password'),
                    mechanisms: config('kafka.sasl.mechanisms'),
                    securityProtocol: config('kafka.sasl.security_protocol'),
                )
                ->withMessage($message)
                ->send();

            return response()->json([
                'ok' => true,
                'topic' => $topic,
                'body' => $body,
            ]);
        } catch (\Throwable $e) {
            Log::error('Kafka produce failed', [
                'topic' => $topic,
                'brokers' => $brokers,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
                'topic' => $topic,
                'hint' => 'Verifique se o Kafka está no ar, a rede (kafka-network) e as variáveis KAFKA_* no .env ou docker-compose.',
            ], 502);
        }
    }
}
