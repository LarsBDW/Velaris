<?php
declare(strict_types=1);

/*
 * Velaris Admin
 * Protected by HTTP Basic Auth using:
 * VELARIS_ADMIN_USER
 * VELARIS_ADMIN_PASSWORD
 */

$user = getenv('VELARIS_ADMIN_USER');
$password = getenv('VELARIS_ADMIN_PASSWORD');

if (!$user || !$password) {
    http_response_code(503);
    exit('Admin is disabled. Configure VELARIS_ADMIN_USER and VELARIS_ADMIN_PASSWORD in the server environment.');
}

if (
    !isset($_SERVER['PHP_AUTH_USER']) ||
    !hash_equals($user, $_SERVER['PHP_AUTH_USER']) ||
    !hash_equals($password, $_SERVER['PHP_AUTH_PW'] ?? '')
) {
    header('WWW-Authenticate: Basic realm="Velaris Admin"');
    http_response_code(401);
    exit('Authentication required.');
}

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

$db->exec('CREATE TABLE IF NOT EXISTS leads (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    email TEXT NOT NULL,
    phone TEXT,
    whatsapp TEXT,
    message TEXT,
    contact_method TEXT,
    vehicle_id INTEGER,
    source TEXT NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
)');

$db->exec('CREATE TABLE IF NOT EXISTS dealers (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    contact_name TEXT,
    phone TEXT,
    email TEXT,
    country TEXT,
    internal_notes TEXT
)');

$db->exec('CREATE TABLE IF NOT EXISTS vehicle_internal (
    vehicle_id INTEGER PRIMARY KEY,
    dealer_id INTEGER,
    purchase_price_cents INTEGER,
    target_price_cents INTEGER,
    commission_cents INTEGER,
    internal_notes TEXT,
    FOREIGN KEY(vehicle_id) REFERENCES vehicles(id),
    FOREIGN KEY(dealer_id) REFERENCES dealers(id)
)');

/* ---------- Helpers ---------- */

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirectAdmin(string $message = ''): never
{
    $url = '/admin.php';
    if ($message !== '') {
        $url .= '?message=' . urlencode($message);
    }
    header('Location: ' . $url);
    exit;
}

/* ---------- Inventory actions ---------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id > 0) {
            $stmt = $db->prepare('DELETE FROM vehicle_internal WHERE vehicle_id = ?');
            $stmt->execute([$id]);

            $stmt = $db->prepare('DELETE FROM vehicles WHERE id = ?');
            $stmt->execute([$id]);
        }

        redirectAdmin('Vehicle deleted');
    }

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $make = trim((string) ($_POST['make'] ?? ''));
        $model = trim((string) ($_POST['model'] ?? ''));

        if ($make === '' || $model === '') {
            redirectAdmin('Make and model are required');
        }

        $variant = trim((string) ($_POST['variant'] ?? ''));
        $year = (int) ($_POST['year'] ?? 0) ?: null;
        $mileage = (int) ($_POST['mileage'] ?? 0);
        $fuel = trim((string) ($_POST['fuel'] ?? ''));
        $transmission = trim((string) ($_POST['transmission'] ?? ''));
        $power = (int) ($_POST['power'] ?? 0) ?: null;
        $bodyType = trim((string) ($_POST['body_type'] ?? ''));
        $status = trim((string) ($_POST['status'] ?? 'available'));
        $description = trim((string) ($_POST['description'] ?? ''));
        $featured = isset($_POST['featured']) ? 1 : 0;

        $allowedStatuses = ['available', 'reserved', 'sold', 'hidden'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'available';
        }

        /* Keep the current image unless a new image was uploaded. */
        $image = trim((string) ($_POST['existing_image'] ?? ''));

        if (
            isset($_FILES['image_file']) &&
            $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE
        ) {
            $file = $_FILES['image_file'];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                redirectAdmin('Image upload failed');
            }

            if ($file['size'] > 10 * 1024 * 1024) {
                redirectAdmin('Image is too large (maximum 10 MB)');
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);

            $extensions = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
            ];

            if (!isset($extensions[$mime])) {
                redirectAdmin('Please upload a JPG, PNG or WebP image');
            }

            $uploadDir = dirname(__DIR__) . '/assets/vehicles';

            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                redirectAdmin('Could not create the vehicle image folder');
            }

            $base = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $make . '-' . $model));
            $base = trim($base, '-');
            $filename = $base . '-' . bin2hex(random_bytes(5)) . '.' . $extensions[$mime];

            if (!move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $filename)) {
                redirectAdmin('Could not save the uploaded image');
            }

            $image = '/assets/vehicles/' . $filename;
        }

        if ($id > 0) {
            $stmt = $db->prepare(
                'UPDATE vehicles SET
                    make = ?, model = ?, variant = ?, year = ?, mileage = ?,
                    fuel = ?, transmission = ?, power = ?, body_type = ?,
                    status = ?, image = ?, description = ?, featured = ?
                 WHERE id = ?'
            );

            $stmt->execute([
                $make, $model, $variant, $year, $mileage,
                $fuel, $transmission, $power, $bodyType,
                $status, $image, $description, $featured, $id
            ]);

            redirectAdmin('Vehicle updated');
        }

        $stmt = $db->prepare(
            'INSERT INTO vehicles (
                make, model, variant, year, mileage, fuel, transmission,
                power, body_type, status, image, description, featured
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $make, $model, $variant, $year, $mileage,
            $fuel, $transmission, $power, $bodyType,
            $status, $image, $description, $featured
        ]);

        redirectAdmin('Vehicle added');
    }
}

