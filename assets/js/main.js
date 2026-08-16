const sectionsRoot = document.querySelector("#sections-root");

const sectionPaths = [
  "sections/header.html",
  "sections/hero.html",
  "sections/about.html",
  "sections/episode-flow.html",
  "sections/latest-episode.html",
  "sections/menu.html",
  "sections/host.html",
  "sections/guest.html",
  "sections/episodes.html",
  "sections/partners.html",
  "sections/final-cta.html",
  "sections/footer.html",
];

async function loadSection(path) {
  const response = await fetch(path);

  if (!response.ok) {
    throw new Error(`Не удалось загрузить ${path}: ${response.status}`);
  }

  return response.text();
}

async function loadSections() {
  if (!sectionsRoot) return;

  try {
    const htmlParts = [];

    for (const path of sectionPaths) {
      htmlParts.push(await loadSection(path));
    }

    sectionsRoot.innerHTML = htmlParts.join("\n");
    initHeader();
  } catch (error) {
    console.error(error);
    sectionsRoot.innerHTML = '<p role="alert">Не удалось загрузить секции страницы.</p>';
  }
}

function initHeader() {
  const header = document.querySelector("[data-header]");
  const menuToggle = document.querySelector("[data-menu-toggle]");
  const mobileNav = document.querySelector("[data-mobile-nav]");

  if (!header) return;

  function setScrolledState() {
    header.classList.toggle("is-scrolled", window.scrollY > 48);
  }

  function closeMobileMenu() {
    if (!menuToggle || !mobileNav) return;

    header.classList.remove("is-menu-open");
    menuToggle.setAttribute("aria-expanded", "false");
    menuToggle.setAttribute("aria-label", "Открыть меню");
  }

  function toggleMobileMenu() {
    if (!menuToggle || !mobileNav) return;

    const isOpen = !header.classList.contains("is-menu-open");
    header.classList.toggle("is-menu-open", isOpen);
    menuToggle.setAttribute("aria-expanded", String(isOpen));
    menuToggle.setAttribute("aria-label", isOpen ? "Закрыть меню" : "Открыть меню");
  }

  setScrolledState();
  window.addEventListener("scroll", setScrolledState, { passive: true });

  if (menuToggle && mobileNav) {
    menuToggle.addEventListener("click", toggleMobileMenu);

    mobileNav.querySelectorAll("a").forEach((link) => {
      link.addEventListener("click", closeMobileMenu);
    });

    window.addEventListener("keydown", (event) => {
      if (event.key === "Escape") {
        closeMobileMenu();
      }
    });
  }
}

loadSections();
