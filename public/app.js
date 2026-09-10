let currentVehicle = null;
const PLACEHOLDER_IMAGE = "/assets/vehicle-placeholder.png";
const galleryIndexes = {};

function escapeHtml(value) {
    return String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function vehicleImages(car) {
    if (Array.isArray(car.images) && car.images.length) {
        return car.images.filter(Boolean);
    }

    if (
        car.image &&
        car.image.trim() &&
        !car.image.includes("vehicle-placeholder.png")
    ) {
        return [car.image];
    }

    return [PLACEHOLDER_IMAGE];
}

function changeGallery(vehicleId, direction) {
    const gallery = document.querySelector(`#gallery-${vehicleId}`);
    if (!gallery) return;

    const data = gallery.querySelector("[data-gallery-images]");
    const image = gallery.querySelector("[data-gallery-image]");
    const counter = gallery.querySelector("[data-gallery-current]");
    if (!data || !image) return;

    let images = [];
    try {
        images = JSON.parse(data.textContent || "[]");
    } catch {
        return;
    }

    if (images.length <= 1) return;

    if (!(vehicleId in galleryIndexes)) galleryIndexes[vehicleId] = 0;

    galleryIndexes[vehicleId] += direction;
    if (galleryIndexes[vehicleId] < 0) {
        galleryIndexes[vehicleId] = images.length - 1;
    }
    if (galleryIndexes[vehicleId] >= images.length) {
        galleryIndexes[vehicleId] = 0;
    }

    image.style.opacity = "0";

    window.setTimeout(() => {
        image.src = images[galleryIndexes[vehicleId]];
        image.style.opacity = "1";
        if (counter) {
            counter.textContent = `${galleryIndexes[vehicleId] + 1} / ${images.length}`;
        }
    }, 110);
}

function renderVehicleCard(car) {
    const images = vehicleImages(car);
    const safeImages = JSON.stringify(images);
    const firstImage = escapeHtml(images[0]);
    const title = `${car.model || ""} ${car.variant || ""}`.trim();

    return `
        <article class="car">
            <div class="car-gallery" id="gallery-${Number(car.id)}">
                <img
                    class="car-gallery-image"
                    src="${firstImage}"
                    alt="${escapeHtml(`${car.make || ""} ${title}`.trim())}"
                    data-gallery-image
                >

                <span class="badge">
                    ${car.featured ? "FEATURED" : "AVAILABLE"}
                </span>

                ${
                    images.length > 1
                        ? `
                            <button class="gallery-arrow gallery-prev" type="button" aria-label="Previous photo" onclick="changeGallery(${Number(car.id)}, -1)">‹</button>
                            <button class="gallery-arrow gallery-next" type="button" aria-label="Next photo" onclick="changeGallery(${Number(car.id)}, 1)">›</button>
                            <div class="gallery-counter" data-gallery-current>1 / ${images.length}</div>
                          `
                        : ""
                }

                <script type="application/json" data-gallery-images>${safeImages.replace(/</g, "\\u003c")}</script>
            </div>

            <div class="car-content">
                <div class="car-make">${escapeHtml(car.make)}</div>
                <h3>${escapeHtml(title)}</h3>
                <div class="specs">
                    ${escapeHtml(car.year || "—")}
                    &nbsp;·&nbsp;
                    ${Number(car.mileage || 0).toLocaleString()} km
                    &nbsp;·&nbsp;
                    ${escapeHtml(car.fuel || "—")}
                    <br>
                    ${escapeHtml(car.transmission || "—")}
                    &nbsp;·&nbsp;
                    ${escapeHtml(car.power || "—")} HP
                </div>
                <button
                    class="card-action"
                    onclick="openLead(${Number(car.id)}, 'Request current price for ${escapeHtml(`${car.make || ""} ${car.model || ""}`.trim())}')"
                >
                    Request price →
                </button>
            </div>
        </article>
    `;
}

async function loadVehicles() {
    const searchInput = document.querySelector("#search");
    const carsContainer = document.querySelector("#cars");
    if (!carsContainer) return;

    const query = searchInput ? searchInput.value.trim() : "";

    try {
        const response = await fetch(`/api/vehicles?q=${encodeURIComponent(query)}`);
        if (!response.ok) throw new Error("Failed to load vehicles");

        const cars = await response.json();

        if (!cars.length) {
            carsContainer.innerHTML = `
                <p>No vehicles match your current filters. Tell Velaris what you are looking for.</p>
            `;
            return;
        }

        carsContainer.innerHTML = cars.map(renderVehicleCard).join("");
    } catch (error) {
        console.error("Error loading vehicles:", error);
        carsContainer.innerHTML = `
            <p>Unable to load vehicles right now. Please try again later.</p>
        `;
    }
}

function openLead(vehicleId = null, title = "Request current price") {
    currentVehicle = vehicleId;
    const modal = document.querySelector("#lead-modal");
    const titleEl = document.querySelector("#form-title");
    if (titleEl) titleEl.textContent = title;
    if (modal) modal.showModal();
}

function closeLead() {
    const modal = document.querySelector("#lead-modal");
    if (modal) modal.close();
}

const leadForm = document.querySelector("#lead-form");
if (leadForm) {
    leadForm.addEventListener("submit", async (event) => {
        event.preventDefault();
        const form = event.target;
        const data = Object.fromEntries(new FormData(form));
        data.vehicle_id = currentVehicle;
        data.source = currentVehicle ? "vehicle page" : "website";

        try {
            const response = await fetch("/api/leads", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(data),
            });

            const status = document.querySelector("#form-status");
            if (status) {
                status.textContent = response.ok
                    ? "Thank you — your enquiry has been received."
                    : "Please check your name and email address.";
            }

            if (response.ok) form.reset();
        } catch (error) {
            console.error("Error submitting enquiry:", error);
            const status = document.querySelector("#form-status");
            if (status) status.textContent = "Something went wrong. Please try again.";
        }
    });
}

loadVehicles();
