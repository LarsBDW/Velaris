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

$db->exec('CREATE TABLE IF NOT EXISTS vehicle_images (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    vehicle_id INTEGER NOT NULL,
    image TEXT NOT NULL,
    sort_order INTEGER DEFAULT 0,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
)');

$db->exec("INSERT INTO vehicle_images (vehicle_id, image, sort_order)
    SELECT v.id, v.image, 0
    FROM vehicles v
    WHERE v.image IS NOT NULL
      AND TRIM(v.image) <> ''
      AND v.image NOT LIKE '%vehicle-placeholder.png'
      AND NOT EXISTS (
          SELECT 1 FROM vehicle_images vi
          WHERE vi.vehicle_id = v.id AND vi.image = v.image
      )");

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function imageUrl(?string $image): string
{
    $image = trim((string)$image);
    if ($image === '' || str_contains($image, 'vehicle-placeholder.png')) {
        return '/assets/vehicle-placeholder.png';
    }
    return str_starts_with($image, '/') ? $image : '/' . $image;
}

$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare('SELECT * FROM vehicles WHERE id = ? AND status = "available" LIMIT 1');
$stmt->execute([$id]);
$vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vehicle) {
    http_response_code(404);
    echo 'Vehicle not found.';
    exit;
}

$galleryStmt = $db->prepare('SELECT id, image FROM vehicle_images WHERE vehicle_id = ? ORDER BY sort_order, id');
$galleryStmt->execute([$id]);
$galleryImages = $galleryStmt->fetchAll(PDO::FETCH_COLUMN, 1);

if (!$galleryImages && !empty($vehicle['image'])) {
    $galleryImages = [$vehicle['image']];
}

$galleryImages = array_values(array_map('imageUrl', $galleryImages));
if (!$galleryImages) {
    $galleryImages = ['/assets/vehicle-placeholder.png'];
}

