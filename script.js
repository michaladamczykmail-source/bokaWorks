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
