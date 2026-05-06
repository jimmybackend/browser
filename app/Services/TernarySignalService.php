<?php

declare(strict_types=1);

namespace Browser\Services;

use Browser\Core\Database;
use Browser\Core\TernarySignal;
use InvalidArgumentException;
use PDO;

final class TernarySignalService
{
    private const ALLOWED_SOURCES = ['system', 'ai', 'user', 'admin'];

    public function __construct(private readonly ?PDO $connection = null)
    {
    }

    public function record(
        string $signalKey,
        int $signalValue,
        ?string $entityType = null,
        ?string $entityId = null,
        ?int $userId = null,
        string $source = 'system',
        ?float $confidence = null,
        ?string $reasonCode = null,
        ?string $humanNote = null,
        array $metadata = []
    ): void {
        if (!TernarySignal::isValid($signalValue)) {
            throw new InvalidArgumentException('signalValue debe ser -1, 0 o 1.');
        }

        if (!in_array($source, self::ALLOWED_SOURCES, true)) {
            throw new InvalidArgumentException('source no permitido.');
        }

        if ($confidence !== null && ($confidence < 0 || $confidence > 1)) {
            throw new InvalidArgumentException('confidence debe estar entre 0 y 1.');
        }

        if ($this->containsSensitiveData($humanNote)) {
            throw new InvalidArgumentException('humanNote parece contener datos sensibles.');
        }

        if ($this->containsSensitiveDataFromMetadata($metadata)) {
            throw new InvalidArgumentException('metadata parece contener datos sensibles.');
        }

        $statement = $this->pdo()->prepare(
            'INSERT INTO ternary_signal_events
                (signal_key, signal_value, entity_type, entity_id, user_id, source, confidence, reason_code, human_note, metadata)
             VALUES
                (:signal_key, :signal_value, :entity_type, :entity_id, :user_id, :source, :confidence, :reason_code, :human_note, :metadata)'
        );

        $statement->execute([
            ':signal_key' => $signalKey,
            ':signal_value' => $signalValue,
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':user_id' => $userId,
            ':source' => $source,
            ':confidence' => $confidence,
            ':reason_code' => $reasonCode,
            ':human_note' => $humanNote,
            ':metadata' => $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR),
        ]);
    }

    private function pdo(): PDO
    {
        return $this->connection ?? Database::connection();
    }

    private function containsSensitiveData(?string $text): bool
    {
        if ($text === null || trim($text) === '') {
            return false;
        }

        return (bool)preg_match('/(password|passwd|token|secret|api[_-]?key|authorization|bearer|credit\s?card|ssn|dni)/i', $text);
    }

    private function containsSensitiveDataFromMetadata(array $metadata): bool
    {
        if ($metadata === []) {
            return false;
        }

        $flatText = json_encode($metadata, JSON_THROW_ON_ERROR);

        return $this->containsSensitiveData($flatText);
    }
}
