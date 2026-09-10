<?php

declare(strict_types=1);

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

$db = new PDO('sqlite:' . dirname(__DIR__) . '/storage/velaris.sqlite');
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
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
)');

$db->exec(
    'CREATE TABLE IF NOT EXISTS vehicle_images (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        vehicle_id INTEGER NOT NULL,
        image TEXT NOT NULL,
        sort_order INTEGER DEFAULT 0,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
    )'
);

$db->exec(
    "INSERT INTO vehicle_images (vehicle_id, image, sort_order)
     SELECT v.id, v.image, 0
     FROM vehicles v
     WHERE v.image IS NOT NULL
       AND TRIM(v.image) <> ''
       AND v.image NOT LIKE '%vehicle-placeholder.png'
       AND NOT EXISTS (
           SELECT 1 FROM vehicle_images vi
           WHERE vi.vehicle_id = v.id AND vi.image = v.image
       )"
);

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function imageUrl(?string $image): string
{
    $image = trim((string) $image);

    if ($image === '' || str_contains($image, 'vehicle-placeholder.png')) {
        return '/assets/vehicle-placeholder.png';
    }

    return str_starts_with($image, '/') ? $image : '/' . $image;
}

$q = trim((string) ($_GET['q'] ?? ''));
$make = trim((string) ($_GET['make'] ?? ''));
$bodyType = trim((string) ($_GET['body_type'] ?? ''));
$fuel = trim((string) ($_GET['fuel'] ?? ''));
$sort = (string) ($_GET['sort'] ?? 'featured');

$sortMap = [
    'featured' => 'featured DESC, created_at DESC',
    'newest' => 'created_at DESC',
    'year_desc' => 'year DESC, created_at DESC',
    'mileage_asc' => 'mileage ASC, created_at DESC',
];
$orderBy = $sortMap[$sort] ?? $sortMap['featured'];

$where = ['status = "available"'];
$params = [];

if ($q !== '') {
    $where[] = '(make LIKE ? OR model LIKE ? OR variant LIKE ? OR body_type LIKE ?)';
    $term = "%{$q}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if ($make !== '') {
    $where[] = 'make = ?';
    $params[] = $make;
}

if ($bodyType !== '') {
    $where[] = 'body_type = ?';
    $params[] = $bodyType;
}

if ($fuel !== '') {
    $where[] = 'fuel = ?';
    $params[] = $fuel;
}

$whereSql = implode(' AND ', $where);

$stmt = $db->prepare("SELECT * FROM vehicles WHERE {$whereSql} ORDER BY {$orderBy}");
$stmt->execute($params);
$vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filterValues = static function (PDO $db, string $column): array {
    $allowed = ['make', 'body_type', 'fuel'];
    if (!in_array($column, $allowed, true)) {
        return [];
    }

    return $db->query(
        "SELECT DISTINCT {$column} FROM vehicles
         WHERE status = 'available'
           AND {$column} IS NOT NULL
           AND TRIM({$column}) <> ''
         ORDER BY {$column} ASC"
    )->fetchAll(PDO::FETCH_COLUMN);
};

$makes = $filterValues($db, 'make');
$bodyTypes = $filterValues($db, 'body_type');
$fuels = $filterValues($db, 'fuel');

