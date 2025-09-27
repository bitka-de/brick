<?php
declare(strict_types=1);

namespace Core;

use JsonSerializable;
use ArrayAccess;
use DateTimeImmutable;
use Core\QueryBuilder;

/**
 * Model – Smart Active Record mit Relations, Scopes & Events
 *
 * - Relations: hasOne, hasMany, belongsTo, belongsToMany (basic pivot)
 * - Global Scopes + Soft Deletes
 * - Events: creating/created, updating/updated, saving/saved, deleting/deleted (return false to abort)
 * - Attribute-Mutators: getFooAttribute()/setFooAttribute($value)
 * - Casting, Hidden, Appends, Dirty-Tracking
 * - Strict Fill (optional) für harte Mass-Assignment-Regeln
 */
abstract class Model implements JsonSerializable, ArrayAccess
{
    protected Database $db;

    /** Tabelle & PK */
    protected static string $table;
    protected static string $primaryKey = 'id';

    /** Mass Assignment */
    protected array $fillable = [];
    protected array $guarded  = [];
    protected bool $strictFill = false; // wirft bei unerlaubten Feldern eine Exception

    /** Attribute */
    protected array $attributes = [];
    protected array $original   = [];

    /** Caches */
    protected array $relationCache = [];

    /** Casting / Sichtbarkeit */
    protected array $casts   = [];
    protected array $hidden  = [];
    protected array $appends = [];

    /** Timestamps/SoftDeletes */
    protected bool $timestamps = true;
    protected const CREATED_AT = 'created_at';
    protected const UPDATED_AT = 'updated_at';

    protected bool $softDeletes = false;
    protected const DELETED_AT  = 'deleted_at';

    /** Existenz-Flag */
    protected bool $exists = false;

    /** Boot-System */
    protected static array $booted = [];
    protected static array $modelEvents = [];   // [class => [event => [callables]]]
    protected static array $globalScopes = [];  // [class => [name => callable]]

    public function __construct(Database $db, array $attributes = [], bool $exists = false)
    {
        if (!isset(static::$table)) {
            throw new \LogicException(static::class.' missing static $table.');
        }
        $this->db = $db;

        static::bootIfNotBooted();

        $this->fill($attributes, force: true);
        $this->exists   = $exists;
        $this->original = $this->attributes;
    }

    /* ===========================
       Boot & Scopes & Events
       =========================== */

    protected static function bootIfNotBooted(): void
    {
        $class = static::class;
        if (isset(static::$booted[$class])) return;

        static::$booted[$class] = true;
        static::boot();

        if (static::hasSoftDeletes()) {
            static::addGlobalScope('soft_deletes', function(QueryBuilder $q): void {
                $q->whereNull(static::DELETED_AT);
            });
        }
    }

    protected static function boot(): void {}

    public static function addGlobalScope(string $name, callable $callback): void
    {
        static::$globalScopes[static::class][$name] = $callback;
    }

    protected static function applyGlobalScopes(QueryBuilder $q): void
    {
        foreach (static::$globalScopes[static::class] ?? [] as $scope) {
            $scope($q);
        }
    }

    // Events API
    public static function creating(callable $cb): void { static::registerEvent('creating', $cb); }
    public static function created(callable $cb): void  { static::registerEvent('created', $cb); }
    public static function updating(callable $cb): void { static::registerEvent('updating', $cb); }
    public static function updated(callable $cb): void  { static::registerEvent('updated', $cb); }
    public static function saving(callable $cb): void   { static::registerEvent('saving', $cb); }
    public static function saved(callable $cb): void    { static::registerEvent('saved', $cb); }
    public static function deleting(callable $cb): void { static::registerEvent('deleting', $cb); }
    public static function deleted(callable $cb): void  { static::registerEvent('deleted', $cb); }

    protected static function registerEvent(string $event, callable $callback): void
    {
        static::$modelEvents[static::class][$event][] = $callback;
    }

    /** Gibt false zurück, wenn ein Listener abbricht */
    protected function fireEvent(string $event): bool
    {
        foreach (static::$modelEvents[static::class][$event] ?? [] as $cb) {
            if ($cb($this) === false) return false;
        }
        return true;
    }

    /* ===========================
       Query Entrypoints
       =========================== */

    public static function query(Database $db): QueryBuilder
    {
        $q = $db->table(static::$table);
        static::applyGlobalScopes($q);
        return $q;
    }

    /** Soft-Delete-Brecheisen: keine Global Scopes */
    public static function withTrashed(Database $db): QueryBuilder
    {
        return $db->table(static::$table);
    }

    /** Nur gelöschte Datensätze */
    public static function onlyTrashed(Database $db): QueryBuilder
    {
        return $db->table(static::$table)->where(static::DELETED_AT, '!=', null);
    }

