(function () {
  "use strict";

  const RADIO_STREAM_URL = "https://listen9.myradio24.com/nastroike";

  function initHeaderRadio() {
    const root = document.querySelector("[data-radio-player]");
    if (!root || root.dataset.radioInitialized === "true") return;

    const audio = root.querySelector("[data-radio-audio]");
    const playButton = root.querySelector("[data-radio-play]");
    const status = root.querySelector("[data-radio-status]");

    if (!audio || !playButton || !status || !RADIO_STREAM_URL) return;

    root.dataset.radioInitialized = "true";
    root.hidden = false;

    function setState(state, message) {
      root.classList.toggle("is-loading", state === "loading");
      root.classList.toggle("is-playing", state === "playing");
      root.classList.toggle("is-error", state === "error");
      status.textContent = message;
      root.title = state === "error" ? message : "";
      playButton.setAttribute("aria-busy", String(state === "loading"));
    }

    function syncPlaybackUi() {
      const isPlaying = !audio.paused && !audio.ended;
      root.classList.toggle("is-playing", isPlaying);
      playButton.setAttribute("aria-pressed", String(isPlaying));
      playButton.setAttribute("aria-label", isPlaying ? "Остановить радио" : "Включить радио");
    }

    async function playRadio() {
      if (!audio.src) audio.src = RADIO_STREAM_URL;
      setState("loading", "Подключаемся к эфиру…");

      try {
        await audio.play();
      } catch (error) {
        setState("error", "Эфир временно недоступен. Нажмите, чтобы повторить.");
        syncPlaybackUi();
      }
    }

    playButton.addEventListener("click", () => {
      if (!audio.paused) {
        audio.pause();
        return;
      }

      if (root.classList.contains("is-error")) {
        audio.removeAttribute("src");
        audio.load();
      }

      playRadio();
    });

    audio.addEventListener("play", syncPlaybackUi);
    audio.addEventListener("pause", () => {
      syncPlaybackUi();
      setState("paused", "Радио остановлено");
    });
    audio.addEventListener("waiting", () => setState("loading", "Подключаемся к эфиру…"));
    audio.addEventListener("stalled", () => setState("loading", "Подключаемся к эфиру…"));
    audio.addEventListener("playing", () => {
      syncPlaybackUi();
      setState("playing", "Радио играет");
    });
    audio.addEventListener("error", () => {
      syncPlaybackUi();
      setState("error", "Эфир временно недоступен. Нажмите, чтобы повторить.");
    });

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
  }

  window.initHeaderRadio = initHeaderRadio;
})();