function queryString(array $overrides = []): string
{
    $values = array_merge($_GET, $overrides);
    $values = array_filter(
        $values,
        static fn($value) => $value !== null && $value !== ''
    );

    return http_build_query($values);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Explore the current Velaris Automotive collection of premium and exclusive vehicles.">
    <title>Collection — Velaris Automotive</title>
    <link rel="icon" type="image/png" href="/brand/icon-site.png">
    <link rel="apple-touch-icon" href="/brand/icon-site.png">
    <link rel="stylesheet" href="/styles.css">
    <style>
        .collection-page{background:var(--black);min-height:100vh}
        .collection-hero{padding:155px 5.25vw 82px;border-bottom:1px solid var(--line);background:radial-gradient(circle at 78% 15%,#2a160b 0,transparent 34%),linear-gradient(180deg,#111 0,#080808 100%)}
        .collection-hero-inner{max-width:1250px;margin:0 auto;display:grid;grid-template-columns:1.2fr .8fr;gap:70px;align-items:end}
        .collection-hero h1{font-size:clamp(44px,6vw,78px);font-weight:500;letter-spacing:-3px;line-height:.98;margin:0}
        .collection-hero h1 em{font-style:normal;color:var(--orange)}
        .collection-lead{max-width:480px;color:#aaa;margin:0 0 6px;font-size:14px}
        .collection-stats{display:flex;gap:35px;margin-top:35px}
        .collection-stat strong{display:block;font-size:25px;font-weight:500;color:#fff}
        .collection-stat span{display:block;font-size:9px;text-transform:uppercase;letter-spacing:2px;color:#777;margin-top:3px}
        .collection-shell{max-width:1250px;margin:0 auto;padding:45px 0 110px}
        .collection-toolbar{background:#111;border:1px solid #292929;padding:18px;display:grid;grid-template-columns:1.7fr repeat(3,1fr) 1fr auto;gap:9px;position:sticky;top:0;z-index:4}
        .collection-toolbar input,.collection-toolbar select{width:100%;font:inherit;color:#eee;background:#080808;border:1px solid #363636;padding:13px 14px;outline:none;min-height:47px}
        .filter-button{border:0;background:var(--orange);color:#fff;font:700 12px Montserrat,Arial,sans-serif;padding:0 20px;cursor:pointer;min-height:47px}
        .collection-meta{display:flex;justify-content:space-between;align-items:center;margin:36px 0 20px}
        .collection-meta p{margin:0;color:#888;font-size:11px;letter-spacing:1.5px;text-transform:uppercase}
        .collection-meta strong{color:#eee}
        .clear-link{color:var(--orange);font-size:11px;text-decoration:none}
        .collection-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}
        .collection-card{background:var(--card);border:1px solid #242424;overflow:hidden;display:flex;flex-direction:column;min-width:0}
        .collection-image{height:270px;background:#0d0d0d center/cover no-repeat;position:relative}
        .collection-image:after{content:"";position:absolute;inset:auto 0 0;height:38%;background:linear-gradient(0deg,#111,transparent);pointer-events:none}
        .collection-badge{position:absolute;top:14px;left:14px;z-index:1;background:#0d0d0ddd;border:1px solid #555;color:#fff;padding:6px 9px;font-size:9px;letter-spacing:1.2px}
        .collection-featured{position:absolute;top:14px;right:14px;z-index:1;background:var(--orange);color:#fff;padding:6px 9px;font-size:9px;letter-spacing:1.2px}
        .collection-content{padding:22px 21px 20px;display:flex;flex:1;flex-direction:column}
        .collection-make{color:var(--muted);text-transform:uppercase;letter-spacing:2px;font-size:10px}
        .collection-content h2{font-size:21px;font-weight:600;line-height:1.2;letter-spacing:-.5px;margin:7px 0 8px}
        .collection-variant{color:#aaa;font-size:11px;min-height:18px}
        .collection-specs{display:grid;grid-template-columns:1fr 1fr;gap:7px 12px;border-top:1px solid #292929;border-bottom:1px solid #292929;padding:14px 0;margin:18px 0;color:#aaa;font-size:11px}
        .collection-specs b{color:#eee;font-weight:500}
        .collection-bottom{display:flex;justify-content:space-between;align-items:center;gap:15px;margin-top:auto}
        .collection-price-note{font-size:10px;color:#666;line-height:1.4}
        .collection-action{border:0;background:none;color:var(--orange);font:600 11px Montserrat,Arial,sans-serif;padding:0;cursor:pointer;white-space:nowrap}
        .empty-collection{border:1px solid #292929;background:#111;padding:65px 30px;text-align:center;grid-column:1/-1}
        .empty-collection h2{font-size:28px;font-weight:500;margin-bottom:10px}
        .empty-collection p{color:#888;margin:0 0 22px}
        .collection-footer{margin-top:0}
        @media(max-width:1050px){.collection-toolbar{grid-template-columns:repeat(2,1fr)}.collection-toolbar input{grid-column:span 2}.collection-grid{grid-template-columns:repeat(2,1fr)}.collection-hero-inner{grid-template-columns:1fr}}
        @media(max-width:700px){.collection-hero{padding:125px 25px 65px}.collection-hero-inner{gap:35px}.collection-hero h1{letter-spacing:-2px}.collection-shell{padding:25px 25px 80px}.collection-toolbar{position:static;grid-template-columns:1fr}.collection-toolbar input{grid-column:auto}.collection-grid{grid-template-columns:1fr}.collection-image{height:230px}.collection-meta{align-items:flex-start;gap:20px}.collection-stats{gap:25px}}
    </style>
</head>
<body>
<div class="collection-page">
    <header class="nav" style="position:absolute">
        <a href="/" class="logo">
            <img src="/brand/logo-primary.svg" alt="Velaris Automotive">
        </a>
        <nav>
            <a href="/">Home</a>
            <a class="active" href="/collection.php">Cars</a>
            <a href="/#about">About</a>
            <a href="/#process">How it works</a>
            <a href="/#services">Services</a>
            <a href="/#contact">Contact</a>
        </nav>
        <div class="nav-actions">
            <a class="icon-btn" href="#collection-filters" aria-label="Search">⌕</a>
            <button class="outline" type="button" onclick="openLead()">◉ &nbsp; Get in touch</button>
        </div>
    </header>

    <main>
        <section class="collection-hero">
            <div class="collection-hero-inner">
                <div>
                    <p class="eyebrow">THE VELARIS COLLECTION</p>
                    <h1>Cars chosen<br><em>with intent.</em></h1>
                    <div class="collection-stats">
                        <div class="collection-stat"><strong><?=count($vehicles)?></strong><span>Vehicles shown</span></div>
                        <div class="collection-stat"><strong>EU</strong><span>Dealer network</span></div>
                        <div class="collection-stat"><strong>01</strong><span>Point of contact</span></div>
                    </div>
                </div>
                <p class="collection-lead">
                    Explore our current availability. Every vehicle is presented with the key information you need to decide whether it deserves a closer look.
                </p>
            </div>
        </section>

        <section class="collection-shell" id="collection-filters">
            <form class="collection-toolbar" method="get" action="/collection.php">
                <input name="q" value="<?=e($q)?>" placeholder="Search make, model or keyword" autocomplete="off">

                <select name="make" aria-label="Make">
                    <option value="">All makes</option>
                    <?php foreach ($makes as $value): ?>
                        <option value="<?=e($value)?>" <?=$make === $value ? 'selected' : ''?>><?=e($value)?></option>
                    <?php endforeach; ?>
                </select>

                <select name="body_type" aria-label="Body type">
                    <option value="">All body types</option>
                    <?php foreach ($bodyTypes as $value): ?>
                        <option value="<?=e($value)?>" <?=$bodyType === $value ? 'selected' : ''?>><?=e($value)?></option>
                    <?php endforeach; ?>
                </select>

                <select name="fuel" aria-label="Fuel">
                    <option value="">All fuel types</option>
                    <?php foreach ($fuels as $value): ?>
                        <option value="<?=e($value)?>" <?=$fuel === $value ? 'selected' : ''?>><?=e($value)?></option>
                    <?php endforeach; ?>
                </select>

                <select name="sort" aria-label="Sort by">
                    <option value="featured" <?=$sort === 'featured' ? 'selected' : ''?>>Featured first</option>
                    <option value="newest" <?=$sort === 'newest' ? 'selected' : ''?>>Newest added</option>
                    <option value="year_desc" <?=$sort === 'year_desc' ? 'selected' : ''?>>Newest model year</option>
                    <option value="mileage_asc" <?=$sort === 'mileage_asc' ? 'selected' : ''?>>Lowest mileage</option>
                </select>

                <button class="filter-button" type="submit">Apply</button>
            </form>

            <div class="collection-meta">
                <p><strong><?=count($vehicles)?></strong> vehicles currently available</p>
                <?php if ($q !== '' || $make !== '' || $bodyType !== '' || $fuel !== '' || $sort !== 'featured'): ?>
                    <a class="clear-link" href="/collection.php">Clear filters ×</a>
                <?php endif; ?>
            </div>

            <div class="collection-grid">
                <?php if (!$vehicles): ?>
                    <div class="empty-collection">
                        <p class="eyebrow">NO MATCHES</p>
                        <h2>Nothing fits those filters.</h2>
                        <p>Try a broader search or clear the filters to see the current collection.</p>
                        <a class="button" href="/collection.php">View all vehicles →</a>
                    </div>
                <?php endif; ?>

                <?php
                    $galleryStmt = $db->prepare(
                        'SELECT image FROM vehicle_images WHERE vehicle_id = ? ORDER BY sort_order, id'
                    );
                ?>

                <?php foreach ($vehicles as $vehicle): ?>
                    <?php
                        $galleryStmt->execute([(int) $vehicle['id']]);
                        $galleryImages = $galleryStmt->fetchAll(PDO::FETCH_COLUMN);
                        if (!$galleryImages && !empty($vehicle['image'])) {
                            $galleryImages = [$vehicle['image']];
                        }
                        $galleryImages = array_values(array_map('imageUrl', $galleryImages));
                        if (!$galleryImages) {
                            $galleryImages = ['/assets/vehicle-placeholder.png'];
                        }
                        $image = $galleryImages[0];
                        $title = trim(($vehicle['model'] ?? '') . ' ' . ($vehicle['variant'] ?? ''));
                        $vehicleName = trim(($vehicle['make'] ?? '') . ' ' . $title);
                    ?>
                    <article class="collection-card">
                        <div class="collection-image gallery-enabled" id="collection-gallery-<?= (int) $vehicle['id'] ?>">
                            <img class="collection-gallery-image" src="<?=e($image)?>" alt="<?=e($vehicleName)?>" data-collection-gallery-image>
                            <span class="collection-badge">AVAILABLE</span>
                            <?php if (!empty($vehicle['featured'])): ?><span class="collection-featured">FEATURED</span><?php endif; ?>
                            <?php if (count($galleryImages) > 1): ?>
                                <button class="gallery-arrow gallery-prev" type="button" aria-label="Previous photo" onclick="changeCollectionGallery(<?= (int) $vehicle['id'] ?>,-1)">‹</button>
                                <button class="gallery-arrow gallery-next" type="button" aria-label="Next photo" onclick="changeCollectionGallery(<?= (int) $vehicle['id'] ?>,1)">›</button>
                                <div class="collection-gallery-counter" data-collection-gallery-current>1 / <?= count($galleryImages) ?></div>
                                <script type="application/json" data-collection-gallery-images><?= e(json_encode($galleryImages, JSON_UNESCAPED_SLASHES)) ?></script>
                            <?php endif; ?>
                        </div>

                        <div class="collection-content">
                            <div class="collection-make"><?=e($vehicle['make'])?></div>
                            <h2><?=e($title)?></h2>
                            <div class="collection-variant"><?=e($vehicle['body_type'] ?? '')?></div>

                            <div class="collection-specs">
                                <span>Year <b><?=e((string)($vehicle['year'] ?: '—'))?></b></span>
                                <span>Power <b><?=e((string)($vehicle['power'] ?: '—'))?> HP</b></span>
                                <span>Mileage <b><?=number_format((int)($vehicle['mileage'] ?? 0))?> km</b></span>
                                <span>Fuel <b><?=e($vehicle['fuel'] ?: '—')?></b></span>
                                <span>Gearbox <b><?=e($vehicle['transmission'] ?: '—')?></b></span>
                            </div>

                            <div class="collection-bottom">
                                <span class="collection-price-note">Price &amp; availability<br>confirmed on request</span>
                                <button class="collection-action" type="button" onclick="openLead(<?= (int)$vehicle['id'] ?>, <?=json_encode('Request current price for ' . $vehicleName)?>)">Request price →</button>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <footer class="collection-footer" id="contact">
        <img src="/brand/logo-primary.svg" alt="Velaris Automotive">
        <p>Premium automotive sourcing &amp; brokerage.</p>
        <a href="mailto:hello@velarisautomotive.com">hello@velarisautomotive.com</a>
        <p class="legal">Velaris Automotive acts as an intermediary where applicable. Vehicle availability, condition and pricing are confirmed on request.</p>
    </footer>
</div>

<dialog id="lead-modal">
    <button class="close" type="button" onclick="closeLead()">×</button>
    <p class="eyebrow">VELARIS AUTOMOTIVE</p>
    <h2 id="form-title">Request current price</h2>
    <p>Tell us how we can reach you and we’ll confirm current availability and pricing.</p>
    <form id="lead-form">
        <input name="name" required placeholder="Your name">
        <input name="email" type="email" required placeholder="Email address">
        <input name="phone" placeholder="Phone number">
        <input name="whatsapp" placeholder="WhatsApp (optional)">
        <select name="contact_method">
            <option>Email</option>
            <option>Phone</option>
            <option>WhatsApp</option>
        </select>
        <textarea name="message" placeholder="Anything you would like us to know?"></textarea>
        <button class="button" type="submit">Send enquiry →</button>
        <p id="form-status"></p>
    </form>
</dialog>

<script>
const collectionGalleryIndexes = {};

function changeCollectionGallery(vehicleId, direction) {
    const gallery = document.querySelector(`#collection-gallery-${vehicleId}`);
    if (!gallery) return;

    const data = gallery.querySelector('[data-collection-gallery-images]');
    const image = gallery.querySelector('[data-collection-gallery-image]');
    const counter = gallery.querySelector('[data-collection-gallery-current]');
    if (!data || !image) return;

    let images = [];
    try { images = JSON.parse(data.textContent || '[]'); } catch { return; }
    if (images.length <= 1) return;

    if (!(vehicleId in collectionGalleryIndexes)) collectionGalleryIndexes[vehicleId] = 0;
    collectionGalleryIndexes[vehicleId] += direction;
    if (collectionGalleryIndexes[vehicleId] < 0) collectionGalleryIndexes[vehicleId] = images.length - 1;
    if (collectionGalleryIndexes[vehicleId] >= images.length) collectionGalleryIndexes[vehicleId] = 0;

    image.style.opacity = '0';
    window.setTimeout(() => {
        image.src = images[collectionGalleryIndexes[vehicleId]];
        image.style.opacity = '1';
        if (counter) counter.textContent = `${collectionGalleryIndexes[vehicleId] + 1} / ${images.length}`;
    }, 110);
}

let currentVehicle = null;

function openLead(vehicleId = null, title = 'Request current price') {
    currentVehicle = vehicleId;
    document.querySelector('#form-title').textContent = title;
    document.querySelector('#lead-modal').showModal();
}

function closeLead() {
    document.querySelector('#lead-modal').close();
}

document.querySelector('#lead-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.target;
    const data = Object.fromEntries(new FormData(form));
    data.vehicle_id = currentVehicle;
    data.source = currentVehicle ? 'collection page' : 'collection page';

    try {
        const response = await fetch('/api/leads', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });

        document.querySelector('#form-status').textContent = response.ok
            ? 'Thank you — your enquiry has been received.'
            : 'Please check your name and email address.';

        if (response.ok) form.reset();
    } catch (error) {
        console.error(error);
        document.querySelector('#form-status').textContent = 'Something went wrong. Please try again.';
    }
});
</script>
</body>
</html>
