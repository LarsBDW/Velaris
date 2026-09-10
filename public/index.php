<?php

declare(strict_types=1);

// ------------------------------------------------------------
// Security headers
// ------------------------------------------------------------

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

// ------------------------------------------------------------
// Database
// ------------------------------------------------------------

$db = new PDO('sqlite:' . dirname(__DIR__) . '/storage/velaris.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ------------------------------------------------------------
// Database tables
// ------------------------------------------------------------

$db->exec(
    'CREATE TABLE IF NOT EXISTS vehicles (
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
    )'
);

$db->exec(
    'CREATE TABLE IF NOT EXISTS leads (
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
    )'
);

$db->exec(
    'CREATE TABLE IF NOT EXISTS dealers (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL,
        contact_name TEXT,
        phone TEXT,
        email TEXT,
        country TEXT,
        internal_notes TEXT
    )'
);

$db->exec(
    'CREATE TABLE IF NOT EXISTS vehicle_internal (
        vehicle_id INTEGER PRIMARY KEY,
        dealer_id INTEGER,
        purchase_price_cents INTEGER,
        target_price_cents INTEGER,
        commission_cents INTEGER,
        internal_notes TEXT,
        FOREIGN KEY(vehicle_id) REFERENCES vehicles(id),
        FOREIGN KEY(dealer_id) REFERENCES dealers(id)
    )'
);

// ------------------------------------------------------------
// Seed demo vehicles
// ------------------------------------------------------------

$vehicleCount = (int) $db
    ->query('SELECT COUNT(*) FROM vehicles')
    ->fetchColumn();

if ($vehicleCount === 0) {
    $seed = $db->prepare(
        'INSERT INTO vehicles (
            make,
            model,
            variant,
            year,
            mileage,
            fuel,
            transmission,
            power,
            body_type,
            status,
            image,
            description,
            featured
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $seed->execute([
        'BMW',
        'M4',
        'Competition xDrive',
        2024,
        24500,
        'Petrol',
        'Automatic',
        510,
        'Coupé',
        'available',
        'assets/vehicle-placeholder.png',
        'A precisely specified performance coupé, sourced through our trusted European dealer network.',
        1,
    ]);

    $seed->execute([
        'Porsche',
        '911',
        'Carrera 4 GTS',
        2023,
        18900,
        'Petrol',
        'Automatic',
        480,
        'Sports Car',
        'available',
        'assets/vehicle-placeholder.png',
        'An exceptional grand touring icon, presented with a complete specification and clear provenance.',
        1,
    ]);

    $seed->execute([
        'Range Rover',
        'Sport',
        'P460e Autobiography',
        2024,
        12000,
        'Plug-in Hybrid',
        'Automatic',
        460,
        'SUV',
        'available',
        'assets/vehicle-placeholder.png',
        'Refined capability and contemporary luxury, selected for its exceptional configuration.',
        1,
    ]);
}

// ------------------------------------------------------------
// Routing
// ------------------------------------------------------------

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

// ------------------------------------------------------------
// API: Vehicles
// ------------------------------------------------------------

if ($path === '/api/vehicles') {
    header('Content-Type: application/json');

    $term = trim((string) ($_GET['q'] ?? ''));

    $sql = '
        SELECT
            id,
            make,
            model,
            variant,
            year,
            mileage,
            fuel,
            transmission,
            power,
            body_type,
            status,
            image,
            description,
            featured
        FROM vehicles
        WHERE status = "available"
    ';

    $params = [];

    if ($term !== '') {
        $sql .= '
            AND (
                make LIKE ?
                OR model LIKE ?
                OR variant LIKE ?
            )
        ';

        $searchTerm = "%{$term}%";

        $params = [
            $searchTerm,
            $searchTerm,
            $searchTerm,
        ];
    }

    $sql .= '
        ORDER BY featured DESC, created_at DESC
    ';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    echo json_encode(
        $stmt->fetchAll(PDO::FETCH_ASSOC)
    );

    exit;
}

// ------------------------------------------------------------
// API: Leads
// ------------------------------------------------------------

