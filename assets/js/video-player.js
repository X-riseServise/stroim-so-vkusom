(function () {
  let modal = null;
  let previousFocus = null;

  function buildRutubeEmbedUrl(rawUrl) {
    if (!rawUrl) return null;

    let url;

    try {
      url = new URL(rawUrl);
    } catch (error) {
      return null;
    }

    const host = url.hostname.toLowerCase().replace(/^www\./, "");

    if (host !== "rutube.ru") {
      return null;
    }

    const match = url.pathname.match(/^\/(?:play\/embed|video(?:\/private)?)\/([a-f\d]{32})\/?$/i);

    if (!match) {
      return null;
    }

    const embed = new URL(`https://rutube.ru/play/embed/${match[1].toLowerCase()}/`);
    const privateAccessKey = url.searchParams.get("p");

    if (privateAccessKey && /^[a-zA-Z0-9_-]+$/.test(privateAccessKey)) {
      embed.searchParams.set("p", privateAccessKey);
    }

    return embed.toString();
  }

  function buildVkEmbedUrl(rawUrl) {
    if (!rawUrl) return null;

    let url;

    try {
      url = new URL(rawUrl);
    } catch (error) {
      try {
        url = new URL(`https://${rawUrl}`);
      } catch (secondError) {
        return null;
      }
    }

    const host = url.hostname.toLowerCase().replace(/^www\./, "");

    if (!["vk.com", "vkvideo.ru"].includes(host)) {
      return null;
    }

    if (url.pathname === "/video_ext.php") {
      const oid = url.searchParams.get("oid");
      const id = url.searchParams.get("id");

      if (!oid || !id || !/^-?\d+$/.test(oid) || !/^\d+$/.test(id)) {
        return null;
      }

      const embed = new URL("https://vkvideo.ru/video_ext.php");
      embed.searchParams.set("oid", oid);
      embed.searchParams.set("id", id);

      const hash = url.searchParams.get("hash");
      if (hash && /^[a-zA-Z0-9_-]+$/.test(hash)) {
        embed.searchParams.set("hash", hash);
      }

      const hd = url.searchParams.get("hd");
      embed.searchParams.set("hd", hd && /^[0-4]$/.test(hd) ? hd : "4");
      return embed.toString();
    }

    const match = url.pathname.match(/\/video(-?\d+)_(\d+)/);

    if (!match) {
      return null;
    }

    const embed = new URL("https://vkvideo.ru/video_ext.php");
    embed.searchParams.set("oid", match[1]);
    embed.searchParams.set("id", match[2]);
    embed.searchParams.set("hd", "4");

    return embed.toString();
  }

  function buildVideoEmbedUrl(rawUrl) {
    return buildRutubeEmbedUrl(rawUrl) || buildVkEmbedUrl(rawUrl);
  }

  function getFocusableElements(container) {
    return Array.from(
      container.querySelectorAll(
        'a[href], button:not([disabled]), iframe, input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
      )
    ).filter((element) => element instanceof HTMLElement && !element.hidden);
  }

  function ensureModal() {
    if (modal) return modal;

    modal = document.createElement("div");
    modal.className = "video-modal";
    modal.hidden = true;

    const backdrop = document.createElement("button");
    backdrop.className = "video-modal__backdrop";
    backdrop.type = "button";
    backdrop.setAttribute("aria-label", "Закрыть видео");

    const dialog = document.createElement("div");
    dialog.className = "video-modal__dialog";
    dialog.setAttribute("role", "dialog");
    dialog.setAttribute("aria-modal", "true");
    dialog.setAttribute("aria-label", "Видео выпуска");

    const close = document.createElement("button");
    close.className = "video-modal__close";
    close.type = "button";
    close.setAttribute("aria-label", "Закрыть видео");
    close.textContent = "×";

    const frameWrap = document.createElement("div");
    frameWrap.className = "video-modal__frame";

    dialog.append(close, frameWrap);
    modal.append(backdrop, dialog);
    document.body.append(modal);

    backdrop.addEventListener("click", closeVideoModal);
    close.addEventListener("click", closeVideoModal);

    window.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && modal && !modal.hidden) {
        closeVideoModal();
      }

      if (event.key !== "Tab" || !modal || modal.hidden) return;

      const dialog = modal.querySelector(".video-modal__dialog");
      const focusable = dialog ? getFocusableElements(dialog) : [];

      if (!focusable.length) return;

      const first = focusable[0];
      const last = focusable[focusable.length - 1];

      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    });

    return modal;
  }

  function showUnavailableMessage(trigger) {
    const activeModal = ensureModal();
    const frameWrap = activeModal.querySelector(".video-modal__frame");
    const close = activeModal.querySelector(".video-modal__close");
    const message = document.createElement("p");

    message.className = "video-modal__message";
    message.textContent = "Видео временно недоступно.";

    frameWrap.replaceChildren(message);
    previousFocus = trigger || document.activeElement;
    activeModal.hidden = false;
    document.body.classList.add("is-video-modal-open");
    close.focus();
  }

  function openVideoModal(rawUrl, trigger) {
    const embedUrl = buildVideoEmbedUrl(rawUrl);

    if (!embedUrl) {
      showUnavailableMessage(trigger);
      return false;
    }

    const activeModal = ensureModal();
    const frameWrap = activeModal.querySelector(".video-modal__frame");
    const close = activeModal.querySelector(".video-modal__close");
    const iframe = document.createElement("iframe");

    iframe.src = embedUrl;
    iframe.title = "Видео выпуска";
    iframe.allow = "clipboard-write; autoplay; encrypted-media; fullscreen; picture-in-picture; screen-wake-lock";
    iframe.allowFullscreen = true;
    iframe.frameBorder = "0";
    iframe.referrerPolicy = "origin-when-cross-origin";

    frameWrap.replaceChildren(iframe);
    previousFocus = trigger || document.activeElement;
    activeModal.hidden = false;
    document.body.classList.add("is-video-modal-open");
    close.focus();

    return true;
  }

  function closeVideoModal() {
    if (!modal) return;

    modal.hidden = true;
    modal.querySelector(".video-modal__frame")?.replaceChildren();
    document.body.classList.remove("is-video-modal-open");

    if (previousFocus instanceof HTMLElement) {
      previousFocus.focus();
    }
  }

  window.StroimVideoPlayer = {
    buildRutubeEmbedUrl,
    buildVkEmbedUrl,
    buildVideoEmbedUrl,
    openVideoModal,
    closeVideoModal,
  };
})();
