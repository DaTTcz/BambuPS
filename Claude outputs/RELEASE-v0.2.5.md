# BambuPS v0.2.5

## ✨ Novinky

### 🌡️ Teplota trysky před tiskem
- V dialogu tisku jde u každého filamentu změnit **teplotu trysky pro 1. vrstvu a pro další vrstvy**, každou zvlášť. Hodí se, když stejný model tiskneš z jiného filamentu stejného materiálu.
- Upraví se jen **dočasná kopie** souboru, originál v Souborech zůstává beze změny. Funguje to i dohromady se změnou podložky.
- Mění se jen příkazy `M104` a `M109` s teplotou filamentu. Ostatní teploty ze startovního gcode zůstanou, například ohřev pro čištění trysky nebo vyfukování při výměně filamentu.
- Když má soubor obě teploty stejné a ty je nastavíš různě, appka vloží změnu teploty na začátek 2. vrstvy.
- Šipkou ↺ vrátíš teploty ze souboru.
- Omezení: u vícebarevného tisku jde 1. vrstvu nastavit jinak než ostatní jen tehdy, když to tak má už soubor ze sliceru.

### 🕒 Časová zóna
- Nová stránka **Nastavení → ⚙️ Obecné** s výběrem časové zóny. Výchozí je `Europe/Prague`.
- Časy v appce i v notifikacích se zobrazují v místním čase a **letní a zimní čas se přepíná sám**.
- Data se dál ukládají v UTC, takže změna zóny se projeví i ve staré historii.

### 🗂️ Detail souboru
- Dlaždice **Teplota trysky** ukazuje i teplotu 1. vrstvy: „220°C / první vrstva 225°C“. U starších souborů se teplota 1. vrstvy objeví po znovunačtení souboru.

## 🐛 Opravy
- Časy v notifikacích a v appce byly o hodinu posunuté (zobrazovaly se v UTC).
- AMS hlášky pro **sloty 2–4** se zobrazovaly jako „Neznámá chyba (kód: …)“. Teď mají srozumitelný text, např. „AMS Slot 4: došel filament. Probíhá čištění starého filamentu.“

## ⬆️ Aktualizace
Aktualizovat jde přímo z appky (**Verze a zálohy**). Databáze se předtím sama zazálohuje a služby se restartují. Migrace není potřeba.

Při ruční aktualizaci:
```bash
php artisan config:clear
php artisan view:clear
sudo supervisorctl restart bambups-mqtt
```

Po aktualizaci zkontroluj zónu v **Nastavení → Obecné**. Kdo je jinde než v Česku, nastaví si tam svou.