    public static function find(Database $db, int|string $id): ?static
    {
        $row = static::query($db)->where(static::$primaryKey, $id)->first();
        return $row ? new static($db, $row, true) : null;
    }

    public static function findWithTrashed(Database $db, int|string $id): ?static
    {
        $row = static::withTrashed($db)->where(static::$primaryKey, $id)->first();
        return $row ? new static($db, $row, true) : null;
    }

    public static function create(Database $db, array $data): static
    {
        $m = new static($db);
        $m->fill($data);
        $m->touchTimestamps(onCreate: true);

        $qb = static::query($db);
        $insertData = $m->attributesForInsert();

        // insertGetId() bevorzugt, Fallback: insert() -> lastInsertId()
        $id = method_exists($qb, 'insertGetId')
            ? $qb->insertGetId($insertData)
            : (static function() use ($qb, $db, $insertData) {
                $qb->insert($insertData);
                return $db->connection()->lastInsertId();
            })();

        $pk = static::$primaryKey;
        $m->attributes[$pk] = is_numeric($id) ? (int)$id : $id;
        $m->exists = true;
        $m->original = $m->attributes;
        return $m;
    }

    public static function all(Database $db): array
    {
        $rows = static::query($db)->get();
        return array_map(fn($row) => new static($db, $row, true), $rows);
    }

    public static function make(Database $db, array $attributes = []): static
    {
        return new static($db, $attributes, false);
    }

    /* ===============
       Instance API
       =============== */

    public function save(): void
    {
        $this->assertFillRules();

        $isCreating = !$this->exists;
        if (!$this->fireEvent($isCreating ? 'creating' : 'updating')) return;
        if (!$this->fireEvent('saving')) return;

        if ($this->timestamps) {
            $this->touchTimestamps(onCreate: $isCreating);
        }

        $qb = static::query($this->db);
        $pk = static::$primaryKey;

        if ($this->exists) {
            $changes = $this->dirtyAttributes();
            if ($changes !== []) {
                $qb->where($pk, $this->attributes[$pk])->update($changes);
                $this->original = $this->attributes;
            }
        } else {
            $insertData = $this->attributesForInsert();
            $id = method_exists($qb, 'insertGetId')
                ? $qb->insertGetId($insertData)
                : (static function() use ($qb, $insertData, $pk) {
                    $qb->insert($insertData);
                    return $insertData[$pk] ?? null;
                })();
            if ($id !== null) {
                $this->attributes[$pk] = is_numeric($id) ? (int)$id : $id;
            }
            $this->exists   = true;
            $this->original = $this->attributes;
        }

        $this->fireEvent($isCreating ? 'created' : 'updated');
        $this->fireEvent('saved');
    }

    public function delete(): void
    {
        if (!$this->exists) return;
        if (!$this->fireEvent('deleting')) return;

        $pk = static::$primaryKey;

        if ($this->softDeletes) {
            $this->attributes[self::DELETED_AT] = $this->now();
            static::withTrashed($this->db)
                ->where($pk, $this->attributes[$pk])
                ->update([self::DELETED_AT => $this->attributes[self::DELETED_AT]]);
            $this->original[self::DELETED_AT] = $this->attributes[self::DELETED_AT];
        } else {
            static::withTrashed($this->db)
                ->where($pk, $this->attributes[$pk])
                ->delete();
            $this->exists = false;
        }

        $this->fireEvent('deleted');
    }

    public function restore(): void
    {
        if (!$this->softDeletes || !$this->exists) return;
        $pk = static::$primaryKey;
        $this->attributes[self::DELETED_AT] = null;
        static::withTrashed($this->db)
            ->where($pk, $this->attributes[$pk])
            ->update([self::DELETED_AT => null]);
        $this->original[self::DELETED_AT] = null;
    }

    public function refresh(): void
    {
        if (!$this->exists) return;
        $pk = static::$primaryKey;
        $fresh = static::withTrashed($this->db)->where($pk, $this->attributes[$pk])->first();
        if ($fresh) {
            $this->attributes = $fresh;
            $this->original   = $fresh;
            $this->relationCache = [];
        }
    }

    public function replicate(array $overrides = []): static
    {
        $copy = new static($this->db, $this->toArray(), false);
        unset($copy->attributes[static::$primaryKey]);
        foreach ($overrides as $k => $v) $copy->setAttribute($k, $v);
        return $copy;
    }

    /* =======================
       Attribute Management
       ======================= */

    public function fill(array $data, bool $force = false): void
    {
        foreach ($data as $key => $value) {
            if (!$force && !$this->isFillable($key)) {
                if ($this->strictFill) {
                    throw new \InvalidArgumentException("Attribute '$key' is not fillable on ".static::class);
                }
                continue;
            }
            $this->setAttribute($key, $value);
        }
    }

