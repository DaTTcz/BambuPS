# BambuPS v0.2.3

## ✨ Novinky

### 🔔 Šablony notifikací
- Každý kanál (📧 e-mail, ✈️ Telegram, 🔌 MQTT) má pro každou událost **vlastní šablonu**: začátek tisku, dokončení, selhání, HMS varování a konec filamentu.
- Klikem na událost v **Nastavení → Notifikace** se otevře editor:
  - **text zprávy s proměnnými**, vkládají se klikem, např. `{printer}`, `{file}`, `{eta}`, `{filament}`, `{bed}`, `{error}`
  - **předmět e-mailu**
  - **přiložit obrázek ano/ne** (e-mail a Telegram)
  - **živý náhled** s ukázkovými daty, **zkušební odeslání** a **obnovení výchozí šablony**
- Upravené šablony mají u události štítek „upraveno“.
- Řádek, ve kterém jsou všechny proměnné prázdné, se nepošle. Když třeba chybí údaj o filamentu, řádek s filamentem ve zprávě není.
- Telegram podporuje formátování `<b>`, `<i>` a `<code>`. Když je HTML v šabloně rozbité, zpráva odejde aspoň jako čistý text.
- **Přepínače událostí se ukládají hned**, bez tlačítka Uložit.
- MQTT zpráva obsahuje navíc `vars` se všemi proměnnými, což se hodí pro automatizace v Home Assistantu.

### 🧩 Soubory z PrusaSliceru
- Podpora **`.bgcode`** (binární G-code), včetně náhledu a metadat.
- Rozpoznání G-code z **PrusaSliceru** vedle Orca Sliceru a Bambu Studia.
- **Víc nástrojů** (Prusa XL 5T): použité nástroje a filament po jednotlivých nástrojích.
- Náhled souboru po najetí myší ukazuje nástroje, např. „T4 z 5“.

### 📷 Vlastní kamera u tiskárny
- V nastavení tiskárny lze zadat **vlastní URL streamu a snímku**, např. kameru na externím Raspberry Pi.
- Vlastní kamera se použije pro živý obraz, snímky na konci tisku i obrázky v notifikacích.

### 🗂️ Detail souboru
- Stejné objekty na desce se slučují, např. `A.stl ×8, B.stl ×3`.

## 🐛 Opravy
- **Historie tisku bez náhledů**: snímky z kamery ukládá MQTT daemon a vznikaly s právy, kvůli kterým je web nepřečetl. Nové snímky se už ukládají čitelné.
- **Záložní náhled v historii**: když se snímek z kamery nenačte, zobrazí se náhled desky ze souboru, pak náhled souboru a nakonec ikonka.
- Snímek z kamery se uloží jen tehdy, když je to platný JPEG. Chybová stránka ani kus streamu se už jako náhled neuloží.
- **3MF projekty bez nakrájených desek**: zobrazí se všechny desky a jejich náhledy (dřív jen dvě). Průměr trysky se zobrazuje správně (`0.4` místo `0.40000000596046`).

## ⬆️ Aktualizace
Aktualizovat jde přímo z appky (**Verze a zálohy**). Databáze se předtím sama zazálohuje, proběhne migrace a restartují se služby.

**Jednorázově po aktualizaci** opravte práva u snímků uložených staršími verzemi:
```bash
cd /opt/bambups/app
sudo chmod 755 storage/app/private/print-history
sudo chmod 644 storage/app/private/print-history/*
```
Bez toho se u starších tisků v historii ukáže náhled desky místo fotky z kamery.

Při ruční aktualizaci navíc spusťte:
```bash
php artisan migrate --force
php artisan view:clear
sudo supervisorctl restart bambups-mqtt
```