/* ---------- Load data ---------- */

$editId = (int) ($_GET['edit'] ?? 0);
$editingVehicle = null;

if ($editId > 0) {
    $stmt = $db->prepare('SELECT * FROM vehicles WHERE id = ?');
    $stmt->execute([$editId]);
    $editingVehicle = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$vehicles = $db->query(
    'SELECT v.*, d.name dealer,
        i.purchase_price_cents,
        i.target_price_cents,
        i.commission_cents
     FROM vehicles v
     LEFT JOIN vehicle_internal i ON i.vehicle_id = v.id
     LEFT JOIN dealers d ON d.id = i.dealer_id
     ORDER BY v.created_at DESC'
)->fetchAll(PDO::FETCH_ASSOC);

$leads = $db->query(
    'SELECT l.*, v.make || " " || v.model AS vehicle
     FROM leads l
     LEFT JOIN vehicles v ON v.id = l.vehicle_id
     ORDER BY l.created_at DESC'
)->fetchAll(PDO::FETCH_ASSOC);

$message = (string) ($_GET['message'] ?? '');

function field(array $vehicle, string $key, string $default = ''): string
{
    return e(isset($vehicle[$key]) ? (string) $vehicle[$key] : $default);
}

$defaults = [
    'make' => '',
    'model' => '',
    'variant' => '',
    'year' => '',
    'mileage' => '',
    'fuel' => 'Petrol',
    'transmission' => 'Automatic',
    'power' => '',
    'body_type' => 'Sports Car',
    'status' => 'available',
    'image' => '',
    'description' => '',
    'featured' => 0,
];

$formVehicle = array_merge($defaults, $editingVehicle ?: []);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Velaris Admin</title>
<style>
*{box-sizing:border-box}
body{margin:0;background:#0d0d0d;color:#eee;font:14px Arial,sans-serif;padding:32px}
.wrap{max-width:1500px;margin:0 auto}
h1{color:#ff6a00;margin:0 0 8px}
h2{margin-top:38px}
h3{margin-top:0}
p{color:#ccc}
.panel{background:#151515;border:1px solid #303030;padding:22px;margin:20px 0}
.notice{border:1px solid #ff6a00;background:#21160f;padding:12px;color:#fff}
.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
.grid .wide{grid-column:span 2}
label{display:block;color:#aaa;font-size:12px;margin-bottom:6px}
input,select,textarea{width:100%;background:#0d0d0d;border:1px solid #383838;color:#fff;padding:11px;border-radius:3px}
textarea{min-height:100px;resize:vertical}
.actions{display:flex;gap:10px;align-items:center;margin-top:18px;flex-wrap:wrap}
button,.button{border:1px solid #ff6a00;background:#ff6a00;color:#fff;padding:11px 16px;border-radius:4px;font-weight:bold;cursor:pointer;text-decoration:none}
button.secondary,.button.secondary{background:transparent;border-color:#555}
button.danger{background:#6d1717;border-color:#8e2424}
.check{display:flex;gap:8px;align-items:center;color:#ddd;margin-top:25px}
.check input{width:auto}
.table-wrap{overflow:auto}
table{border-collapse:collapse;width:100%;min-width:1050px;background:#151515}
td,th{padding:11px;border:1px solid #303030;text-align:left;vertical-align:middle}
th{color:#ff6a00}
.thumb{width:110px;height:70px;object-fit:cover;background:#222;border:1px solid #333}
.muted{color:#888}
.inline-form{display:inline}
@media(max-width:900px){.grid{grid-template-columns:1fr 1fr}.grid .wide{grid-column:span 2}}
@media(max-width:600px){body{padding:16px}.grid{grid-template-columns:1fr}.grid .wide{grid-column:span 1}}
</style>
</head>
<body>
<div class="wrap">

<h1>Velaris Admin</h1>
<p>Private operational data — do not share this URL or credentials.</p>

<?php if ($message !== ''): ?>
    <div class="notice"><?=e($message)?></div>
<?php endif; ?>

<section class="panel">
    <h2><?= $editingVehicle ? 'Edit vehicle' : 'Add vehicle' ?></h2>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int) ($formVehicle['id'] ?? 0) ?>">
        <input type="hidden" name="existing_image" value="<?=e((string) $formVehicle['image'])?>">

        <div class="grid">
            <div>
                <label>Make *</label>
                <input name="make" required value="<?=field($formVehicle,'make')?>" placeholder="Porsche">
            </div>

            <div>
                <label>Model *</label>
                <input name="model" required value="<?=field($formVehicle,'model')?>" placeholder="911">
            </div>

            <div>
                <label>Variant</label>
                <input name="variant" value="<?=field($formVehicle,'variant')?>" placeholder="GT3 RS Weissach">
            </div>

            <div>
                <label>Year</label>
                <input type="number" name="year" value="<?=field($formVehicle,'year')?>" placeholder="2025">
            </div>

            <div>
                <label>Mileage (km)</label>
                <input type="number" name="mileage" value="<?=field($formVehicle,'mileage')?>" placeholder="12000">
            </div>

            <div>
                <label>Fuel</label>
                <input name="fuel" value="<?=field($formVehicle,'fuel')?>" placeholder="Petrol">
            </div>

            <div>
                <label>Transmission</label>
                <input name="transmission" value="<?=field($formVehicle,'transmission')?>" placeholder="Automatic">
            </div>

            <div>
                <label>Power (HP)</label>
                <input type="number" name="power" value="<?=field($formVehicle,'power')?>" placeholder="525">
            </div>

            <div>
                <label>Body type</label>
                <input name="body_type" value="<?=field($formVehicle,'body_type')?>" placeholder="Sports Car">
            </div>

            <div>
                <label>Status</label>
                <select name="status">
                    <?php foreach (['available','reserved','sold','hidden'] as $status): ?>
                        <option value="<?=e($status)?>" <?=($formVehicle['status'] === $status ? 'selected' : '')?>>
                            <?=ucfirst($status)?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="wide">
                <label>Vehicle image</label>
                <input type="file" name="image_file" accept="image/jpeg,image/png,image/webp">
                <?php if (!empty($formVehicle['image'])): ?>
                    <p class="muted">Current image: <?=e((string) $formVehicle['image'])?></p>
                <?php endif; ?>
            </div>

            <div class="wide">
                <label>Description</label>
                <textarea name="description" placeholder="Short description of the vehicle..."><?=field($formVehicle,'description')?></textarea>
            </div>
        </div>

        <label class="check">
            <input type="checkbox" name="featured" value="1" <?=!empty($formVehicle['featured']) ? 'checked' : ''?>>
            Featured vehicle
        </label>

        <div class="actions">
            <button type="submit"><?= $editingVehicle ? 'Save changes' : 'Add vehicle' ?></button>
            <?php if ($editingVehicle): ?>
                <a class="button secondary" href="/admin.php">Cancel edit</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<h2>Inventory</h2>
<div class="table-wrap">
<table>
<tr>
    <th>Image</th>
    <th>Vehicle</th>
    <th>Status</th>
    <th>Year</th>
    <th>Mileage</th>
    <th>Power</th>
    <th>Dealer</th>
    <th>Purchase</th>
    <th>Target</th>
    <th>Actions</th>
</tr>

<?php foreach ($vehicles as $v): ?>
<tr>
    <td>
        <img class="thumb"
             src="<?=e($v['image'] ?: '/assets/vehicle-placeholder.png')?>"
             alt="<?=e($v['make'].' '.$v['model'])?>">
    </td>
    <td>
        <strong><?=e($v['make'].' '.$v['model'])?></strong><br>
        <span class="muted"><?=e($v['variant'] ?? '')?></span>
    </td>
    <td><?=e($v['status'])?></td>
    <td><?=e((string)($v['year'] ?? '—'))?></td>
    <td><?=number_format((int)($v['mileage'] ?? 0))?> km</td>
    <td><?=e((string)($v['power'] ?? '—'))?> HP</td>
    <td><?=e($v['dealer'] ?? '—')?></td>
    <td><?=isset($v['purchase_price_cents']) ? '€'.number_format($v['purchase_price_cents']/100,0) : '—'?></td>
    <td><?=isset($v['target_price_cents']) ? '€'.number_format($v['target_price_cents']/100,0) : '—'?></td>
    <td>
        <a class="button secondary" href="/admin.php?edit=<?=(int)$v['id']?>">Edit</a>
        <form class="inline-form" method="post" onsubmit="return confirm('Delete this vehicle?');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?=(int)$v['id']?>">
            <button class="danger" type="submit">Delete</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</table>
</div>

<h2>Latest enquiries</h2>
<div class="table-wrap">
<table>
<tr>
    <th>Received</th>
    <th>Customer</th>
    <th>Vehicle</th>
    <th>Contact</th>
    <th>Message</th>
</tr>
<?php foreach ($leads as $l): ?>
<tr>
    <td><?=e($l['created_at'])?></td>
    <td><?=e($l['name'])?><br><span class="muted"><?=e($l['email'])?></span></td>
    <td><?=e($l['vehicle'] ?? 'General enquiry')?></td>
    <td><?=e($l['contact_method'] ?? 'Email')?> · <?=e($l['phone'] ?? $l['whatsapp'] ?? '—')?></td>
    <td><?=e($l['message'] ?? '—')?></td>
</tr>
<?php endforeach; ?>
</table>
</div>

</div>
</body>
</html>