    public function isDirty(?string $key = null): bool
    {
        return $key
            ? (($this->attributes[$key] ?? null) !== ($this->original[$key] ?? null))
            : $this->dirtyAttributes() !== [];
    }

    public function getAttribute(string $key): mixed
    {
        // Accessor
        $accessor = 'get'.str_replace('_', '', ucwords($key, '_')).'Attribute';
        if (method_exists($this, $accessor)) {
            return $this->$accessor();
        }

        // Relation Auto-Loader: wenn es eine parameterlose Methode gleichen Namens gibt
        if (method_exists($this, $key)) {
            // gecacht?
            if (array_key_exists($key, $this->relationCache)) {
                return $this->relationCache[$key];
            }
            $rel = $this->$key(); // z.B. posts(), profile()
            return $this->relationCache[$key] = $rel;
        }

        $value = $this->attributes[$key] ?? null;
        return $this->castFromStorage($key, $value);
    }

    public function setAttribute(string $key, mixed $value): void
    {
        // Mutator
        $mutator = 'set'.str_replace('_', '', ucwords($key, '_')).'Attribute';
        if (method_exists($this, $mutator)) {
            $this->$mutator($value);
            return;
        }
        $this->attributes[$key] = $this->castForStorage($key, $value);
    }

    public function clearRelationCache(?string $name = null): void
    {
        if ($name === null) { $this->relationCache = []; return; }
        unset($this->relationCache[$name]);
    }

