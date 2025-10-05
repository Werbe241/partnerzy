const CERT_DEFAULT = {
  role: "KR",
  personName: "Imię i Nazwisko",
  userId: "",
  issuedAt: new Date().toISOString().slice(0,10),
  validUntil: "bezterminowo",
  certNo: genCertNo("KR"),
  verifyBase: location.origin + "/wp-json/kk/v1/certificate/verify?cert_no="
};
let CERT_DATA = window.CERT_DATA || CERT_DEFAULT;

function roleTheme(role){
  if(role === "MR") return { cls:"theme-mr", title:"CERTYFIKAT MENADŻERA REGIONALNEGO", label:"Menadżer Regionalny" };
  if(role === "RT") return { cls:"theme-rt", title:"DYPLOM RENTIERA SYSTEMU WERBEKOORDINATOR", label:"Rentier Systemu" };
  return { cls:"theme-kr", title:"CERTYFIKAT KOORDYNATORA REKLAMY", label:"Koordynator Reklamy" };
}
function genCertNo(prefix){
  const d = new Date(); const y = d.getFullYear();
  const m = String(d.getMonth()+1).padStart(2,"0"); const day = String(d.getDate()).padStart(2,"0");
  const rnd = Math.floor(Math.random()*9000)+1000; return `${prefix}-${y}${m}${day}-${rnd}`;
}

function init(){
  const role = (CERT_DATA.role || "KR").toUpperCase();
  const theme = roleTheme(role);
  const root = document.getElementById("cert");
  root.classList.remove("theme-kr","theme-mr","theme-rt");
  root.classList.add(theme.cls);

  document.getElementById("title").textContent = theme.title;
  document.getElementById("roleLabel").textContent = theme.label;
  document.getElementById("personName").textContent = CERT_DATA.personName || CERT_DEFAULT.personName;
  document.getElementById("userId").textContent = CERT_DATA.userId || "";
  document.getElementById("certNo").textContent = CERT_DATA.certNo || genCertNo(role);
  document.getElementById("issueDate").textContent = CERT_DATA.issuedAt || new Date().toISOString().slice(0,10);
  document.getElementById("validUntil").textContent = CERT_DATA.validUntil || "bezterminowo";

  const verifyUrl = (CERT_DATA.verifyBase || CERT_DEFAULT.verifyBase) + encodeURIComponent(CERT_DATA.certNo || genCertNo(role));
  document.getElementById("verifyUrl").textContent = verifyUrl.replace(/^https?:\/\//,'');
  const qrEl = document.getElementById("qr");
  if(window.QRCode){
    qrEl.innerHTML = "";
    new QRCode(qrEl, { text: verifyUrl, width: 120, height: 120, correctLevel: QRCode.CorrectLevel.M });
  }
  try { parent.postMessage({ type:"CERT_READY", certNo: CERT_DATA.certNo }, "*"); } catch(e){}
}
document.addEventListener("DOMContentLoaded", init);
window.addEventListener("message", (e)=>{
  if(e.data && e.data.type==="CERT_DATA"){
    CERT_DATA = e.data.payload || CERT_DEFAULT;
    init();
  }
});

async function downloadPNG(){
  const node = document.getElementById("cert");
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${node.offsetWidth}" height="${node.offsetHeight}"><foreignObject width="100%" height="100%">${new XMLSerializer().serializeToString(node)}</foreignObject></svg>`;
  const blob = new Blob([svg], {type: "image/svg+xml;charset=utf-8"});
  const url = URL.createObjectURL(blob);
  const img = new Image();
  img.onload = () => {
    const canvas = document.createElement("canvas");
    canvas.width = node.offsetWidth * 2; canvas.height = node.offsetHeight * 2;
    const ctx = canvas.getContext("2d"); ctx.scale(2,2);
    ctx.fillStyle = "#fff"; ctx.fillRect(0,0,canvas.width,canvas.height);
    ctx.drawImage(img,0,0); URL.revokeObjectURL(url);
    canvas.toBlob(b=>{
      const a = document.createElement("a");
      a.href = URL.createObjectURL(b);
      a.download = (CERT_DATA.certNo || "certyfikat") + ".png";
      a.click(); setTimeout(()=>URL.revokeObjectURL(a.href), 1000);
    },"image/png", 0.92);
  };
  img.src = url;
}