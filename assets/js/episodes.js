(function () {
  const api = {
    latest: "/api/latest-episode.php",
    episodes: "/api/episodes.php",
    episode: "/api/episode.php",
  };

  function createElement(tag, className, text) {
    const element = document.createElement(tag);

    if (className) {
      element.className = className;
    }

    if (text !== undefined && text !== null) {
      element.textContent = text;
    }

    return element;
  }

  async function fetchJson(url) {
    const response = await fetch(url, {
      headers: { Accept: "application/json" },
    });

    const data = await response.json().catch(() => null);

    if (!response.ok || !data || data.success === false) {
      throw new Error(data?.error?.message || "API error");
    }

    return data;
  }

  function formatEpisodeNumber(episode) {
    return episode?.number_label || String(episode?.number || "").padStart(2, "0");
  }

  function formatHeading(value) {
    return String(value || "").replace(/\.+\s*$/, "");
  }

  function formatDate(value) {
    if (!value) return "Дата скоро";

    const normalized = value.includes("T") ? value : value.replace(" ", "T");
    const date = new Date(normalized);

    if (Number.isNaN(date.getTime())) {
      return value;
    }

    return new Intl.DateTimeFormat("ru-RU", {
      day: "2-digit",
      month: "long",
      year: "numeric",
    }).format(date);
  }

  function setImage(image, src, alt) {
    if (!image) return;

    image.src = src || "assets/images/episode-01-cover-v2.png";
    image.alt = alt || "Обложка выпуска";
  }

  function bindVideoTriggers(root, episode) {
    const triggers = root.querySelectorAll("[data-video-trigger]");
    const embedUrl = window.StroimVideoPlayer?.buildVkEmbedUrl(episode.vk_video_url);

    triggers.forEach((trigger) => {
      trigger.disabled = !embedUrl;
      trigger.setAttribute(
        "aria-label",
        embedUrl ? `Смотреть выпуск ${formatEpisodeNumber(episode)} с ${episode.guest.name}` : "Видео пока недоступно"
      );

      trigger.addEventListener("click", () => {
        window.StroimVideoPlayer?.openVideoModal(episode.vk_video_url, trigger);
      });
    });
  }

  function renderLatestEpisode(episode) {
    const root = document.querySelector("[data-latest-episode]");
    if (!root) return;

    const number = formatEpisodeNumber(episode);
    root.classList.remove("is-loading", "is-empty", "is-error");

    root.querySelector("[data-latest-label]").textContent = `ВЫПУСК / ${number}`;
    root.querySelector("[data-latest-title]").textContent = formatHeading(episode.title);
    root.querySelector("[data-latest-guest-line]").textContent = `В гостях — ${episode.guest.name}`;
    root.querySelector("[data-latest-number]").textContent = `ВЫПУСК #${number}`;
    root.querySelector("[data-latest-guest]").textContent = episode.guest.name;
    root.querySelector("[data-latest-role]").textContent = episode.guest.position || "";
    root.querySelector("[data-latest-description]").textContent = episode.description;
    root.querySelector("[data-latest-meta-number]").textContent = `Выпуск #${number}`;
    root.querySelector("[data-latest-meta-guest]").textContent = `Гость: ${episode.guest.name}`;

    setImage(
      root.querySelector("[data-latest-cover]"),
      episode.cover_image,
      `Обложка выпуска ${number} с гостем ${episode.guest.name}`
    );
    bindVideoTriggers(root, episode);

    root.dispatchEvent(new CustomEvent("cms:latest-loaded", { detail: { episode } }));
  }

  function renderLatestEmpty() {
    const root = document.querySelector("[data-latest-episode]");
    if (!root) return;

    root.classList.remove("is-loading", "is-error");
    root.classList.add("is-empty");
    root.querySelector("[data-latest-label]").textContent = "ВЫПУСК / СКОРО";
    root.querySelector("[data-latest-title]").textContent = "Первый выпуск скоро появится";
    root.querySelector("[data-latest-guest-line]").textContent = "Мы готовим публикацию";
    root.querySelector("[data-latest-number]").textContent = "СКОРО";
    root.querySelector("[data-latest-guest]").textContent = "Выпуск в подготовке";
    root.querySelector("[data-latest-role]").textContent = "";
    root.querySelector("[data-latest-description]").textContent = "Первый выпуск скоро появится.";
    root.querySelector("[data-latest-meta-number]").textContent = "Выпуск скоро";
    root.querySelector("[data-latest-meta-guest]").textContent = "Гость будет объявлен";
    root.querySelectorAll("[data-video-trigger]").forEach((trigger) => {
      trigger.disabled = true;
    });
  }

  function renderLatestError() {
    const root = document.querySelector("[data-latest-episode]");
    if (!root) return;

    root.classList.remove("is-loading", "is-empty");
    root.classList.add("is-error");
    root.querySelector("[data-latest-title]").textContent = "Последний выпуск";
    root.querySelector("[data-latest-guest-line]").textContent = "Информация временно недоступна";
    root.querySelector("[data-latest-description]").textContent = "Не удалось загрузить информацию о выпуске.";
    root.querySelectorAll("[data-video-trigger]").forEach((trigger) => {
      trigger.disabled = true;
    });
  }

  function createEpisodeCard(episode) {
    const number = formatEpisodeNumber(episode);
    const card = createElement("article", "episodes__card");
    const imageWrap = createElement("div", "episodes__image");
    const image = document.createElement("img");

    setImage(image, episode.cover_image, `Обложка выпуска ${number} с гостем ${episode.guest.name}`);
    image.loading = "lazy";
    imageWrap.append(image);

    const content = createElement("div", "episodes__card-content");
    const meta = createElement("p", "episodes__meta", `Выпуск #${number} / ${formatDate(episode.published_at)}`);
    const title = createElement("h3", "", formatHeading(episode.title));
    const guest = createElement("p", "episodes__guest", episode.guest.name);
    const description = createElement("p", "episodes__description", episode.description);
    const link = createElement("a", "episodes__more", "Подробнее");

    link.href = `/api/episode.php?id=${encodeURIComponent(episode.id)}`;
    link.target = "_blank";
    link.rel = "noopener noreferrer";
    link.setAttribute("aria-label", `Открыть JSON выпуска ${number}`);

    content.append(meta, title, guest, description, link);
    card.append(imageWrap, content);

    return card;
  }

  function renderEpisodes(episodes) {
    const root = document.querySelector("[data-episodes]");
    if (!root) return;

    const status = root.querySelector("[data-episodes-status]");
    const list = root.querySelector("[data-episodes-list]");

    list.replaceChildren();

    if (!episodes.length) {
      status.textContent = "Опубликованные выпуски скоро появятся.";
      list.hidden = true;
      return;
    }

    episodes.forEach((episode) => {
      list.append(createEpisodeCard(episode));
    });

    status.textContent = "";
    status.hidden = true;
    list.hidden = false;
  }

  function renderEpisodesError() {
    const root = document.querySelector("[data-episodes]");
    if (!root) return;

    const status = root.querySelector("[data-episodes-status]");
    const list = root.querySelector("[data-episodes-list]");

    status.hidden = false;
    status.textContent = "Не удалось загрузить список выпусков.";
    list.hidden = true;
  }

  async function loadLatestEpisode() {
    try {
      const data = await fetchJson(api.latest);

      if (!data.episode) {
        renderLatestEmpty();
        return;
      }

      renderLatestEpisode(data.episode);
    } catch (error) {
      renderLatestError();
    }
  }

  async function loadEpisodes() {
    try {
      const data = await fetchJson(api.episodes);
      renderEpisodes(data.episodes || []);
    } catch (error) {
      renderEpisodesError();
    }
  }

  window.initCmsEpisodes = function initCmsEpisodes() {
    loadLatestEpisode();
    loadEpisodes();
  };
})();
