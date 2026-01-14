<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class KafkaService
{
    private $isAvailable = false;
    private $broker;
    private $kafkaProducerPath = '/usr/bin/kafka-console-producer.sh';

    public function __construct()
    {
        $this->checkKafkaAvailability();
    }

    private function checkKafkaAvailability()
    {
        $broker = env('KAFKA_BROKERS', 'localhost:9092');
        $parts = explode(':', $broker);
        $host = $parts[0] ?? 'localhost';
        $port = (int)($parts[1] ?? 9092);

        $socket = @fsockopen($host, $port, $errno, $errstr, 1);

        if ($socket) {
            fclose($socket);
            $this->broker = $broker;
            $this->isAvailable = true;
            Log::info('Kafka broker available at: ' . $broker);
        } else {
            Log::warning("Kafka broker not available at {$broker}. Running in fallback mode.");
            $this->isAvailable = false;
        }
    }

    public function isAvailable(): bool
    {
        return $this->isAvailable;
    }

    public function publish(string $topic, array $message)
    {
        if (!$this->isAvailable) {
            Log::info("Kafka unavailable - Topic: {$topic}, Message: " . json_encode($message));
            return false;
        }

        try {
            $messageJson = json_encode($message);
            $broker = $this->broker;

            // Use kafka-console-producer.sh with full path
            $command = "echo '{$messageJson}' | {$this->kafkaProducerPath} --bootstrap-server {$broker} --topic {$topic} 2>&1";

            $result = Process::run($command);

            if ($result->successful()) {
                Log::info("Kafka message published to topic '{$topic}': " . $messageJson);
                return true;
            } else {
                Log::error("Kafka publish failed for topic '{$topic}': " . $result->errorOutput());
                return false;
            }
        } catch (Exception $e) {
            Log::error('Kafka publish error: ' . $e->getMessage());
            return false;
        }
    }
}