    public function toArray(): array
    {
        $array = [];

        // normale Attribute
        foreach ($this->attributes as $key => $value) {
            if (in_array($key, $this->hidden, true)) continue;
            $array[$key] = $this->getAttribute($key);
        }

        // Appends
        foreach ($this->appends as $key) {
            if (!in_array($key, $this->hidden, true)) {
                $array[$key] = $this->getAttribute($key);
            }
        }

        // Geladene Relationen
        foreach ($this->relationCache as $key => $relation) {
            if (in_array($key, $this->hidden, true)) continue;
            $array[$key] = $relation instanceof self
                ? $relation->toArray()
                : (is_array($relation)
                    ? array_map(fn($r) => $r instanceof self ? $r->toArray() : $r, $relation)
                    : $relation);
        }

        return $array;
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /* ============
       Magic & ArrayAccess
       ============ */

    public function __get(string $name): mixed     { return $this->getAttribute($name); }
    public function __set(string $name, mixed $v): void { $this->setAttribute($name, $v); }
    public function __isset(string $name): bool    { return isset($this->attributes[$name]) || isset($this->relationCache[$name]); }

    public function offsetExists(mixed $o): bool   { return $this->__isset((string)$o); }
    public function offsetGet(mixed $o): mixed     { return $this->__get((string)$o); }
    public function offsetSet(mixed $o, mixed $v): void { $this->__set((string)$o, $v); }
    public function offsetUnset(mixed $o): void    { unset($this->attributes[(string)$o], $this->relationCache[(string)$o]); }

    /* =================
       Relations
       ================= */

    protected function relationCacheKey(string $method): string
    {
        // schneller als debug_backtrace in Hotpaths, wir geben es direkt aus Call-Site mit
        return $method;
    }

    /** one-to-many */
    protected function hasMany(string $related, string $foreignKey, ?string $localKey = null, ?string $as = null): array
    {
        $localKey   = $localKey ?? static::$primaryKey;
        $localValue = $this->getAttribute($localKey);
        if ($localValue === null) return [];

        $key = $this->relationCacheKey($as ?? (new \ReflectionMethod($this, __FUNCTION__))->getName());
        if (isset($this->relationCache[$key])) return $this->relationCache[$key];

        $rows = (new $related($this->db))::query($this->db)
            ->where($foreignKey, $localValue)
            ->get();

        return $this->relationCache[$key] = array_map(fn($r) => new $related($this->db, $r, true), $rows);
    }

    /** one-to-one */
    protected function hasOne(string $related, string $foreignKey, ?string $localKey = null, ?string $as = null): ?self
    {
        $localKey   = $localKey ?? static::$primaryKey;
        $localValue = $this->getAttribute($localKey);
        if ($localValue === null) return null;

        $key = $this->relationCacheKey($as ?? (new \ReflectionMethod($this, __FUNCTION__))->getName());
        if (isset($this->relationCache[$key])) return $this->relationCache[$key];

        $row = (new $related($this->db))::query($this->db)
            ->where($foreignKey, $localValue)
            ->first();

        return $this->relationCache[$key] = ($row ? new $related($this->db, $row, true) : null);
    }

    /** inverse one: belongs-to */
    protected function belongsTo(string $related, string $foreignKey, ?string $ownerKey = null, ?string $as = null): ?self
    {
        $ownerKey     = $ownerKey ?? static::$primaryKey;
        $foreignValue = $this->getAttribute($foreignKey);
        if ($foreignValue === null) return null;

        $key = $this->relationCacheKey($as ?? (new \ReflectionMethod($this, __FUNCTION__))->getName());
        if (isset($this->relationCache[$key])) return $this->relationCache[$key];

        $row = (new $related($this->db))::query($this->db)
            ->where($ownerKey, $foreignValue)
            ->first();

        return $this->relationCache[$key] = ($row ? new $related($this->db, $row, true) : null);
    }

    /** many-to-many (einfach): pivotTable(fk_this, fk_related) */
    protected function belongsToMany(
        string $related,
        string $pivotTable,
        string $thisKeyOnPivot,
        string $relatedKeyOnPivot,
        ?string $localKey = null,
        ?string $as = null
    ): array {
        $localKey   = $localKey ?? static::$primaryKey;
        $localValue = $this->getAttribute($localKey);
        if ($localValue === null) return [];

        $key = $this->relationCacheKey($as ?? (new \ReflectionMethod($this, __FUNCTION__))->getName());
        if (isset($this->relationCache[$key])) return $this->relationCache[$key];

        // 1) Pivot-IDs holen
        $pivotRows = $this->db->table($pivotTable)
            ->where($thisKeyOnPivot, $localValue)
            ->get();
        $ids = array_column($pivotRows, $relatedKeyOnPivot);

        if (empty($ids)) return $this->relationCache[$key] = [];

        // 2) Related Rows holen
        $rows = (new $related($this->db))::query($this->db)
            ->whereIn(static::$primaryKey, $ids)
            ->get();

        return $this->relationCache[$key] = array_map(fn($r) => new $related($this->db, $r, true), $rows);
    }

    /* =================
       Internals
       ================= */

    protected function attributesForInsert(): array
    {
        $data = $this->attributes;
        $pk = static::$primaryKey;
        if (!isset($data[$pk])) unset($data[$pk]);
        return $data;
    }

    protected function dirtyAttributes(): array
    {
        $dirty = [];
        foreach ($this->attributes as $k => $v) {
            if (($this->original[$k] ?? null) !== $v) $dirty[$k] = $v;
        }
        return $dirty;
    }

    protected function isFillable(string $key): bool
    {
        if ($this->fillable === [] && $this->guarded === []) return true;
        if (in_array('*', $this->guarded, true)) return in_array($key, $this->fillable, true);
        if (in_array($key, $this->guarded, true)) return false;
        return $this->fillable === [] ? true : in_array($key, $this->fillable, true);
    }

    protected function assertFillRules(): void
    {
        if ($this->fillable === [] && in_array('*', $this->guarded, true)) {
            throw new \LogicException(static::class.' has guarded="*" but no fillable fields.');
        }
    }

    protected function touchTimestamps(bool $onCreate): void
    {
        if (!$this->timestamps) return;
        $now = $this->now();
        if ($onCreate && !isset($this->attributes[self::CREATED_AT])) {
            $this->attributes[self::CREATED_AT] = $now;
        }
        $this->attributes[self::UPDATED_AT] = $now;
    }

    protected function now(): string
    {
        return (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    }

    protected function castFromStorage(string $key, mixed $value): mixed
    {
        $type = $this->casts[$key] ?? null;
        if ($value === null || $type === null) return $value;

        return match ($type) {
            'int'      => (int)$value,
            'float'    => (float)$value,
            'bool'     => (bool)$value,
            'string'   => (string)$value,
            'json'     => is_string($value) ? json_decode($value, true, flags: JSON_THROW_ON_ERROR) : $value,
            'datetime' => is_string($value) ? new DateTimeImmutable($value) : $value,
            default    => $value,
        };
    }

    protected function castForStorage(string $key, mixed $value): mixed
    {
        $type = $this->casts[$key] ?? null;
        if ($value === null || $type === null) return $value;

        return match ($type) {
            'int'      => is_numeric($value) ? (int)$value : null,
            'float'    => is_numeric($value) ? (float)$value : null,
            'bool'     => (bool)$value,
            'string'   => (string)$value,
            'json'     => is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'datetime' => $value instanceof DateTimeImmutable ? $value->format('Y-m-d H:i:s') : (string)$value,
            default    => $value,
        };
    }

    protected static function hasSoftDeletes(): bool
    {
        // liest Default-Eigenschaften (statisch übersteuerbar)
        return (new \ReflectionClass(static::class))->getDefaultProperties()['softDeletes'] ?? false;
    }

    /**
     * Statische dynamische Aufrufe bewusst deaktiviert – wir brauchen DB-Injektion.
     */
    public static function __callStatic(string $method, array $args): mixed
    {
        throw new \LogicException('Static model calls require Database injection. Use: ' . static::class . '::query($db)->'.$method.'()');
    }
}
