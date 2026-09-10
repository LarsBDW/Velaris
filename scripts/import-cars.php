<?php
declare(strict_types=1);

/*
 * Velaris bulk vehicle importer
 * Run from project root:
 *   php scripts/import-cars.php
 *
 * Reads:
 *   scripts/cars.json
 *
 * Idempotent: records use a source_key built from make/model/year/mileage.
 */

$projectRoot = dirname(__DIR__);
$dbPath = $projectRoot . '/storage/velaris.sqlite';
$jsonPath = __DIR__ . '/cars.json';

if (!file_exists($jsonPath)) {
    exit("ERROR: scripts/cars.json not found.\n");
}

if (!is_dir(dirname($dbPath))) {
    mkdir(dirname($dbPath), 0755, true);
}

$db = new PDO('sqlite:' . $dbPath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$db->exec('CREATE TABLE IF NOT EXISTS vehicles (
    id INTEGER PRIMARY KEY,
    make TEXT NOT NULL,
    model TEXT NOT NULL,
    variant TEXT,
    year INTEGER,
    mileage INTEGER,
    fuel TEXT,
    transmission TEXT,
    power INTEGER,
    body_type TEXT,
    status TEXT DEFAULT "available",
    image TEXT,
    description TEXT,
    featured INTEGER DEFAULT 0,
    price_cents INTEGER,
    price_type TEXT,
    source_key TEXT UNIQUE,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
)');

function ensureColumn(PDO $db, string $column, string $definition): void
{
    $columns = $db->query('PRAGMA table_info(vehicles)')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        if (($col['name'] ?? '') === $column) {
            return;
        }
    }
    $db->exec("ALTER TABLE vehicles ADD COLUMN {$column} {$definition}");
}

ensureColumn($db, 'price_cents', 'INTEGER');
ensureColumn($db, 'price_type', 'TEXT');
ensureColumn($db, 'source_key', 'TEXT');

$indexExists = $db->query("
    SELECT COUNT(*)
    FROM sqlite_master
    WHERE type='index' AND name='idx_vehicles_source_key'
")->fetchColumn();

if (!(int)$indexExists) {
    $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_vehicles_source_key ON vehicles(source_key)');
}

$data = json_decode(file_get_contents($jsonPath), true, 512, JSON_THROW_ON_ERROR);

if (!is_array($data)) {
    exit("ERROR: cars.json must contain an array.\n");
}

$find = $db->prepare('SELECT id FROM vehicles WHERE source_key = ?');

$update = $db->prepare('
    UPDATE vehicles SET
        make=?, model=?, variant=?, year=?, mileage=?,
        fuel=?, transmission=?, power=?, body_type=?,
        status=?, image=?, description=?, featured=?,
        price_cents=?, price_type=?
    WHERE source_key=?
');

$insert = $db->prepare('
    INSERT INTO vehicles (
        make, model, variant, year, mileage, fuel, transmission,
        power, body_type, status, image, description, featured,
        price_cents, price_type, source_key
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
');

$created = 0;
$updated = 0;

foreach ($data as $index => $car) {
    $make = trim((string)($car['make'] ?? ''));
    $model = trim((string)($car['model'] ?? ''));

    if ($make === '' || $model === '') {
        echo "SKIP row " . ($index + 1) . ": missing make/model\n";
        continue;
    }

    $variant = trim((string)($car['variant'] ?? ''));
    $year = !empty($car['year']) ? (int)$car['year'] : null;
    $mileage = isset($car['mileage']) ? (int)$car['mileage'] : 0;
    $fuel = trim((string)($car['fuel'] ?? 'Petrol'));
    $transmission = trim((string)($car['transmission'] ?? 'Automatic'));
    $power = !empty($car['power']) ? (int)$car['power'] : null;
    $bodyType = trim((string)($car['body_type'] ?? ''));
    $status = trim((string)($car['status'] ?? 'available'));
    $image = trim((string)($car['image'] ?? ''));
    $description = trim((string)($car['description'] ?? ''));
    $featured = !empty($car['featured']) ? 1 : 0;
    $priceCents = isset($car['price_eur']) && $car['price_eur'] !== null
        ? (int)round((float)$car['price_eur'] * 100)
        : null;
    $priceType = trim((string)($car['price_type'] ?? ''));
    $sourceKey = strtolower(preg_replace(
        '/[^a-z0-9]+/i',
        '-',
        "{$make}-{$model}-{$variant}-{$year}-{$mileage}"
    ));
    $sourceKey = trim($sourceKey, '-');

    $allowedStatuses = ['available', 'reserved', 'sold', 'hidden'];
    if (!in_array($status, $allowedStatuses, true)) {
        $status = 'available';
    }

    $find->execute([$sourceKey]);
    $existingId = $find->fetchColumn();

    if ($existingId !== false) {
        $update->execute([
            $make, $model, $variant, $year, $mileage,
            $fuel, $transmission, $power, $bodyType,
            $status, $image, $description, $featured,
            $priceCents, $priceType, $sourceKey
        ]);
        $updated++;
        echo "Updated: {$make} {$model} {$year} / {$mileage} km\n";
    } else {
        $insert->execute([
            $make, $model, $variant, $year, $mileage,
            $fuel, $transmission, $power, $bodyType,
            $status, $image, $description, $featured,
            $priceCents, $priceType, $sourceKey
        ]);
        $created++;
        echo "Added: {$make} {$model} {$year} / {$mileage} km\n";
    }
}

echo "\n=============================\n";
echo "Velaris import complete\n";
echo "Created: {$created}\n";
echo "Updated: {$updated}\n";
echo "Total source records: " . count($data) . "\n";
echo "=============================\n";