if (
    $path === '/api/leads'
    && $_SERVER['REQUEST_METHOD'] === 'POST'
) {
    header('Content-Type: application/json');

    $data = json_decode(
        (string) file_get_contents('php://input'),
        true
    ) ?: [];

    // Validate required fields
    if (
        empty($data['name'])
        || !filter_var(
            $data['email'] ?? '',
            FILTER_VALIDATE_EMAIL
        )
    ) {
        http_response_code(422);

        echo json_encode([
            'error' => 'Please provide your name and a valid email address.',
        ]);

        exit;
    }

    $stmt = $db->prepare(
        'INSERT INTO leads (
            name,
            email,
            phone,
            whatsapp,
            message,
            contact_method,
            vehicle_id,
            source
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $stmt->execute([
        substr((string) $data['name'], 0, 100),
        substr((string) $data['email'], 0, 190),
        substr((string) ($data['phone'] ?? ''), 0, 40),
        substr((string) ($data['whatsapp'] ?? ''), 0, 40),
        substr((string) ($data['message'] ?? ''), 0, 2000),
        substr((string) ($data['contact_method'] ?? 'Email'), 0, 20),
        isset($data['vehicle_id'])
            ? (int) $data['vehicle_id']
            : null,
        substr((string) ($data['source'] ?? 'website'), 0, 30),
    ]);

    echo json_encode([
        'ok' => true,
    ]);

    exit;
}

?>

<!doctype html>

<html lang="en">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        name="description"
        content="Velaris Automotive — premium automotive sourcing and brokerage."
    >

    <meta
        property="og:image"
        content="/assets/vehicle-placeholder.png"
    >

    <title>Velaris Automotive — Sourced differently</title>

    <link
        rel="icon"
        type="image/png"
        href="/brand/icon-site.png"
    >

    <link
        rel="apple-touch-icon"
        href="/brand/icon-site.png"
    >

    <link
        rel="stylesheet"
        href="/styles.css"
    >

    <style>
        .hero-image {
            background:
                linear-gradient(
                    90deg,
                    #080808 3%,
                    #080808e6 31%,
                    #0808081a 67%,
                    #08080845
                ),
                linear-gradient(
                    0deg,
                    #080808 0%,
                    transparent 29%
                ),
                url('/assets/hero-porsche-sunset.png')
                center 57% / cover
                no-repeat;
        }
    </style>

</head>

<body>

    <!-- ========================================================
         Navigation
    ========================================================= -->

    <header class="nav">

        <a href="#top" class="logo">
            <img
                src="/brand/logo-primary.svg"
                alt="Velaris Automotive"
            >
        </a>

        <nav>

            <a
                class="active"
                href="#top"
            >
                Home
            </a>

            <a href="/collection.php">
                Cars
            </a>

            <a href="#about">
                About
            </a>

            <a href="#process">
                How it works
            </a>

            <a href="#services">
                Services
            </a>

            <a href="#contact">
                Contact
            </a>

        </nav>

        <div class="nav-actions">

            <button
                class="icon-btn"
                aria-label="Search"
                onclick="document.querySelector('#search').focus()"
            >
                ⌕
            </button>

            <button
                class="outline"
                onclick="openLead()"
            >
                ◉ &nbsp; Get in touch
            </button>

        </div>

    </header>

    <!-- ========================================================
         Main content
    ========================================================= -->

    <main id="top">

        <!-- Hero -->

        <section class="hero">

            <div class="hero-image"></div>

            <div class="hero-copy">

                <p class="eyebrow">
                    PREMIUM CARS. EXCLUSIVE SOURCING.
                </p>

                <h1>
                    Your next car,
                    <br>
                    <em>sourced differently.</em>
                </h1>

                <p class="intro">
                    Velaris Automotive is your independent sourcing partner
                    for premium and exclusive vehicles. We connect you with
                    trusted dealers across Europe to find the perfect car —
                    exactly how you want it.
                </p>

                <div class="hero-buttons">

                    <a
                        class="button"
                        href="#inventory"
                    >
                        Browse inventory
                        <span>→</span>
                    </a>

                    <button
                        class="button ghost"
                        onclick="openLead(null, 'Find My Car')"
                    >
                        Find my car&nbsp; ⌕
                    </button>

                </div>

            </div>

        </section>

        <!-- Search -->

        <section
            class="search-panel"
            aria-label="Search inventory"
        >

            <div>

                <p class="eyebrow">
                    CURATED AVAILABILITY
                </p>

                <h2>
                    Find something exceptional.
                </h2>

            </div>

            <div class="search-row">

                <input
                    id="search"
                    placeholder="Make, model or keyword"
                >

                <select>

                    <option>
                        All body types
                    </option>

                    <option>
                        SUV
                    </option>

                    <option>
                        Coupé
                    </option>

                    <option>
                        Sports Car
                    </option>

                </select>

                <button
                    class="button"
                    onclick="loadVehicles()"
                >
                    Search cars →
                </button>

            </div>

        </section>

        <!-- ====================================================
             Inventory
        ===================================================== -->

        <section
            id="inventory"
            class="section"
        >

            <div class="section-heading">

                <div>

                    <p class="eyebrow">
                        SELECTED FOR YOU
                    </p>

                    <h2>
                        Featured vehicles
                    </h2>

                </div>

                <a
                    class="text-link"
                    href="/collection.php"
                >
                    View all vehicles →
                </a>

            </div>

            <div
                id="cars"
                class="cars"
                aria-live="polite"
            ></div>

        </section>

        <!-- ====================================================
             About / Benefits
        ===================================================== -->

        <section
            id="about"
            class="benefits section"
        >

            <div>

                <p class="eyebrow">
                    THE VELARIS STANDARD
                </p>

                <h2>
                    Beyond the showroom.
                </h2>

                <p>
                    We are independent by design. That means the focus stays
                    on the right vehicle, the right source and a straightforward
                    path from first conversation to the next step.
                </p>

            </div>

            <div class="benefit-grid">

                <article>

                    <span>01</span>

                    <h3>
                        Independent
                    </h3>

                    <p>
                        Not tied to a single showroom or stock list.
                    </p>

                </article>

                <article>

                    <span>02</span>

                    <h3>
                        Connected
                    </h3>

                    <p>
                        A considered network of professional dealers across Europe.
                    </p>

                </article>

                <article>

                    <span>03</span>

                    <h3>
                        Personal
                    </h3>

                    <p>
                        One point of contact through the sourcing process.
                    </p>

                </article>

                <article>

                    <span>04</span>

                    <h3>
                        Selective
                    </h3>

                    <p>
                        Vehicles presented with intent, not volume.
                    </p>

                </article>

            </div>

        </section>

        <!-- ====================================================
             How it works
        ===================================================== -->

        <section
            id="process"
            class="process"
        >

            <p class="eyebrow">
                HOW IT WORKS
            </p>

            <h2>
                A sharper route to your next car.
            </h2>

            <div class="steps">

                <article>

                    <b>01</b>

                    <h3>
                        Discover
                    </h3>

                    <p>
                        Browse available vehicles or tell us exactly what you want.
                    </p>

                </article>

                <article>

                    <b>02</b>

                    <h3>
                        Source
                    </h3>

                    <p>
                        We check our dealer network and market availability.
                    </p>

                </article>

                <article>

                    <b>03</b>

                    <h3>
                        Confirm
                    </h3>

                    <p>
                        We verify price, availability and vehicle details.
                    </p>

                </article>

                <article>

                    <b>04</b>

                    <h3>
                        Connect
                    </h3>

                    <p>
                        We arrange next steps with the relevant dealer or partner.
                    </p>

                </article>

            </div>

        </section>

        <!-- ====================================================
             Find My Car
        ===================================================== -->

        <section
            id="services"
            class="find-car"
        >

            <div>

                <p class="eyebrow">
                    CAN'T SEE THE RIGHT ONE?
                </p>

                <h2>
                    Let us find your car.
                </h2>

                <p>
                    Share your ideal specification and we’ll start searching
                    our dealer network on your behalf.
                </p>

            </div>

            <button
                class="button"
                onclick="openLead(null, 'Start my search')"
            >
                Start my search →
            </button>

        </section>

    </main>

    <!-- ========================================================
         Footer
    ========================================================= -->

    <footer id="contact">

        <img
            src="/brand/logo-primary.svg"
            alt="Velaris Automotive"
        >

        <p>
            Premium automotive sourcing &amp; brokerage.
        </p>

        <a href="mailto:hello@velarisautomotive.com">
            hello@velarisautomotive.com
        </a>

        <p class="legal">
            Velaris Automotive acts as an intermediary where applicable.
            Vehicle availability, condition and pricing are confirmed on request.
        </p>

    </footer>

    <!-- ========================================================
         Mobile WhatsApp
    ========================================================= -->

    <button
        class="mobile-whatsapp"
        onclick="openLead()"
    >
        ◉ WhatsApp
    </button>

    <!-- ========================================================
         Lead Modal
    ========================================================= -->

    <dialog id="lead-modal">

        <button
            class="close"
            onclick="closeLead()"
        >
            ×
        </button>

        <p class="eyebrow">
            VELARIS AUTOMOTIVE
        </p>

        <h2 id="form-title">
            Request current price
        </h2>

        <p id="form-copy">
            Tell us how we can reach you and we’ll confirm
            current availability and pricing.
        </p>

        <form id="lead-form">

            <input
                name="name"
                required
                placeholder="Your name"
            >

            <input
                name="email"
                required
                type="email"
                placeholder="Email address"
            >

            <input
                name="phone"
                placeholder="Phone / WhatsApp"
            >

            <select name="contact_method">

                <option>
                    Email
                </option>

                <option>
                    Phone
                </option>

                <option>
                    WhatsApp
                </option>

            </select>

            <textarea
                name="message"
                placeholder="Your message"
            ></textarea>

            <button
                class="button"
                type="submit"
            >
                Send enquiry →
            </button>

            <p id="form-status"></p>

        </form>

    </dialog>

    <!-- ========================================================
         JavaScript
    ========================================================= -->

    <script src="/app.js"></script>

</body>

</html>