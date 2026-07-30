<p align="center">
  <img src="public/images/bambups_logo.png" alt="BambuPS logo" width="200">
</p>

# BambuPS (Bambu Print Server)

Self-hosted webová appka pro správu tiskáren **Bambu Lab** (X1C, X1E, P1, A1 a další) přes lokální síť — bez závislosti na cloudu Bambu Lab.

Postavená na Laravel 13 + Livewire + Alpine.js/Tailwind CSS. Ovládá tiskárny přímo přes MQTT/FTP v tvé vlastní síti, ukládá a organizuje tiskové soubory, streamuje kamery, dekóduje chybové HMS kódy a posílá notifikace.

---

## ✨ Funkce

- **Správa tiskáren** — víc tiskáren najednou, live status (teploty, průběh tisku, WiFi signál...)
- **Tisk s AMS** — automatické i ruční mapování filamentů na AMS sloty, podpora více AMS jednotek
- **Volba typu podložky** za běhu — appka umí přepočítat a upravit teplotu podložky v gcode podle zvoleného typu, i po naslicování
- **Správa souborů** — organizace do složek, náhledy jednotlivých desek, přejmenování/přesun/smazání, opětovné naparsování metadat
- **Kamera** — živý stream přes [go2rtc](https://github.com/AlexxIT/go2rtc), automaticky se nastaví při přidání tiskárny
- **Notifikace** — e-mail, Telegram, MQTT při dokončení/selhání tisku, HMS chybách, docházejícím filamentu
- **HMS diagnostika** — dekódování chybových kódů tiskárny do srozumitelných hlášek (čeština)
- **Ovládání v reálném čase** — teploty, světla, pohyb os, rychlost tisku, pauza/pokračování/zastavení
- **Tmavý/světlý režim** s uložením preference
- **Automatická kontrola aktualizací** — appka si sama zkontroluje novou verzi na GitHubu a nabídne update jedním klikem

## 📋 Požadavky

- Ubuntu Server 22.04+ (nebo jiná Debian-based distribuce)
- Tiskárna Bambu Lab s aktivním **LAN Only Mode** a **Developer Mode** (Nastavení → síť na displeji tiskárny)
- Root/sudo přístup na serveru
- Server ve stejné síti jako tiskárna(y)

## 🚀 Instalace

Nejjednodušší cesta — stáhni a spusť instalátor:

```bash
curl -O https://raw.githubusercontent.com/DaTTcz/BambuPS/main/install-clean.sh
sudo bash install-clean.sh
```

Skript se zeptá na pár základních věcí (IP/doména serveru, porty) a zařídí kompletně vše:
- Nainstaluje závislosti (PHP 8.5, nginx, MariaDB, Node.js, Supervisor, ffmpeg, go2rtc)
- Naklonuje appku a nastaví databázi
- Vygeneruje self-signed SSL certifikát
- Nastaví nginx, PHP-FPM a supervisor démony
- Na konci tě interaktivně provede vytvořením prvního administrátorského účtu

Po dokončení appku najdeš na `https://<tvoje-IP>:<port>` a přihlásíš se účtem, který jsi právě vytvořil. Zbytek — přidání tiskáren, zapnutí modulů (kamera, MQTT, notifikace) — už probíhá přímo přes webové rozhraní appky.

## 🔄 Aktualizace

Appka si sama hlídá nové verze (odznak v horním menu). Kliknutím na "aktualizovat" appka:
1. Stáhne novou verzi z GitHubu (`git fetch` + `checkout`)
2. Aktualizuje PHP závislosti a přebuilduje frontend
3. Spustí databázové migrace
4. Vyčistí cache

Průběh vidíš živě v appce, žádný ruční zásah na serveru není potřeba.

## 🖨️ Kompatibilita

Vyvíjeno a testováno primárně na **Bambu Lab X1 Carbon** a **X1E**. Měla by fungovat i s P1/A1 řadou, ale bez záruky — pokud narazíš na problém specifický pro tvůj model, otevři prosím issue.

## 🛠️ Technologie

- **Backend:** PHP 8.5, Laravel 13, Jetstream, Livewire 3
- **Frontend:** Alpine.js, Tailwind CSS
- **Databáze:** MariaDB
- **Web server:** nginx + PHP-FPM
- **Procesy na pozadí:** Supervisor (MQTT listener, go2rtc, kamery)
- **MQTT klient:** [php-mqtt/client](https://github.com/php-mqtt/client)
- **Kamera:** [go2rtc](https://github.com/AlexxIT/go2rtc)

## 📖 Jak appka komunikuje s tiskárnou

BambuPS mluví s tiskárnou přímo přes lokální MQTT (port 8883, TLS) a FTPS (port 990) — stejný protokol, jaký používá oficiální Bambu Studio v LAN režimu. Žádná data neopouští tvoji síť.

Formát MQTT příkazů byl z velké části zpětně odvozen a ověřen porovnáním proti [OpenBambuAPI](https://github.com/Doridian/OpenBambuAPI) dokumentaci a [Bambuddy](https://github.com/maziggy/bambuddy) (děkujeme za inspiraci a otevřený zdrojový kód, který pomohl vyladit nejeden detail).

## ⚠️ Prohlášení

Tohle je neoficiální, komunitní projekt. Není přidružený k Bambu Lab. Používáš na vlastní riziko — appka posílá příkazy přímo tiskárně, a i když je to navržené bezpečně, doporučujeme mít oči na tiskárně při prvních tiscích s novou appkou.

## 📄 Licence

[PolyForm Noncommercial 1.0.0](LICENSE) — appku smíš volně používat, upravovat a sdílet pro nekomerční účely (osobní, vzdělávací, hobby). **Komerční využití vyžaduje svolení autora** — ozvi se přes GitHub, domluvíme se.

## 🙏 Poděkování

- [Bambu Lab](https://bambulab.com) za skvělé tiskárny
- Komunitě kolem [OpenBambuAPI](https://github.com/Doridian/OpenBambuAPI) a [Bambuddy](https://github.com/maziggy/bambuddy) za zpětně odvozenou dokumentaci protokolu
- Vytvořeno s pomocí Claude (Anthropic)

---

Vytvořil [David Trubka](https://github.com/DaTTcz)
