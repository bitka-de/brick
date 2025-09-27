<?php
/**
 * Brick Database – Enterprise Database Layer mit Query Builder (Smarter Edition)
 *
 * - Multiple Named Connections (read/write, tenants)
 * - Resiliente Verbindungen (Retry + Exponential Backoff)
 * - Verschachtelte Transaktionen via Savepoints
 * - Fluent Query Builder (whereIn, orWhere, groupBy, having, paginate, upsert)
 * - Sichere Identifier-Quotes pro Treiber (MySQL/SQLite/PG)
 * - Query-Logging (konfigurierbarer Slow-Query-Threshold, optional PSR-3)
 * - Raw queries mit Bindings + präziser Param-Namensvergabe
 */

declare(strict_types=1);

namespace Core;

use PDO;
use PDOException;
use PDOStatement;

// PSR-3 Logger Interface (optional dependency)
if (interface_exists('Psr\Log\LoggerInterface')) {
    // PSR-3 Logger verfügbar - verwende Original
} else {
    // Fallback Logger Interface wenn PSR-3 nicht installiert
    interface LoggerInterface {
        public function emergency(string|\Stringable $message, array $context = []): void;
        public function alert(string|\Stringable $message, array $context = []): void;
        public function critical(string|\Stringable $message, array $context = []): void;
        public function error(string|\Stringable $message, array $context = []): void;
        public function warning(string|\Stringable $message, array $context = []): void;
        public function notice(string|\Stringable $message, array $context = []): void;
        public function info(string|\Stringable $message, array $context = []): void;
        public function debug(string|\Stringable $message, array $context = []): void;
        public function log(mixed $level, string|\Stringable $message, array $context = []): void;
    }
}

final class Database
{
    /** @var array<string,PDO> */
    private static array $connectionPool = [];

    /** @var array<string,array> */
    private static array $connectionConfigs = [];

    /** @var array<int,array{sql:string,params:array,time:float,ts:float,conn:string}> */
    private static array $queryLog = [];

    private static bool $loggingEnabled = false;
    /** @var LoggerInterface|\Psr\Log\LoggerInterface|null */
    private static $psrLogger = null;
    private static float $slowQuerySeconds = 0.100;

    private static int $maxRetries = 3;
    private static string $defaultConnection = 'default';

    /** @var array<string,int> Nested-Transaktionslevel je Connection */
    private static array $txDepth = [];

    /** PUBLIC API *************************************************************/

    public static function connection(string $name = 'default'): PDO
    {
        if (!isset(self::$connectionPool[$name])) {
            self::$connectionPool[$name] = self::createConnection($name);
        }
        if (!self::isConnectionAlive(self::$connectionPool[$name])) {
            self::$connectionPool[$name] = self::createConnection($name);
        }
        return self::$connectionPool[$name];
    }

    /** @deprecated */
    public static function pdo(): PDO { return self::connection(self::$defaultConnection); }

    public static function setDefaultConnection(string $name): void
    {
        self::$defaultConnection = $name;
    }

    public static function addConnection(string $name, array $config): void
    {
        self::$connectionConfigs[$name] = $config;
    }

    /**
     * @param LoggerInterface|\Psr\Log\LoggerInterface|null $logger
     */
    public static function setLogger($logger): void
    {
        self::$psrLogger = $logger;
    }

    public static function enableQueryLog(bool $enabled = true, ?float $slowSeconds = null): void
    {
        self::$loggingEnabled = $enabled;
        if ($slowSeconds !== null) self::$slowQuerySeconds = max(0.0, $slowSeconds);
        if (!$enabled) self::$queryLog = [];
    }

    public static function getQueryLog(): array { return self::$queryLog; }
    public static function getQueryCount(): int { return count(self::$queryLog); }

    public static function table(string $table, ?string $connection = null): QueryBuilder
    {
        $pdo = $connection ? self::connection($connection) : self::connection();
        return new QueryBuilder($pdo, $table, self::driverOf($pdo));
    }

