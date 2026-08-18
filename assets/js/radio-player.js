(function () {
  "use strict";

  const RADIO_STREAM_URL = "https://listen9.myradio24.com/nastroike";
  const STORAGE_VOLUME_KEY = "stroim-radio-volume";

  function readStoredVolume() {
    try {
      return localStorage.getItem(STORAGE_VOLUME_KEY);
    } catch (error) {
      return null;
    }
  }

  function storeVolume(value) {
    try {
      localStorage.setItem(STORAGE_VOLUME_KEY, value);
    } catch (error) {
      // Private browsing or storage policy may disable localStorage.
    }
  }

  function initRadioPlayer(root) {
    if (!root || root.dataset.radioInitialized === "true") return;

    root.dataset.radioInitialized = "true";

    if (!RADIO_STREAM_URL) {
      root.hidden = true;
      return;
    }

    const audio = root.querySelector("[data-radio-audio]");
    const playButton = root.querySelector("[data-radio-play]");
    const playIcon = root.querySelector("[data-radio-play-icon]");
    const muteButton = root.querySelector("[data-radio-mute]");
    const muteIcon = root.querySelector("[data-radio-mute-icon]");
    const volumeInput = root.querySelector("[data-radio-volume]");
    const status = root.querySelector("[data-radio-status]");
    const retryButton = root.querySelector("[data-radio-retry]");
    const collapseButton = root.querySelector("[data-radio-collapse]");
    const expandButton = root.querySelector("[data-radio-expand]");

    if (!audio || !playButton || !status) return;

    let storedVolume = Number.parseFloat(readStoredVolume() || "0.75");
    if (!Number.isFinite(storedVolume)) storedVolume = 0.75;
    storedVolume = Math.min(1, Math.max(0, storedVolume));

    audio.volume = storedVolume;
    volumeInput.value = String(storedVolume);
    root.hidden = false;

    function setStatus(message, state) {
      status.textContent = message;
      root.classList.toggle("is-loading", state === "loading");
      root.classList.toggle("is-playing", state === "playing");
      root.classList.toggle("is-error", state === "error");
      retryButton.hidden = state !== "error";
    }

    function syncPlaybackUi() {
      const isPlaying = !audio.paused && !audio.ended;
      playIcon.textContent = isPlaying ? "❚❚" : "▶";
      playButton.setAttribute("aria-label", isPlaying ? "Поставить радио на паузу" : "Включить Радио на Стройке");
      root.classList.toggle("is-playing", isPlaying);
    }

    function syncMuteUi() {
      const muted = audio.muted || audio.volume === 0;
      muteIcon.textContent = muted ? "MUTE" : "VOL";
      muteButton.setAttribute("aria-label", muted ? "Включить звук" : "Выключить звук");
    }

    if (window.location.protocol === "https:" && RADIO_STREAM_URL.startsWith("http://")) {
      playButton.disabled = true;
      setStatus("Эфир временно недоступен", "error");
      retryButton.hidden = true;
      return;
    }

    async function playRadio() {
      if (!audio.src) audio.src = RADIO_STREAM_URL;
      setStatus("Подключаемся к эфиру…", "loading");

      try {
        await audio.play();
      } catch (error) {
        setStatus("Эфир временно недоступен", "error");
      }
    }

    playButton.addEventListener("click", () => {
      if (audio.paused) playRadio();
      else audio.pause();
    });

    retryButton.addEventListener("click", () => {
      audio.removeAttribute("src");
      audio.load();
      playRadio();
    });

    muteButton.addEventListener("click", () => {
      audio.muted = !audio.muted;
      syncMuteUi();
    });

    volumeInput.addEventListener("input", () => {
      audio.volume = Number(volumeInput.value);
      audio.muted = false;
      storeVolume(String(audio.volume));
      syncMuteUi();
    });

    collapseButton.addEventListener("click", () => {
      root.classList.add("is-collapsed");
      collapseButton.setAttribute("aria-expanded", "false");
      expandButton.focus();
    });

    expandButton.addEventListener("click", () => {
      root.classList.remove("is-collapsed");
      collapseButton.setAttribute("aria-expanded", "true");
      collapseButton.focus();
    });

    audio.addEventListener("play", syncPlaybackUi);
    audio.addEventListener("pause", () => {
      syncPlaybackUi();
      setStatus("Эфир приостановлен", "paused");
    });
    audio.addEventListener("waiting", () => setStatus("Подключаемся к эфиру…", "loading"));
    audio.addEventListener("stalled", () => setStatus("Подключаемся к эфиру…", "loading"));
    audio.addEventListener("playing", () => {
      syncPlaybackUi();
      setStatus("Прямой эфир", "playing");
    });
    audio.addEventListener("error", () => {
      syncPlaybackUi();
      setStatus("Эфир временно недоступен", "error");
    });
    audio.addEventListener("volumechange", syncMuteUi);

    if ("mediaSession" in navigator && "MediaMetadata" in window) {
      try {
        navigator.mediaSession.metadata = new MediaMetadata({
          title: "Радио на Стройке",
          artist: "Прямой эфир",
          artwork: [{ src: "/assets/images/logo/radionastroyke.svg", type: "image/svg+xml" }],
        });
        navigator.mediaSession.setActionHandler("play", playRadio);
        navigator.mediaSession.setActionHandler("pause", () => audio.pause());
      } catch (error) {
        // Media Session support varies; audio playback remains available.
      }
    }

    syncPlaybackUi();
    syncMuteUi();
  }

  function findAndInit() {
    const root = document.querySelector("[data-radio-player]");
    if (!root) return false;
    initRadioPlayer(root);
    return true;
  }

  if (!findAndInit()) {
    const observer = new MutationObserver(() => {
      if (findAndInit()) observer.disconnect();
    });
    observer.observe(document.documentElement, { childList: true, subtree: true });
  }
})();
