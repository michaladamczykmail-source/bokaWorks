document.getElementById("year").textContent = new Date().getFullYear();

/* Mobile nav toggle */
const navToggle = document.getElementById("navToggle");
const mainNav = document.getElementById("main-nav");

if (navToggle && mainNav) {
  navToggle.addEventListener("click", () => {
    const isOpen = mainNav.classList.toggle("is-open");
    navToggle.setAttribute("aria-expanded", String(isOpen));
  });

  mainNav.querySelectorAll("a").forEach((link) => {
    link.addEventListener("click", () => {
      mainNav.classList.remove("is-open");
      navToggle.setAttribute("aria-expanded", "false");
    });
  });
}

/* Cards are real <a> elements, so the whole tile is natively clickable,
   keyboard-focusable and works with middle-click / cmd-click out of the box. */

/* Contact form */
const contactForm = document.getElementById("contactForm");

if (contactForm) {
  const loadedAt = Date.now();
  const submitBtn = document.getElementById("cf-submit");
  const statusEl = document.getElementById("cf-status");
  const nameInput = document.getElementById("cf-name");
  const contactInput = document.getElementById("cf-contact");
  const consentInput = document.getElementById("cf-consent");
  const consentLabel = consentInput.closest(".consent-label");

  [nameInput, contactInput].forEach((input) => {
    input.addEventListener("input", () => input.classList.remove("invalid"));
  });
  consentInput.addEventListener("change", () => consentLabel.classList.remove("invalid"));

  contactForm.addEventListener("submit", async (e) => {
    e.preventDefault();

    const name = nameInput.value.trim();
    const contact = contactInput.value.trim();
    const message = document.getElementById("cf-message").value.trim();
    const consent = consentInput.checked;
    const website = document.getElementById("cf-website").value;

    statusEl.textContent = "";
    statusEl.removeAttribute("data-state");

    nameInput.classList.toggle("invalid", !name);
    contactInput.classList.toggle("invalid", !contact);
    consentLabel.classList.toggle("invalid", !consent);

    if (!name || !contact || !consent) {
      statusEl.textContent = "Uzupełnij imię, kontakt i zgodę na kontakt.";
      statusEl.dataset.state = "error";
      return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = "Wysyłanie…";

    try {
      const res = await fetch("api/contact.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          name,
          contact,
          message,
          consent,
          website,
          elapsed: Date.now() - loadedAt,
        }),
      });
      const data = await res.json().catch(() => ({}));

      if (res.ok && data.ok) {
        contactForm.reset();
        statusEl.textContent = "Dziękujemy! Wiadomość została wysłana.";
        statusEl.dataset.state = "ok";
      } else {
        statusEl.textContent = "Nie udało się wysłać. Spróbuj ponownie lub napisz na info@bokaworks.pl.";
        statusEl.dataset.state = "error";
      }
    } catch {
      statusEl.textContent = "Nie udało się wysłać. Spróbuj ponownie lub napisz na info@bokaworks.pl.";
      statusEl.dataset.state = "error";
    } finally {
      submitBtn.disabled = false;
      submitBtn.innerHTML =
        'Wyślij wiadomość<svg class="icon-arrow" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    }
  });
}

/* Cookie banner */
(() => {
  const COOKIE_CONSENT_KEY = "bokaworks_cookie_consent";
  const banner = document.getElementById("cookieBanner");
  if (!banner) return;

  let consent = null;
  try {
    consent = localStorage.getItem(COOKIE_CONSENT_KEY);
  } catch {}

  if (!consent) banner.hidden = false;

  const acceptBtn = document.getElementById("cookieAccept");
  if (acceptBtn) {
    acceptBtn.addEventListener("click", () => {
      try {
        localStorage.setItem(COOKIE_CONSENT_KEY, "accepted");
      } catch {}
      banner.hidden = true;
    });
  }
})();