    public static function query(string $sql, array $params = [], ?string $connection = null): PDOStatement
    {
        $pdo = $connection ? self::connection($connection) : self::connection();

        $start = microtime(true);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $t = microtime(true) - $start;

        self::maybeLog($sql, $params, $t, self::nameOf($pdo));
        return $stmt;
    }

    /** TRANSAKTIONEN **********************************************************/

    /**
     * Verschachtelte Transaktionen mit Savepoints.
     * transaction(fn(QueryRunner $db)) -> mixed
     */
    public static function transaction(callable $callback, ?string $connection = null): mixed
    {
        $pdo = $connection ? self::connection($connection) : self::connection();
        $name = self::nameOf($pdo);

        self::$txDepth[$name] = self::$txDepth[$name] ?? 0;
        if (self::$txDepth[$name] === 0) {
            $pdo->beginTransaction();
        } else {
            $savepoint = self::savepointName(self::$txDepth[$name] + 1);
            $pdo->exec("SAVEPOINT {$savepoint}");
        }
        self::$txDepth[$name]++;

        try {
            $result = $callback(new QueryRunner($pdo));
            self::$txDepth[$name]--;
            if (self::$txDepth[$name] === 0) {
                $pdo->commit();
                self::maybeLog('TRANSACTION COMMIT', [], 0.0, $name);
            } else {
                $savepoint = self::savepointName(self::$txDepth[$name] + 1);
                $pdo->exec("RELEASE SAVEPOINT {$savepoint}");
            }
            return $result;
        } catch (\Throwable $e) {
            self::$txDepth[$name]--;
            if (self::$txDepth[$name] === 0) {
                $pdo->rollBack();
                self::maybeLog('TRANSACTION ROLLBACK', [], 0.0, $name);
            } else {
                $savepoint = self::savepointName(self::$txDepth[$name] + 1);
                $pdo->exec("ROLLBACK TO SAVEPOINT {$savepoint}");
            }
            throw $e;
        }
    }

    public static function beginTransaction(?string $connection = null): void
    {
        self::transaction(static fn() => null, $connection);
    }

    public static function commit(?string $connection = null): void
    {
        // Explizite commits sind mit Depth-Handling heikel – lieber transaction() nutzen.
        // Für Kompat bleiben wir no-op, wenn es keine offene TX gibt.
        $pdo = $connection ? self::connection($connection) : self::connection();
        $name = self::nameOf($pdo);
        if (!empty(self::$txDepth[$name])) {
            // Bis zur Wurzel committen:
            while (self::$txDepth[$name] > 0) {
                self::transaction(static fn() => null, $connection);
            }
        }
    }

    public static function rollback(?string $connection = null): void
    {
        $pdo = $connection ? self::connection($connection) : self::connection();
        $name = self::nameOf($pdo);
        if (!empty(self::$txDepth[$name])) {
            $pdo->rollBack();
            self::$txDepth[$name] = 0;
            self::maybeLog('TRANSACTION ROLLBACK (force)', [], 0.0, $name);
        }
    }

    /** CONNECTION MGMT *********************************************************/

    public static function reset(): void
    {
        self::$connectionPool = [];
        self::$connectionConfigs = [];
        self::$queryLog = [];
        self::$txDepth = [];
    }

    public static function disconnect(): void
    {
        foreach (array_keys(self::$connectionPool) as $key) {
            self::$connectionPool[$key] = null; // trigger GC
            unset(self::$connectionPool[$key]);
        }
    }

    public static function getConnectionStatus(): array
    {
        $status = [];
        foreach (self::$connectionPool as $name => $pdo) {
            $status[$name] = [
                'alive' => self::isConnectionAlive($pdo),
                'driver' => self::driverOf($pdo),
            ];
        }
        return $status;
    }

