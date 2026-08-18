if ("scrollRestoration" in window.history) {
  window.history.scrollRestoration = "manual";
}

const navigationEntry = window.performance?.getEntriesByType?.("navigation")?.[0];
const isReload = navigationEntry?.type === "reload";
const isBackForward = navigationEntry?.type === "back_forward";
const shouldResetReloadScroll = isReload && window.location.hash === "";

if (isBackForward && "scrollRestoration" in window.history) {
  window.history.scrollRestoration = "auto";
}

function resetReloadScroll() {
  if (!shouldResetReloadScroll) return;

  window.requestAnimationFrame(() => {
    window.requestAnimationFrame(() => {
      window.scrollTo(0, 0);
    });
  });
}

if (shouldResetReloadScroll) {
  window.scrollTo(0, 0);
  window.addEventListener("pageshow", resetReloadScroll);
}

const sectionsRoot = document.querySelector("#sections-root");
const assetVersion = "20260818";

const sectionPaths = [
  "sections/header.html",
  "sections/hero.html",
  "sections/about.html",
  "sections/episode-flow.html",
  "sections/latest-episode.html",
  "sections/episodes.html",
  "sections/partners.html",
  "sections/collaboration.html",
  "sections/footer.html",
];

async function loadSection(path) {
  const response = await fetch(`${path}?v=${assetVersion}`);

  if (!response.ok) {
    throw new Error(`Section load failed ${path}: ${response.status}`);
  }

  return response.text();
}

async function loadSections() {
  if (!sectionsRoot) return;

  const htmlParts = await Promise.all(
    sectionPaths.map(async (path) => {
      try {
        return await loadSection(path);
      } catch (error) {
        console.error(error);
        return "";
      }
    })
  );

  const availableSections = htmlParts.filter(Boolean);

  if (!availableSections.length) {
    sectionsRoot.innerHTML = '<p role="alert">Секции страницы временно недоступны.</p>';
    return;
  }

  sectionsRoot.innerHTML = availableSections.join("\n");
  resetReloadScroll();

  [
    initHeader,
    window.initHeaderRadio,
    initVideoStills,
    initEpisodeFlow,
    initVideoTriggers,
    initPartnersReveal,
    initFooterReveal,
    initPlaceholderLinks,
  ].filter(Boolean).forEach((initializer) => {
    try {
      initializer();
    } catch (error) {
      console.error(error);
    }
  });
}

function initVideoStills() {
  const stillVideos = document.querySelectorAll("[data-about-still], [data-video-still]");

  stillVideos.forEach((video) => {
    const time = Number(video.dataset.aboutStill || video.dataset.videoStill);

    if (!Number.isFinite(time)) return;

    video.addEventListener(
      "loadedmetadata",
      () => {
        video.currentTime = Math.min(time, Math.max(video.duration - 1, 0));
      },
      { once: true }
    );

    video.addEventListener("seeked", () => {
      video.pause();
      video.classList.add("is-ready");
    });
  });
}

function initEpisodeFlow() {
  const steps = document.querySelectorAll("[data-flow-step]");

  if (!steps.length) return;

  if (window.matchMedia("(prefers-reduced-motion: reduce)").matches || !("IntersectionObserver" in window)) {
    steps.forEach((step) => step.classList.add("is-visible"));
    return;
  }

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;

        entry.target.classList.add("is-visible");
        observer.unobserve(entry.target);
      });
    },
    { threshold: 0.28 }
  );

  steps.forEach((step, index) => {
    step.style.transitionDelay = `${index * 90}ms`;
    observer.observe(step);
  });
}

function initPlaceholderLinks() {
  document.querySelectorAll("[data-placeholder-link]").forEach((link) => {
    link.addEventListener("click", (event) => {
      event.preventDefault();
    });
  });
}

function initVideoTriggers() {
  document.querySelectorAll("[data-video-trigger]").forEach((trigger) => {
    const videoUrl = trigger.dataset.videoUrl?.trim();

    if (!videoUrl) {
      trigger.hidden = true;
      return;
    }

    trigger.addEventListener("click", (event) => {
      event.preventDefault();

      window.StroimVideoPlayer?.openVideoModal(videoUrl, trigger);
    });
  });
}

function initPartnersReveal() {
  const items = document.querySelectorAll("[data-partners-reveal]");

  if (!items.length) return;

  if (window.matchMedia("(prefers-reduced-motion: reduce)").matches || !("IntersectionObserver" in window)) {
    items.forEach((item) => item.classList.add("is-visible"));
    return;
  }

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;

        entry.target.classList.add("is-visible");
        observer.unobserve(entry.target);
      });
    },
    { threshold: 0.18 }
  );

  items.forEach((item, index) => {
    item.style.transitionDelay = `${Math.min(index * 80, 320)}ms`;
    observer.observe(item);
  });
}