$vehicleTitle = trim(($vehicle['make'] ?? '') . ' ' . ($vehicle['model'] ?? '') . ' ' . ($vehicle['variant'] ?? ''));
$mainImage = $galleryImages[0];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="<?=e($vehicleTitle)?> — Velaris Automotive">
<title><?=e($vehicleTitle)?> — Velaris Automotive</title>
<link rel="icon" type="image/png" href="/brand/icon-site.png">
<link rel="stylesheet" href="/styles.css">
<style>
.vehicle-page{min-height:100vh;background:var(--black)}
.vehicle-header{position:relative;z-index:5}
.vehicle-detail{max-width:1250px;margin:0 auto;padding:135px 0 100px}
.vehicle-back{display:inline-flex;align-items:center;gap:10px;color:#aaa;text-decoration:none;font-size:11px;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:24px}
.vehicle-back:hover{color:var(--orange)}
.vehicle-layout{display:grid;grid-template-columns:minmax(0,1.65fr) minmax(330px,.85fr);gap:42px;align-items:start}
.vehicle-gallery{background:#101010;border:1px solid #292929}
.vehicle-main-photo{position:relative;aspect-ratio:16/10;overflow:hidden;background:#0b0b0b}
.vehicle-main-photo img{width:100%;height:100%;object-fit:cover;display:block;transition:opacity .18s ease;cursor:zoom-in}
.vehicle-gallery-arrow{position:absolute;top:50%;transform:translateY(-50%);width:52px;height:52px;border-radius:50%;background:rgba(8,8,8,.8);border:1px solid rgba(255,106,0,.8);color:var(--orange);display:flex;align-items:center;justify-content:center;font-size:36px;line-height:1;cursor:pointer;z-index:3;transition:.2s}
.vehicle-gallery-arrow:hover{background:var(--orange);color:#fff;transform:translateY(-50%) scale(1.04)}
.vehicle-gallery-prev{left:20px}.vehicle-gallery-next{right:20px}
.vehicle-gallery-counter{position:absolute;bottom:18px;left:50%;transform:translateX(-50%);background:rgba(8,8,8,.82);border:1px solid #ffffff18;color:#fff;padding:6px 12px;border-radius:20px;font-size:10px;letter-spacing:1px}
.vehicle-thumbs{display:grid;grid-template-columns:repeat(6,1fr);gap:8px;padding:10px}
.vehicle-thumb{aspect-ratio:4/3;background:#0b0b0b;border:1px solid #292929;padding:0;cursor:pointer;overflow:hidden;opacity:.62;transition:.18s}
.vehicle-thumb:hover,.vehicle-thumb.active{opacity:1;border-color:var(--orange)}
.vehicle-thumb img{width:100%;height:100%;object-fit:cover;display:block}
.vehicle-copy{position:sticky;top:25px}
.vehicle-eyebrow{font-size:10px;text-transform:uppercase;letter-spacing:2.5px;color:#888;margin:0 0 13px}
.vehicle-copy h1{font-size:clamp(36px,4.2vw,58px);line-height:1;letter-spacing:-2.4px;font-weight:500;margin:0}
.vehicle-copy h1 span{display:block;color:var(--orange)}
.vehicle-variant{color:#aaa;margin-top:11px;font-size:13px}
.vehicle-spec-grid{display:grid;grid-template-columns:1fr 1fr;gap:0;border-top:1px solid var(--line);border-bottom:1px solid var(--line);margin:28px 0}
.vehicle-spec{padding:15px 0;border-bottom:1px solid #202020}
.vehicle-spec:nth-last-child(-n+2){border-bottom:0}
.vehicle-spec small{display:block;color:#666;text-transform:uppercase;letter-spacing:1.5px;font-size:9px;margin-bottom:3px}
.vehicle-spec strong{font-size:13px;font-weight:500;color:#eee}
.vehicle-description{color:#aaa;font-size:13px;line-height:1.85}
.vehicle-actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:28px}
.vehicle-actions .button{justify-content:center}
.vehicle-meta-note{font-size:10px;color:#666;margin-top:17px}
.vehicle-lightbox{position:fixed;inset:0;background:rgba(0,0,0,.94);display:none;align-items:center;justify-content:center;z-index:50;padding:30px}
.vehicle-lightbox.open{display:flex}
.vehicle-lightbox img{max-width:min(1400px,94vw);max-height:88vh;object-fit:contain}
.vehicle-lightbox-close{position:absolute;top:18px;right:24px;border:0;background:none;color:#fff;font-size:38px;cursor:pointer}
@media(max-width:1000px){.vehicle-detail{padding:120px 25px 80px}.vehicle-layout{grid-template-columns:1fr}.vehicle-copy{position:static}.vehicle-thumbs{grid-template-columns:repeat(5,1fr)}}
@media(max-width:600px){.vehicle-detail{padding:110px 18px 60px}.vehicle-main-photo{aspect-ratio:4/3}.vehicle-gallery-arrow{width:44px;height:44px;font-size:29px}.vehicle-gallery-prev{left:10px}.vehicle-gallery-next{right:10px}.vehicle-thumbs{grid-template-columns:repeat(4,1fr);gap:6px}.vehicle-copy h1{font-size:39px}.vehicle-spec-grid{grid-template-columns:1fr 1fr}}
</style>
</head>
<body>
<div class="vehicle-page">
<header class="nav vehicle-header">
    <a href="/" class="logo"><img src="/brand/logo-primary.svg" alt="Velaris Automotive"></a>
    <nav>
        <a href="/">Home</a>
        <a class="active" href="/collection.php">Cars</a>
        <a href="/#about">About</a>
        <a href="/#process">How it works</a>
        <a href="/#services">Services</a>
        <a href="/#contact">Contact</a>
    </nav>
    <div class="nav-actions">
        <a class="icon-btn" href="/collection.php" aria-label="Back to collection">←</a>
        <button class="outline" type="button" onclick="openLead(<?= (int)$vehicle['id'] ?>, <?=json_encode('Request current price for ' . $vehicleTitle)?>)">◉ &nbsp; Get in touch</button>
    </div>
</header>

<main class="vehicle-detail">
    <a href="/collection.php" class="vehicle-back">← Back to collection</a>

    <div class="vehicle-layout">
        <section class="vehicle-gallery" aria-label="Vehicle photo gallery">
            <div class="vehicle-main-photo" id="vehicle-gallery">
                <img id="vehicle-main-image" src="<?=e($mainImage)?>" alt="<?=e($vehicleTitle)?>" data-index="0">
                <?php if (count($galleryImages) > 1): ?>
                    <button class="vehicle-gallery-arrow vehicle-gallery-prev" type="button" aria-label="Previous photo" onclick="detailGallery(-1)">‹</button>
                    <button class="vehicle-gallery-arrow vehicle-gallery-next" type="button" aria-label="Next photo" onclick="detailGallery(1)">›</button>
                    <div class="vehicle-gallery-counter" id="vehicle-gallery-counter">1 / <?=count($galleryImages)?></div>
                <?php endif; ?>
            </div>

            <?php if (count($galleryImages) > 1): ?>
            <div class="vehicle-thumbs" id="vehicle-thumbs">
                <?php foreach ($galleryImages as $index => $galleryImage): ?>
                    <button class="vehicle-thumb <?=$index === 0 ? 'active' : ''?>" type="button" data-index="<?=$index?>" onclick="setDetailGallery(<?=$index?>)" aria-label="View photo <?=$index + 1?>">
                        <img src="<?=e($galleryImage)?>" alt="<?=e($vehicleTitle)?> photo <?=$index + 1?>">
                    </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>

        <section class="vehicle-copy">
            <p class="vehicle-eyebrow">Velaris Collection</p>
            <h1><?=e($vehicle['make'])?><span><?=e(trim(($vehicle['model'] ?? '') . ' ' . ($vehicle['variant'] ?? '')))?></span></h1>
            <p class="vehicle-variant"><?=e($vehicle['body_type'] ?? '')?></p>

            <div class="vehicle-spec-grid">
                <div class="vehicle-spec"><small>Year</small><strong><?=e((string)($vehicle['year'] ?: '—'))?></strong></div>
                <div class="vehicle-spec"><small>Mileage</small><strong><?=number_format((int)($vehicle['mileage'] ?? 0))?> km</strong></div>
                <div class="vehicle-spec"><small>Power</small><strong><?=e((string)($vehicle['power'] ?: '—'))?> HP</strong></div>
                <div class="vehicle-spec"><small>Fuel</small><strong><?=e($vehicle['fuel'] ?: '—')?></strong></div>
                <div class="vehicle-spec"><small>Transmission</small><strong><?=e($vehicle['transmission'] ?: '—')?></strong></div>
                <div class="vehicle-spec"><small>Status</small><strong>Available</strong></div>
            </div>

            <?php if (!empty($vehicle['description'])): ?>
                <p class="vehicle-description"><?=nl2br(e($vehicle['description']))?></p>
            <?php endif; ?>

            <div class="vehicle-actions">
                <button class="button" type="button" onclick="openLead(<?= (int)$vehicle['id'] ?>, <?=json_encode('Request current price for ' . $vehicleTitle)?>)">Request price →</button>
                <a class="button ghost" href="/collection.php">Back to collection</a>
            </div>
            <p class="vehicle-meta-note">Availability and pricing are confirmed directly with Velaris Automotive.</p>
        </section>
    </div>
</main>

<footer id="contact">
    <img src="/brand/logo-primary.svg" alt="Velaris Automotive">
    <p>Premium automotive sourcing &amp; brokerage.</p>
    <a href="mailto:hello@velarisautomotive.com">hello@velarisautomotive.com</a>
    <p class="legal">Velaris Automotive acts as an intermediary where applicable. Vehicle availability, condition and pricing are confirmed on request.</p>
</footer>
</div>

<div class="vehicle-lightbox" id="vehicle-lightbox" onclick="closeLightbox(event)">
    <button class="vehicle-lightbox-close" type="button" aria-label="Close">×</button>
    <img id="vehicle-lightbox-image" src="" alt="">
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
        <select name="contact_method"><option>Email</option><option>Phone</option><option>WhatsApp</option></select>
        <textarea name="message" placeholder="Anything you would like us to know?"></textarea>
        <button class="button" type="submit">Send enquiry →</button>
        <p id="form-status"></p>
    </form>
</dialog>

<script>
const detailImages = <?=json_encode($galleryImages, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)?>;
let detailIndex = 0;
let currentVehicle = <?= (int)$vehicle['id'] ?>;

function updateDetailImage() {
    const image = document.querySelector('#vehicle-main-image');
    const counter = document.querySelector('#vehicle-gallery-counter');
    const thumbs = document.querySelectorAll('.vehicle-thumb');
    if (!image) return;

    image.style.opacity = '0';
    window.setTimeout(() => {
        image.src = detailImages[detailIndex];
        image.style.opacity = '1';
        image.dataset.index = String(detailIndex);
        if (counter) counter.textContent = `${detailIndex + 1} / ${detailImages.length}`;
        thumbs.forEach((thumb, i) => thumb.classList.toggle('active', i === detailIndex));
    }, 110);
}

function setDetailGallery(index) {
    if (!detailImages.length) return;
    detailIndex = Math.max(0, Math.min(index, detailImages.length - 1));
    updateDetailImage();
}

function detailGallery(direction) {
    if (detailImages.length <= 1) return;
    detailIndex = (detailIndex + direction + detailImages.length) % detailImages.length;
    updateDetailImage();
}

const mainVehicleImage = document.querySelector('#vehicle-main-image');
if (mainVehicleImage) {
    mainVehicleImage.addEventListener('click', () => {
        const box = document.querySelector('#vehicle-lightbox');
        const lightboxImage = document.querySelector('#vehicle-lightbox-image');
        if (!box || !lightboxImage) return;
        lightboxImage.src = detailImages[detailIndex];
        lightboxImage.alt = mainVehicleImage.alt;
        box.classList.add('open');
    });
}

function closeLightbox(event) {
    if (event && event.target && event.target.id === 'vehicle-lightbox-image') return;
    document.querySelector('#vehicle-lightbox')?.classList.remove('open');
}

document.addEventListener('keydown', (event) => {
    const lightbox = document.querySelector('#vehicle-lightbox');
    if (event.key === 'Escape') lightbox?.classList.remove('open');
    if (!lightbox?.classList.contains('open')) {
        if (event.key === 'ArrowLeft') detailGallery(-1);
        if (event.key === 'ArrowRight') detailGallery(1);
    }
});

function openLead(vehicleId = null, title = 'Request current price') {
    currentVehicle = vehicleId;
    document.querySelector('#form-title').textContent = title;
    document.querySelector('#lead-modal').showModal();
}
function closeLead() { document.querySelector('#lead-modal').close(); }

document.querySelector('#lead-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.target;
    const data = Object.fromEntries(new FormData(form));
    data.vehicle_id = currentVehicle;
    data.source = 'vehicle page';
    try {
        const response = await fetch('/api/leads', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data) });
        document.querySelector('#form-status').textContent = response.ok ? 'Thank you — your enquiry has been received.' : 'Please check your name and email address.';
        if (response.ok) form.reset();
    } catch (error) {
        console.error(error);
        document.querySelector('#form-status').textContent = 'Something went wrong. Please try again.';
    }
});
</script>
</body>
</html>