    /** INTERNALS ***************************************************************/

    private static function createConnection(string $name): PDO
    {
        $config = self::getConnectionConfig($name);
        $retry = 0; $last = null;

        while ($retry <= self::$maxRetries) {
            try {
                $pdo = self::buildPdoConnection($config);
                self::configurePdoConnection($pdo, $config);
                self::maybeLog("CONNECT {$name}", [], 0.0, $name);
                return $pdo;
            } catch (PDOException $e) {
                $last = $e;
                $retry++;
                if ($retry <= self::$maxRetries) {
                    usleep((int)(100 * (2 ** ($retry - 1))) * 1000);
                }
            }
        }
        throw new \RuntimeException("DB connection failed after {$retry} attempts: " . $last?->getMessage(), (int)$last?->getCode(), $last);
    }

    private static function getConnectionConfig(string $name): array
    {
        if (isset(self::$connectionConfigs[$name])) return self::$connectionConfigs[$name];

        $file = __DIR__ . '/../config/database.php';
        if (!file_exists($file)) throw new \RuntimeException("Database config not found: {$file}");
        $cfg = require $file;

        if (isset($cfg['connections'])) {
            if (!isset($cfg['connections'][$name])) throw new \RuntimeException("Connection '{$name}' not configured");
            return $cfg['connections'][$name];
        }
        if ($name !== 'default') throw new \RuntimeException("Only 'default' connection available");
        return $cfg;
    }

    private static function buildPdoConnection(array $cfg): PDO
    {
        $cfg = self::mergeEnvironmentVariables($cfg);
        self::validateConnectionConfig($cfg);

        return match ($cfg['driver']) {
            'sqlite' => new PDO("sqlite:" . $cfg['path'], null, null, $cfg['options'] ?? self::defaultOptions()),
            'mysql'  => new PDO(
                sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
                    $cfg['host'], $cfg['port'] ?? 3306, $cfg['dbname'], $cfg['charset'] ?? 'utf8mb4'),
                $cfg['user'], $cfg['password'], $cfg['options'] ?? self::defaultOptions()
            ),
            'pgsql'  => new PDO(
                sprintf("pgsql:host=%s;port=%s;dbname=%s", $cfg['host'], $cfg['port'] ?? 5432, $cfg['dbname']),
                $cfg['user'], $cfg['password'], $cfg['options'] ?? self::defaultOptions()
            ),
            default  => throw new \RuntimeException("Unsupported DB driver: " . $cfg['driver']),
        };
    }

    private static function configurePdoConnection(PDO $pdo, array $cfg): void
    {
        $driver = self::driverOf($pdo);
        if ($driver === 'mysql') {
            $pdo->exec("SET sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
            $pdo->exec("SET time_zone = '+00:00'");
        }
    }

    private static function isConnectionAlive(PDO $pdo): bool
    {
        try { $pdo->query('SELECT 1'); return true; }
        catch (PDOException) { return false; }
    }

    private static function mergeEnvironmentVariables(array $cfg): array
    {
        $map = [
            'host' => 'DB_HOST','port'=>'DB_PORT','dbname'=>'DB_DATABASE',
            'user'=>'DB_USERNAME','password'=>'DB_PASSWORD','path'=>'DB_PATH',
        ];
        foreach ($map as $k => $env) if (isset($_ENV[$env])) $cfg[$k] = $_ENV[$env];
        return $cfg;
    }

    private static function validateConnectionConfig(array $cfg): void
    {
        if (empty($cfg['driver'])) throw new \RuntimeException("Database driver not specified");
        $req = match ($cfg['driver']) {
            'sqlite' => ['path'],
            'mysql','pgsql' => ['host','dbname','user','password'],
            default => []
        };
        foreach ($req as $k) if (empty($cfg[$k])) throw new \RuntimeException("Missing database config: {$k}");
    }

    private static function defaultOptions(): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_TIMEOUT => 30,
        ];
    }

    private static function maybeLog(string $sql, array $params, float $time, string $conn): void
    {
        if (!self::$loggingEnabled) return;

        self::$queryLog[] = ['sql'=>$sql,'params'=>$params,'time'=>$time,'ts'=>microtime(true),'conn'=>$conn];

        if ($time >= self::$slowQuerySeconds) {
            $msg = sprintf("Slow query (%.3fs) on [%s]: %s", $time, $conn, mb_substr($sql, 0, 300));
            error_log($msg);
            if (self::$psrLogger) self::$psrLogger->warning($msg, ['sql'=>$sql,'params'=>$params,'time'=>$time,'conn'=>$conn]);
        } elseif (self::$psrLogger) {
            self::$psrLogger->debug('Query', ['sql'=>$sql,'params'=>$params,'time'=>$time,'conn'=>$conn]);
        }
    }

    private static function driverOf(PDO $pdo): string { return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME); }

    /** Best effort: aktueller Verbindungsname anhand Pool */
    private static function nameOf(PDO $pdo): string
    {
        foreach (self::$connectionPool as $name => $p) if ($p === $pdo) return $name;
        return 'default';
    }

    private static function savepointName(int $depth): string { return "SP_{$depth}"; }
}

