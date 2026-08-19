(function () {
  "use strict";

  const RADIO_STREAM_URL = "https://myradio24.org/nastroike";

  function initHeaderRadio() {
    const root = document.querySelector("[data-radio-player]");
    if (!root || root.dataset.radioInitialized === "true") return;

    const audio = root.querySelector("[data-radio-audio]");
    const playButton = root.querySelector("[data-radio-play]");
    const status = root.querySelector("[data-radio-status]");
    let desiredPlayback = false;
    let currentState = "paused";

    if (!audio || !playButton || !status || !RADIO_STREAM_URL) return;

    root.dataset.radioInitialized = "true";
    root.hidden = false;

    function setState(state, message) {
      currentState = state;
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

    function logPlaybackError(context, error = null) {
      const mediaError = audio.error;
      console.error("Radio playback error", {
        context,
        streamUrl: RADIO_STREAM_URL,
        currentSrc: audio.currentSrc || audio.src || null,
        mediaErrorCode: mediaError?.code ?? null,
        mediaErrorMessage: mediaError?.message || null,
        errorName: error?.name || null,
        errorMessage: error?.message || null,
      });
    }

    function resetStreamSource() {
      audio.pause();
      audio.removeAttribute("src");
      audio.load();
      audio.src = RADIO_STREAM_URL;
      audio.load();
    }

    async function playRadio() {
      const needsReset = !audio.src || currentState === "error";
      desiredPlayback = true;
      setState("loading", "Подключаемся к эфиру…");

      if (needsReset) {
        resetStreamSource();
      }

      try {
        await audio.play();
      } catch (error) {
        desiredPlayback = false;
        logPlaybackError("play() rejected", error);
        setState("error", "Эфир временно недоступен. Нажмите, чтобы повторить.");
        syncPlaybackUi();
      }
    }

    function pauseRadio() {
      desiredPlayback = false;
      audio.pause();
      audio.removeAttribute("src");
      audio.load();
      syncPlaybackUi();
      setState("paused", "Радио остановлено");
    }

    playButton.addEventListener("click", () => {
      if (desiredPlayback || !audio.paused) {
        pauseRadio();
        return;
      }

      playRadio();
    });

    audio.addEventListener("play", () => {
      syncPlaybackUi();
      if (desiredPlayback && currentState !== "playing") {
        setState("loading", "Подключаемся к эфиру…");
      }
    });
    audio.addEventListener("pause", () => {
      syncPlaybackUi();
      if (desiredPlayback || currentState === "error") return;
      setState("paused", "Радио остановлено");
    });
    audio.addEventListener("canplay", () => {
      if (desiredPlayback && currentState !== "playing") {
        setState("loading", "Эфир готов, запускаем…");
      }
    });
    audio.addEventListener("waiting", () => {
      if (desiredPlayback) setState("loading", "Подключаемся к эфиру…");
    });
    audio.addEventListener("stalled", () => {
      if (desiredPlayback) setState("loading", "Соединение с эфиром задержалось…");
    });
    audio.addEventListener("playing", () => {
      desiredPlayback = true;
      syncPlaybackUi();
      setState("playing", "Радио играет");
    });
    audio.addEventListener("error", () => {
      desiredPlayback = false;
      logPlaybackError("media element error");
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
        navigator.mediaSession.setActionHandler("pause", pauseRadio);
      } catch (error) {
        // Media Session support varies; audio playback remains available.
      }
    }

    syncPlaybackUi();
  }

  window.initHeaderRadio = initHeaderRadio;
})();
