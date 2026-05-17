<?php

declare(strict_types=1);

namespace app\commands;

use DateTime;
use Throwable;
use UAParser\Exception\FileNotFoundException;
use UAParser\Parser;
use Yii;
use yii\console\Controller;
use yii\db\Exception;
use yii\helpers\Console;

class ParseController extends Controller
{
    private const LOG_COLUMNS = [
        'ip',
        'requested_at',
        'url',
        'user_agent',
        'operating_system_id',
        'architecture_id',
        'browser_id',
    ];

    private Parser $_parser;

    public int $batchSize = 100;

    /**
     * Кэш справочников
     */
    private array $_browserCache = [];
    private array $_osCache = [];
    private array $_architectureCache = [];

    /**
     * @throws FileNotFoundException
     */
    public function init(): void
    {
        parent::init();

        $this->_parser = Parser::create();
    }

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['batchSize']);
    }

    /**
     * Пример:
     *
     * php yii parse/parse @app/modimio.access.log
     */
    public function actionParse(string $filePath): int
    {
        Yii::$app->log->targets = [];
        Yii::$app->db->enableLogging = false;
        Yii::$app->db->enableProfiling = false;

        $path = Yii::getAlias($filePath);

        if (!file_exists($path)) {
            $this->stderr("File not found: {$path}\n", Console::FG_RED);

            return self::EXIT_CODE_ERROR;
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            $this->stderr("Cannot open file\n", Console::FG_RED);

            return self::EXIT_CODE_ERROR;
        }

        $this->stdout("Parsing started...\n\n", Console::FG_GREEN);

        $count = 0;
        $skipped = 0;
        $batch = [];
        $batchSize = max(1, $this->batchSize);

        while (($line = fgets($handle)) !== false) {
            try {
                $parsed = $this->parseLine($line);

                if ($parsed === null) {
                    $skipped++;
                    continue;
                }

                $batch[] = $this->buildLogRow($parsed);

                $count++;

                if (count($batch) >= $batchSize) {
                    $this->flushLogs($batch);
                    $batch = [];
                }
            } catch (Throwable $e) {
                $skipped++;
            }
        }

        if ($batch !== []) {
            $this->flushLogs($batch);
        }

        fclose($handle);

        $this->stdout("\nDone. Parsed rows: {$count}. Skipped rows: {$skipped}\n", Console::FG_GREEN);

        return self::EXIT_CODE_NORMAL;
    }

    /**
     * Парсинг строки лога
     */
    private function parseLine(string $line): ?array
    {
        $pattern = '/^(?<ip>\S+) .* \[(?<date>[^\]]+)\] '
            . '"(?:GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS)\s+(?<url>\S+).*" '
            . '\d+\s+\d+\s+"[^"]*"\s+"(?<ua>.*)"$/';

        if (!preg_match($pattern, trim($line), $matches)) {
            return null;
        }

        $userAgent = $matches['ua'];

        $result = $this->_parser->parse($userAgent);

        return [
            'ip' => $matches['ip'],
            'requested_at' => $this->parseDate($matches['date']),
            'url' => substr($matches['url'], 0, 65535),
            'user_agent' => substr($userAgent, 0, 500),

            'browser' => $result->ua->family ?? 'Unknown',
            'operating_system' => $result->os->family ?? 'Unknown',
            'architecture' => $this->detectArchitecture($userAgent),
        ];
    }

    /**
     * Apache date -> MySQL datetime
     *
     * 21/Mar/2019:00:20:06 +0300
     */
    private function parseDate(string $date): string
    {
        $dateTime = DateTime::createFromFormat(
            'd/M/Y:H:i:s O',
            $date
        );

        return $dateTime !== false
            ? $dateTime->format('Y-m-d H:i:s')
            : date('Y-m-d H:i:s');
    }

    /**
     * Определение архитектуры
     */
    private function detectArchitecture(string $userAgent): string
    {
        if (preg_match('/(x86_64|Win64|WOW64|amd64|x64)/i', $userAgent)) {
            return 'x64';
        }

        if (preg_match('/(i386|i686|x86)/i', $userAgent)) {
            return 'x86';
        }

        return 'unknown';
    }

    /**
     * Подготовка строки для batch insert.
     * @throws Exception
     */
    private function buildLogRow(array $data): array
    {
        $browserId = $this->getBrowserId($data['browser']);
        $osId = $this->getOperatingSystemId($data['operating_system']);
        $architectureId = $this->getArchitectureId($data['architecture']);

        return [
            $data['ip'],
            $data['requested_at'],
            $data['url'],
            $data['user_agent'],
            $osId,
            $architectureId,
            $browserId,
        ];
    }

    /**
     * @throws Exception
     */
    private function flushLogs(array $rows): void
    {
        Yii::$app->db->createCommand()
            ->batchInsert('logs', self::LOG_COLUMNS, $rows)
            ->execute();
    }

    /**
     * Browser
     */
    private function getBrowserId(string $name): int
    {
        if (isset($this->_browserCache[$name])) {
            return $this->_browserCache[$name];
        }

        return $this->_browserCache[$name] = $this->getDictionaryId('browsers', $name);
    }

    /**
     * Operating System
     */
    private function getOperatingSystemId(string $name): int
    {
        if (isset($this->_osCache[$name])) {
            return $this->_osCache[$name];
        }

        return $this->_osCache[$name] = $this->getDictionaryId('operating_systems', $name);
    }

    /**
     * Architecture
     * @throws Exception
     */
    private function getArchitectureId(string $name): int
    {
        if (isset($this->_architectureCache[$name])) {
            return $this->_architectureCache[$name];
        }

        return $this->_architectureCache[$name] = $this->getDictionaryId('architectures', $name);
    }

    /**
     * @throws Exception
     */
    private function getDictionaryId(string $tableName, string $name): int
    {
        $id = Yii::$app->db->createCommand("SELECT id FROM {$tableName} WHERE name = :name LIMIT 1", [
            ':name' => $name,
        ])->queryScalar();

        if ($id !== false) {
            return (int)$id;
        }

        Yii::$app->db->createCommand()->insert($tableName, [
            'name' => $name,
        ])->execute();

        return (int)Yii::$app->db->getLastInsertID();
    }
}