/**
 * QueryRunner – kleine Hilfskapsel für transaction() Callbacks
 */
final class QueryRunner
{
    public function __construct(private PDO $pdo) {}
    public function table(string $table): QueryBuilder { return new QueryBuilder($this->pdo, $table, $this->driver()); }
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql); $stmt->execute($params); return $stmt;
    }
    private function driver(): string { return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME); }
}

/**
 * Fluent Query Builder – mit sicheren Identifiern & nützlichen Extras.
 */
class QueryBuilder
{
    private PDO $pdo;
    private string $driver;
    private string $table;

    private array $select = ['*'];
    private array $wheres = [];      // [[type, field, op, value]|['raw',sql,bindings]|['group',callable]]
    private array $joins = [];       // [['type','table','on'=>[left,op,right]]]
    private array $orders = [];
    private array $groups = [];
    private array $havings = [];     // wie wheres, aber für HAVING

    private ?int $limit = null;
    private ?int $offset = null;
    private int $paramSeq = 0;       // eindeutige Param-Namen

    public function __construct(PDO $pdo, string $table, string $driver)
    {
        $this->pdo = $pdo;
        $this->driver = $driver;
        $this->table = $table;
    }

    /** Core API ***************************************************************/

    public function select(string ...$fields): self { if ($fields) $this->select = $fields; return $this; }

    public function where(string $field, mixed $op, mixed $val = null): self
    {
        if ($val === null) { $val = $op; $op = '='; }
        $this->wheres[] = ['basic', $field, strtoupper($op), $val, 'AND'];
        return $this;
    }

    public function orWhere(string $field, mixed $op, mixed $val = null): self
    {
        if ($val === null) { $val = $op; $op = '='; }
        $this->wheres[] = ['basic', $field, strtoupper($op), $val, 'OR'];
        return $this;
    }

    public function whereIn(string $field, array $values, bool $not = false): self
    {
        if (empty($values)) {
            // Leeres Array bedeutet "immer false" bei IN, "immer true" bei NOT IN
            return $not ? $this : $this->where('1', '=', '0');
        }
        $this->wheres[] = ['in', $field, array_values($values), $not, 'AND'];
        return $this;
    }

    public function orWhereIn(string $field, array $values, bool $not = false): self
    {
        if (empty($values)) {
            // Leeres Array bedeutet "immer false" bei IN, "immer true" bei NOT IN
            return $not ? $this : $this->orWhere('1', '=', '0');
        }
        $this->wheres[] = ['in', $field, array_values($values), $not, 'OR'];
        return $this;
    }

