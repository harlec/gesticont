<?php
class Model {
    protected static ?PDO $pdo = null;
    protected string $table = '';
    protected string $pk = 'id';
    public static function db(): PDO {
        if (static::$pdo === null) {
            $cfg = require ROOT . '/config/database.php';
            $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['name']};charset={$cfg['charset']}";
            static::$pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], $cfg['options']);
        }
        return static::$pdo;
    }
    public function findAll(array $where = [], string $order = 'id DESC', int $limit = 0): array {
        $sql = "SELECT * FROM {$this->table}"; $params = [];
        if (!empty($where)) { $conditions = array_map(fn($k) => "$k = :$k", array_keys($where)); $sql .= ' WHERE ' . implode(' AND ', $conditions); $params = $where; }
        $sql .= " ORDER BY $order";
        if ($limit > 0) $sql .= " LIMIT $limit";
        $stmt = static::db()->prepare($sql); $stmt->execute($params); return $stmt->fetchAll();
    }
    public function findById(int $id): ?array { $stmt = static::db()->prepare("SELECT * FROM {$this->table} WHERE {$this->pk} = ? LIMIT 1"); $stmt->execute([$id]); return $stmt->fetch() ?: null; }
    public function findOne(array $where): ?array { $conditions = array_map(fn($k) => "$k = :$k", array_keys($where)); $sql = "SELECT * FROM {$this->table} WHERE " . implode(' AND ', $conditions) . " LIMIT 1"; $stmt = static::db()->prepare($sql); $stmt->execute($where); return $stmt->fetch() ?: null; }
    public function insert(array $data): int { $cols = implode(', ', array_keys($data)); $places = implode(', ', array_map(fn($k) => ":$k", array_keys($data))); $stmt = static::db()->prepare("INSERT INTO {$this->table} ($cols) VALUES ($places)"); $stmt->execute($data); return (int) static::db()->lastInsertId(); }
    public function update(int $id, array $data): bool { $sets = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($data))); $data[$this->pk] = $id; $stmt = static::db()->prepare("UPDATE {$this->table} SET $sets WHERE {$this->pk} = :{$this->pk}"); return $stmt->execute($data); }
    public function query(string $sql, array $params = []): array { $stmt = static::db()->prepare($sql); $stmt->execute($params); return $stmt->fetchAll(); }
    public function queryOne(string $sql, array $params = []): ?array { $stmt = static::db()->prepare($sql); $stmt->execute($params); return $stmt->fetch() ?: null; }
    public function execute(string $sql, array $params = []): bool { return static::db()->prepare($sql)->execute($params); }
    public function count(array $where = []): int { $sql = "SELECT COUNT(*) FROM {$this->table}"; $params = []; if (!empty($where)) { $conditions = array_map(fn($k) => "$k = :$k", array_keys($where)); $sql .= ' WHERE ' . implode(' AND ', $conditions); $params = $where; } $stmt = static::db()->prepare($sql); $stmt->execute($params); return (int) $stmt->fetchColumn(); }
    public static function beginTransaction(): void { static::db()->beginTransaction(); }
    public static function commit(): void { static::db()->commit(); }
    public static function rollback(): void { static::db()->rollBack(); }
}
