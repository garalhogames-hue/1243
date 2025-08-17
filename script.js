function getInfo() {
  $.getJSON("//metropolys.ovh/player/php/status.php", function (obj) {
    if (obj.locutor !== " Radio Habblive") {
      $("#programacao span").html(obj.programa.substring(0, 25));

      $("#locutor span").html(obj.locutor);

      $("#avatar").css("background-image", player.avatarUrl.replace("%username%", obj.locutor.trim().replace(/^ /, '')));
    } else {
      $("#programacao span").html("Tocando as melhores!");
      $("#locutor span").html("AutoDJ");
      $("#avatar").css("background-image", player.avatarUrl.replace("%username%", "Blume"));
    }
    $("#ouvintes span").html(obj.ouvintes);
  });
}
<script>
/** CONFIG **/
const STATUS_URL = "/status.php"; // seu PHP acima
const STREAM_URL = "http://sonicpanel.oficialserver.com:8342/;"; // troque se quiser
const POLL_MS = 8000; // atualiza a cada 8s

/** SELETORES **/
const elProg = document.querySelector("#programacao span");
const elLoc  = document.querySelector("#locutor span");
const elOuv  = document.querySelector("#ouvintes span");
const elAv   = document.querySelector("#avatar");
const audio  = document.querySelector("#player");

const btnPlay  = document.querySelector("[data-action='play']");
const btnPause = document.querySelector("[data-action='pause']");

/** (Opcional) Template para avatar do Habbo */
const AVATAR_TEMPLATE = "https://www.habbo.com.br/habbo-imaging/avatarimage?user=%username%&direction=2&head_direction=3&size=b&action=std";

/** UTILS **/
const norm = (v, fb="") => (v ?? "").toString().trim() || fb;
const isAuto = name => !name || /^autodj$/i.test(name.trim());

function updateUI(data) {
  const locutor  = norm(data.locutor, "AutoDJ");
  const programa = norm(data.programa, "Tocando as melhores!");
  const ouvintes = Number.isFinite(data.ouvintes) ? data.ouvintes : 0;

  const auto = isAuto(locutor);
  if (elProg) elProg.textContent = auto ? "Tocando as melhores!" : programa.slice(0, 40);
  if (elLoc)  elLoc.textContent  = auto ? "AutoDJ" : locutor;
  if (elOuv)  elOuv.textContent  = ouvintes;

  if (elAv && AVATAR_TEMPLATE) {
    const user = encodeURIComponent((auto ? "michael" : locutor).replace(/^ /, ""));
    elAv.style.backgroundImage = `url(${AVATAR_TEMPLATE.replace("%username%", user)})`;
  }
}

async function fetchStatus() {
  const r = await fetch(STATUS_URL, { cache: "no-store" });
  if (!r.ok) throw new Error("status_unavailable");
  return r.json();
}

async function refresh() {
  try {
    const data = await fetchStatus();
    updateUI(data);
  } catch (e) {
    // Se quiser, marque UI como offline aqui
    // console.warn(e);
  }
}

/** Sempre ao vivo ao tocar/despausar **/
function forceLiveAndPlay() {
  if (!audio) return;
  audio.src = `${STREAM_URL}?t=${Date.now()}`; // busta cache/buffer
  audio.load();
  audio.play().catch(() => {});
}

/** Eventos dos botões **/
btnPlay?.addEventListener("click", forceLiveAndPlay);
btnPause?.addEventListener("click", () => audio?.pause());

/** Se o usuário der "play" após pausar, garanta live */
audio?.addEventListener("play", () => {
  // Se já tinha áudio carregado, recria a URL para ficar no ponto mais ao vivo
  if (audio.currentTime > 0) forceLiveAndPlay();
});

/** Boot **/
refresh();
setInterval(refresh, POLL_MS);
</script>
