<?php
declare(strict_types=1);

final class SchemaUpdateUnavailable extends RuntimeException {}

// Append new versions; never edit or reorder an already released migration.
// Every step must be safe to retry: MySQL DDL cannot be rolled back as a unit.
function schema_migrations(): array {
    return [
        1 => ['name'=>'Otomatik veritabanı güncellemeleri', 'run'=>static function(PDO $pdo): void {
            if(!cms_ready()) cms_migrate();
            elseif(!growth_ready()) growth_migrate();
        }],
    ];
}

function schema_history(PDO $pdo): array {
    try { return $pdo->query('SELECT version, name, applied_at FROM schema_migrations ORDER BY version')->fetchAll(PDO::FETCH_ASSOC); }
    catch(PDOException $error) {
        // Only a missing history table is expected on the first upgrade.
        if($error->getCode()==='42S02'||($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'&&str_contains($error->getMessage(),'no such table: schema_migrations'))) return [];
        throw $error;
    }
}

function schema_pending(PDO $pdo,array $migrations): array {
    $done=array_column(schema_history($pdo),'version');
    return array_filter($migrations,static fn($version)=>!in_array($version,$done),ARRAY_FILTER_USE_KEY);
}

function schema_apply(PDO $pdo,array $migrations,string $lockPath): void {
    if(!$migrations||!schema_pending($pdo,$migrations)) return;
    if($pdo->inTransaction()) throw new SchemaUpdateUnavailable('Güncelleme açık işlem içinde başlatılamaz.');
    ksort($migrations,SORT_NUMERIC);
    $lock=fopen($lockPath,'c+');
    if(!$lock) throw new SchemaUpdateUnavailable('Güncelleme kilidi açılamadı.');
    $mysql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';$databaseLock=false;$name='';
    try {
        if(!flock($lock,LOCK_EX|LOCK_NB)) throw new SchemaUpdateUnavailable('Başka bir güncelleme devam ediyor.');
        // The DB lock also coordinates separate deployments sharing one MySQL database.
        if($mysql){
            $name='arkesoft-schema-'.substr(hash('sha256',(string)$pdo->query('SELECT DATABASE()')->fetchColumn()),0,40);
            $stmt=$pdo->prepare('SELECT GET_LOCK(?, 0)');$stmt->execute([$name]);
            if((int)$stmt->fetchColumn()!==1) throw new SchemaUpdateUnavailable('Başka bir güncelleme devam ediyor.');
            $databaseLock=true;
        }
        $pending=schema_pending($pdo,$migrations);
        if(!$pending) return;
        // Back off after failure; do not retry expensive DDL on every visitor request.
        $failedUntil=(int)stream_get_contents($lock);
        if($failedUntil>time()) throw new SchemaUpdateUnavailable('Güncelleme yeniden denemeyi bekliyor.');
        $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version INTEGER PRIMARY KEY, name VARCHAR(190) NOT NULL, applied_at VARCHAR(30) NOT NULL)'.($mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4':''));
        foreach($pending as $version=>$migration){
            try {
                if(!$mysql) $pdo->beginTransaction();
                $migration['run']($pdo);
                $stmt=$pdo->prepare('INSERT INTO schema_migrations (version,name,applied_at) VALUES (?,?,?)');
                $stmt->execute([$version,$migration['name'],gmdate('c')]);
                if(!$mysql) $pdo->commit();
            } catch(Throwable $error) {
                if($pdo->inTransaction()) $pdo->rollBack();
                rewind($lock);ftruncate($lock,0);fwrite($lock,(string)(time()+60));fflush($lock);
                error_log('Veritabanı güncellemesi başarısız: sürüm '.(int)$version.'; '.$error->getMessage());
                throw new SchemaUpdateUnavailable('Veritabanı güncellemesi tamamlanamadı.',0,$error);
            }
        }
        rewind($lock);ftruncate($lock,0);
        unset($GLOBALS['settingsCache']);
    } finally {
        if($databaseLock){try{$stmt=$pdo->prepare('SELECT RELEASE_LOCK(?)');$stmt->execute([$name]);}catch(Throwable $ignored){}}
        flock($lock,LOCK_UN);fclose($lock);
    }
}

function schema_update_if_needed(): void {
    if(!installed()) return;
    $pdo=db();
    $identity=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?(string)$pdo->query('SELECT DATABASE()')->fetchColumn():($GLOBALS['config']['test_sqlite']??'local');
    schema_apply($pdo,schema_migrations(),ROOT.'/storage/schema-'.substr(hash('sha256',$identity),0,24).'.lock');
}
