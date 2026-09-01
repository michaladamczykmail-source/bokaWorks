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

/* Sync carousel dots with the projects track on mobile (scroll-snap) */
const track = document.getElementById("projectsTrack");
const dots = document.querySelectorAll("#projectsDots .dot");

if (track && dots.length) {
  const cards = Array.from(track.children);

  const setActive = (index) => {
    dots.forEach((dot, i) => dot.classList.toggle("is-active", i === index));
  };

  dots.forEach((dot) => {
    dot.addEventListener("click", () => {
      const index = Number(dot.dataset.index);
      cards[index]?.scrollIntoView({ behavior: "smooth", inline: "center", block: "nearest" });
    });
  });

  if ("IntersectionObserver" in window) {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            setActive(cards.indexOf(entry.target));
          }
        });
      },
      { root: track, threshold: 0.6 }
    );
    cards.forEach((card) => observer.observe(card));
  }
}

/* Cards are real <a> elements, so the whole tile is natively clickable,
   keyboard-focusable and works with middle-click / cmd-click out of the box. */
