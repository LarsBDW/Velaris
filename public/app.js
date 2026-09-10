let currentVehicle = null;

const PLACEHOLDER_IMAGE = "/assets/vehicle-placeholder.png";

async function loadVehicles() {
    const searchInput = document.querySelector("#search");
    const carsContainer = document.querySelector("#cars");

    const query = searchInput.value.trim();

    try {
        const response = await fetch(
            `/api/vehicles?q=${encodeURIComponent(query)}`
        );

        if (!response.ok) {
            throw new Error("Failed to load vehicles");
        }

        const cars = await response.json();

        if (!cars.length) {
            carsContainer.innerHTML = `
                <p>
                    No vehicles match your current filters.
                    Tell Velaris what you are looking for.
                </p>
            `;
            return;
        }

        carsContainer.innerHTML = cars
            .map((car) => {
                // Never use the homepage hero image for vehicle cards.
                // If no real vehicle image is assigned, use the placeholder.
                const image =
                    car.image &&
                    car.image.trim() &&
                    !car.image.includes("vehicle-placeholder.png")
                        ? car.image
                        : PLACEHOLDER_IMAGE;

                return `
                    <article class="car">
                        <div
                            class="car-img"
                            style="background-image: url('${image}')"
                        >
                            <span class="badge">
                                ${car.featured ? "FEATURED" : "AVAILABLE"}
                            </span>
                        </div>

                        <div class="car-content">
                            <div class="car-make">
                                ${car.make}
                            </div>

                            <h3>
                                ${car.model} ${car.variant || ""}
                            </h3>

                            <div class="specs">
                                ${car.year}
                                &nbsp;·&nbsp;
                                ${Number(car.mileage).toLocaleString()} km
                                &nbsp;·&nbsp;
                                ${car.fuel}
                                <br>
                                ${car.transmission}
                                &nbsp;·&nbsp;
                                ${car.power} HP
                            </div>

                            <button
                                class="card-action"
                                onclick="openLead(
                                    ${car.id},
                                    'Request current price for ${car.make} ${car.model}'
                                )"
                            >
                                Request price →
                            </button>
                        </div>
                    </article>
                `;
            })
            .join("");
    } catch (error) {
        console.error("Error loading vehicles:", error);

        carsContainer.innerHTML = `
            <p>
                Unable to load vehicles right now.
                Please try again later.
            </p>
        `;
    }
}

function openLead(
    vehicleId = null,
    title = "Request current price"
) {
    currentVehicle = vehicleId;

    document.querySelector("#form-title").textContent = title;
    document.querySelector("#lead-modal").showModal();
}

function closeLead() {
    document.querySelector("#lead-modal").close();
}

document
    .querySelector("#lead-form")
    .addEventListener("submit", async (event) => {
        event.preventDefault();

        const form = event.target;
        const data = Object.fromEntries(new FormData(form));

        data.vehicle_id = currentVehicle;
        data.source = currentVehicle
            ? "vehicle page"
            : "website";

        try {
            const response = await fetch("/api/leads", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify(data),
            });

            document.querySelector("#form-status").textContent =
                response.ok
                    ? "Thank you — your enquiry has been received."
                    : "Please check your name and email address.";

            if (response.ok) {
                form.reset();
            }
        } catch (error) {
            console.error("Error submitting enquiry:", error);

            document.querySelector("#form-status").textContent =
                "Something went wrong. Please try again.";
        }
    });

loadVehicles();