    public function whereNull(string $field, bool $not = false): self
    {
        $this->wheres[] = ['null', $field, $not, 'AND'];
        return $this;
    }

    public function orWhereNull(string $field, bool $not = false): self
    {
        $this->wheres[] = ['null', $field, $not, 'OR'];
        return $this;
    }

    public function whereBetween(string $field, mixed $a, mixed $b, bool $not = false): self
    {
        $this->wheres[] = ['between', $field, $a, $b, $not, 'AND'];
        return $this;
    }

    public function orWhereBetween(string $field, mixed $a, mixed $b, bool $not = false): self
    {
        $this->wheres[] = ['between', $field, $a, $b, $not, 'OR'];
        return $this;
    }

    /** Gruppierte Bedingungen: $qb->whereGroup(fn($q)=>$q->where(...)->orWhere(...)) */
    public function whereGroup(callable $builder, string $bool = 'AND'): self
    {
        $this->wheres[] = ['group', $builder, $bool];
        return $this;
    }

    public function join(string $table, string $left, string $op, string $right, string $type = 'INNER'): self
    {
        $this->joins[] = [strtoupper($type), $table, [$left, $op, $right]];
        return $this;
    }

    public function leftJoin(string $table, string $left, string $op, string $right): self
    {
        return $this->join($table, $left, $op, $right, 'LEFT');
    }

    public function groupBy(string ...$fields): self { foreach ($fields as $f) $this->groups[] = $f; return $this; }

    public function having(string $field, mixed $op, mixed $val = null, string $bool = 'AND'): self
    {
        if ($val === null) { $val = $op; $op = '='; }
        $this->havings[] = ['basic', $field, strtoupper($op), $val, $bool];
        return $this;
    }