function initFooterReveal() {
  const items = document.querySelectorAll("[data-footer-reveal]");

  if (!items.length) return;

  if (window.matchMedia("(prefers-reduced-motion: reduce)").matches || !("IntersectionObserver" in window)) {
    items.forEach((item) => item.classList.add("is-visible"));
    return;
  }

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;

        entry.target.classList.add("is-visible");
        observer.unobserve(entry.target);
      });
    },
    { threshold: 0.2 }
  );

  items.forEach((item, index) => {
    item.style.transitionDelay = `${Math.min(index * 110, 330)}ms`;
    observer.observe(item);
  });
}

function initHeader() {
  const header = document.querySelector("[data-header]");
  const menuToggle = document.querySelector("[data-menu-toggle]");
  const mobileNav = document.querySelector("[data-mobile-nav]");
  let lockedScrollY = 0;

  if (!header) return;

  header.classList.remove("is-menu-open");
  document.body.classList.remove("menu-open");
  document.body.style.top = "";
  lockedScrollY = 0;

  if (menuToggle && mobileNav) {
    menuToggle.setAttribute("aria-expanded", "false");
    menuToggle.setAttribute("aria-label", "Открыть меню");
    mobileNav.setAttribute("aria-hidden", "true");
  }

  function setScrolledState() {
    header.classList.toggle("is-scrolled", window.scrollY > 48);
  }

  function closeMobileMenu({ restoreFocus = false } = {}) {
    if (!menuToggle || !mobileNav) return;

    const wasOpen = header.classList.contains("is-menu-open");

    header.classList.remove("is-menu-open");
    document.body.classList.remove("menu-open");
    document.body.style.top = "";
    menuToggle.setAttribute("aria-expanded", "false");
    menuToggle.setAttribute("aria-label", "Открыть меню");
    mobileNav.setAttribute("aria-hidden", "true");

    if (wasOpen) {
      window.scrollTo(0, lockedScrollY);
      if (restoreFocus) menuToggle.focus();
    }
  }

  function targetFromHash(hash) {
    if (!hash || hash === "#") return null;

    try {
      return document.getElementById(decodeURIComponent(hash.slice(1)));
    } catch (error) {
      return null;
    }
  }

  function clearLocationHash() {
    if (!window.location.hash) return;

    window.history.replaceState(
      window.history.state,
      "",
      `${window.location.pathname}${window.location.search}`
    );
  }

  function scrollToTarget(target, smooth = true) {
    const behavior = smooth && !window.matchMedia("(prefers-reduced-motion: reduce)").matches ? "smooth" : "auto";
    target.scrollIntoView({ behavior, block: "start" });
  }

  function handleInternalNavigation(event) {
    const link = event.currentTarget;
    const target = targetFromHash(link.hash);

    if (!target) {
      event.preventDefault();
      clearLocationHash();
      return;
    }

    event.preventDefault();
    closeMobileMenu();
    clearLocationHash();
    requestAnimationFrame(() => scrollToTarget(target));
  }

  function handleInitialHash() {
    const hash = window.location.hash;
    if (!hash) return;

    if (isBackForward) return;

    const target = targetFromHash(hash);
    if (!target) {
      clearLocationHash();
      return;
    }

    requestAnimationFrame(() => {
      scrollToTarget(target, false);
    });
  }

  function toggleMobileMenu() {
    if (!menuToggle || !mobileNav) return;

    const isOpen = !header.classList.contains("is-menu-open");
    header.classList.toggle("is-menu-open", isOpen);
    menuToggle.setAttribute("aria-expanded", String(isOpen));
    menuToggle.setAttribute("aria-label", isOpen ? "Закрыть меню" : "Открыть меню");
    mobileNav.setAttribute("aria-hidden", String(!isOpen));

    if (isOpen) {
      lockedScrollY = window.scrollY;
      document.body.style.top = `-${lockedScrollY}px`;
      document.body.classList.add("menu-open");
      mobileNav.querySelector("a")?.focus({ preventScroll: true });
    } else {
      document.body.classList.remove("menu-open");
      document.body.style.top = "";
      window.scrollTo(0, lockedScrollY);
    }
  }

  setScrolledState();
  window.addEventListener("scroll", setScrolledState, { passive: true });

  if (menuToggle && mobileNav) {
    menuToggle.addEventListener("click", toggleMobileMenu);

    window.addEventListener("keydown", (event) => {
      if (event.key === "Escape") {
        closeMobileMenu({ restoreFocus: true });
      }
    });

    window.addEventListener("resize", () => {
      if (window.innerWidth > 900) closeMobileMenu();
    });
  }

  document.querySelectorAll('a[href^="#"]').forEach((link) => {
    link.addEventListener("click", handleInternalNavigation);
  });

  handleInitialHash();
}

loadSections();