    public function orderBy(string $field, string $direction = 'ASC'): self
    {
        $this->orders[] = [$field, strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC'];
        return $this;
    }

    public function limit(int $n): self { $this->limit = max(0, $n); return $this; }
    public function offset(int $n): self { $this->offset = max(0, $n); return $this; }

    public function get(): array
    {
        [$sql, $params] = $this->buildSelect();
        $stmt = $this->pdo->prepare($sql); $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function first(): ?array
    {
        $clone = clone $this; $clone->limit = 1;
        $rows = $clone->get(); return $rows[0] ?? null;
    }

    /** Pagination: liefert ['data','total','page','per_page','last_page'] */
    public function paginate(int $perPage, int $page = 1): array
    {
        $page = max(1, $page); $perPage = max(1, $perPage);
        $countQb = clone $this; 
        $countQb->select = ['COUNT(*) AS cnt']; 
        $countQb->orders = []; 
        $countQb->limit = $countQb->offset = null;
        [$countSql, $countParams] = $countQb->buildSelect();
        $stmt = $this->pdo->prepare($countSql);
        $stmt->execute($countParams);
        $total = (int) $stmt->fetchColumn();

        $clone = clone $this;
        $clone->limit($perPage)->offset(($page - 1) * $perPage);
        $data = $clone->get();

        $last = (int)ceil(max(1, $total) / $perPage);
        return ['data'=>$data,'total'=>$total,'page'=>$page,'per_page'=>$perPage,'last_page'=>$last];
    }

    public function insert(array $data): bool
    {
        $this->assertAssoc($data);
        [$cols, $placeholders, $params] = $this->compileInsertData($data);
        $sql = "INSERT INTO {$this->qi($this->table)} ({$cols}) VALUES ({$placeholders})";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function insertGetId(array $data): string
    {
        $this->insert($data);
        return $this->pdo->lastInsertId();
    }

    /**
     * Upsert: keys = eindeutige Spalten (z. B. ['email'])
     * data: Spaltenwerte; updateColumns: Liste der zu aktualisierenden Spalten
     */
    public function upsert(array $data, array $keys, array $updateColumns): bool
    {
        $this->assertAssoc($data);
        [$cols, $placeholders, $params] = $this->compileInsertData($data);

        $driver = $this->driver;
        if ($driver === 'mysql') {
            $updates = implode(', ', array_map(fn($c) => $this->qi($c) . " = VALUES(" . $this->qi($c) . ")", $updateColumns));
            $sql = "INSERT INTO {$this->qi($this->table)} ({$cols}) VALUES ({$placeholders}) ON DUPLICATE KEY UPDATE {$updates}";
        } elseif ($driver === 'sqlite' || $driver === 'pgsql') {
            $conflict = implode(', ', array_map(fn($c) => $this->qi($c), $keys));
            $updates = implode(', ', array_map(fn($c) => $this->qi($c) . " = EXCLUDED." . $this->qi($c), $updateColumns));
            $sql = "INSERT INTO {$this->qi($this->table)} ({$cols}) VALUES ({$placeholders}) ON CONFLICT ({$conflict}) DO UPDATE SET {$updates}";
        } else {
            throw new \RuntimeException("Upsert not supported for driver: {$driver}");
        }

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function update(array $data): int
    {
        if (!$this->wheres) throw new \RuntimeException("UPDATE without WHERE is not allowed");
        $this->assertAssoc($data);

        $sets = [];
        $params = [];
        foreach ($data as $col => $val) {
            $p = $this->param("set_{$col}");
            $sets[] = $this->qi($col) . " = :{$p}";
            $params[$p] = $val;
        }
        [$where, $wparams] = $this->buildWhere('WHERE');
        $sql = "UPDATE {$this->qi($this->table)} SET " . implode(', ', $sets) . " {$where}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params + $wparams);
        return $stmt->rowCount();
    }

    public function delete(): int
    {
        if (!$this->wheres) throw new \RuntimeException("DELETE without WHERE is not allowed");
        [$where, $wparams] = $this->buildWhere('WHERE');
        $sql = "DELETE FROM {$this->qi($this->table)} {$where}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($wparams);
        return $stmt->rowCount();
    }

    public function count(): int
    {
        $clone = clone $this;
        $clone->select = ['COUNT(*)'];
        $clone->orders = [];
        [$sql, $params] = $clone->buildSelect();
        $stmt = $this->pdo->prepare($sql); $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** Builder internals ******************************************************/

    private function buildSelect(): array
    {
        $fields = implode(', ', array_map(fn($f) => $this->selectExpr($f), $this->select));
        $sql = "SELECT {$fields} FROM " . $this->qi($this->table);
        $params = [];

        foreach ($this->joins as [$type, $table, [$l, $op, $r]]) {
            $sql .= " {$type} JOIN " . $this->qi($table) . " ON " . $this->colRef($l) . " {$op} " . $this->colRef($r);
        }

        [$where, $wparams] = $this->buildWhere('WHERE');
        $sql .= $where; $params += $wparams;

        if ($this->groups) {
            $sql .= " GROUP BY " . implode(', ', array_map(fn($f) => $this->colRef($f), $this->groups));
        }

        if ($this->havings) {
            [$having, $hparams] = $this->compileConditions($this->havings, 'HAVING');
            $sql .= $having; $params += $hparams;
        }

        if ($this->orders) {
            $order = implode(', ', array_map(fn($o) => $this->colRef($o[0]) . ' ' . $o[1], $this->orders));
            $sql .= " ORDER BY {$order}";
        }

        if ($this->limit !== null) $sql .= " LIMIT {$this->limit}";
        if ($this->offset !== null) $sql .= " OFFSET {$this->offset}";

        return [$sql, $params];
    }

    private function buildWhere(string $label): array
    {
        if (!$this->wheres) return ['', []];
        return $this->compileConditions($this->wheres, $label);
    }

    private function compileConditions(array $conds, string $label): array
    {
        $sqlParts = []; $params = [];
        foreach ($conds as $c) {
            if ($c[0] === 'basic') {
                [, $field, $op, $val, $bool] = $c;
                $p = $this->param('w');
                $sqlParts[] = [$bool, $this->colRef($field) . " {$op} :{$p}"];
                $params[$p] = $val;
            } elseif ($c[0] === 'in') {
                [, $field, $values, $not, $bool] = $c;
                if (!$values) { $sqlParts[] = [$bool, $not ? '1=1' : '1=0']; continue; }
                $placeholders = [];
                foreach ($values as $v) {
                    $p = $this->param('in'); $placeholders[] = ":{$p}"; $params[$p] = $v;
                }
                $sqlParts[] = [$bool, $this->colRef($field) . ($not ? ' NOT IN ' : ' IN ') . '(' . implode(',', $placeholders) . ')'];
            } elseif ($c[0] === 'null') {
                [, $field, $not, $bool] = $c;
                $sqlParts[] = [$bool, $this->colRef($field) . ($not ? ' IS NOT NULL' : ' IS NULL')];
            } elseif ($c[0] === 'between') {
                [, $field, $a, $b, $not, $bool] = $c;
                $p1 = $this->param('bt'); $p2 = $this->param('bt');
                $expr = $this->colRef($field) . ($not ? ' NOT BETWEEN ' : ' BETWEEN ') . ":{$p1} AND :{$p2}";
                $sqlParts[] = [$bool, $expr]; $params[$p1] = $a; $params[$p2] = $b;
            } elseif ($c[0] === 'group') {
                [, $callback, $bool] = $c;
                $nested = new self($this->pdo, $this->table, $this->driver);
                $nested->wheres = [];
                $callback($nested);
                [$w, $p] = $nested->buildWhere($label); // liefert " WHERE ..." – wir wollen nur den Inhalt
                $w = preg_replace('/^\s*' . $label . '\s*/i', '', (string)$w);
                if ($w === '' || $w === null) continue;
                $sqlParts[] = [$bool, '(' . $w . ')'];
                $params += $p;
            }
        }
        if (!$sqlParts) return ['', []];

        $out = '';
        foreach ($sqlParts as $i => [$bool, $chunk]) {
            $prefix = $i === 0 ? $label : $bool;
            $out .= " {$prefix} {$chunk}";
        }
        return [$out, $params];
    }

    /** Helpers ****************************************************************/

    private function qi(string $identifier): string
    {
        // einfache, sichere Identifier-Quotes je Treiber (Tabelle/Spaltennamen)
        return match ($this->driver) {
            'mysql','sqlite' => '`' . str_replace('`', '``', $identifier) . '`',
            'pgsql' => '"' . str_replace('"', '""', $identifier) . '"',
            default => $identifier,
        };
    }

    /** user.id → `user`.`id` (oder "user"."id") */
    private function colRef(string $ref): string
    {
        return implode('.', array_map(fn($p) => $this->qi($p), explode('.', $ref)));
    }

    private function selectExpr(string $expr): string
    {
        // Wenn es wie eine Funktion/Alias aussieht, nicht quoten.
        if (preg_match('/\b(as)\b/i', $expr) || preg_match('/[()]/', $expr)) return $expr;
        return $this->colRef($expr);
    }

    private function param(string $prefix): string { return $prefix . '_' . (++$this->paramSeq); }

    private function assertAssoc(array $data): void
    {
        if ($data === [] || array_keys($data) === range(0, count($data)-1)) {
            throw new \InvalidArgumentException("Expected associative array of column => value");
        }
    }

    private function compileInsertData(array $data): array
    {
        $cols = []; $placeholders = []; $params = [];
        foreach ($data as $col => $val) {
            $cols[] = $this->qi($col);
            $p = $this->param('ins');
            $placeholders[] = ":{$p}";
            $params[$p] = $val;
        }
        return [implode(', ', $cols), implode(', ', $placeholders), $params];
    }
}
