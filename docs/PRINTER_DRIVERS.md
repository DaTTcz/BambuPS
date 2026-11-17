# Ovladače tiskáren

BambuPS umí pracovat s různými typy tiskáren přes **ovladače**. Ovladač je
jediné místo, které ví, jak s daným typem tiskárny mluvit (Bambu MQTT/FTPS,
PrusaLink HTTP, Moonraker…). Zbytek appky (dashboard, detail tiskárny,
dialog tisku, historie, notifikace) pracuje jen se společným rozhraním.

Kdo chce přidat podporu pro svou tiskárnu, napíše jeden ovladač a nemusí
zasahovat do zbytku appky.

## Struktura

```
app/Printers/
├── Contracts/PrinterDriver.php   rozhraní, které ovladač implementuje
├── AbstractPrinterDriver.php     základ: všechno neumím = false / null
├── Capability.php                seznam schopností (AMS, světlo, kalibrace…)
├── PrintRequest.php              požadavek na tisk (soubor, deska, sloty, volby)
├── PrinterDrivers.php            registr ovladačů
└── Drivers/
    └── Bambu/BambuDriver.php     ovladač Bambu Lab (LAN)
config/printers.php               seznam zapnutých ovladačů
```

Tiskárna má v databázi sloupec `driver` (klíč ovladače, např. `bambu`) a
`connection` (šifrované nastavení připojení – API klíč, adresa…).
V kódu se k ovladači dostaneš přes `$printer->driver()`:

```php
$printer->driver()->pause();
$printer->supports(Capability::LIGHT);   // umí tiskárna světlo?
```

## Jak přidat ovladač

1. Vytvoř třídu v `app/Printers/Drivers/<Nazev>/<Nazev>Driver.php`, která
   dědí `AbstractPrinterDriver`.
2. Doplň statické metody:
   - `key()` – krátký jedinečný klíč (`prusalink`, `moonraker`…),
   - `label()`, `description()` – texty pro výběr typu tiskárny,
   - `connectionFields()` – pole formuláře pro připojení (IP, API klíč…);
     formulář se z nich sestaví sám,
   - `capabilities()` – co tiskárna umí (konstanty z `Capability`),
   - `fileTypes()` – jaké soubory tiskne (`['gcode', 'bgcode']`).
3. Implementuj metody, které tiskárna podporuje: `uploadFile()`,
   `startPrint()`, `pause()`, `resume()`, `stop()`, teploty, ventilátory,
   pohyb, `cameraSource()`… Co neimplementuješ, vrací `false` a appka
   příslušné tlačítko skryje podle `capabilities()`.
4. Zaregistruj třídu v `config/printers.php`, případně ze service provideru:
   `PrinterDrivers::register(MujDriver::class);`

Nastavení připojení čti přes `$this->conn('api_key')` – hodnota se vezme
z `printers.connection`, případně ze sloupce tiskárny.

## Stav tiskárny

Ovladač ukládá aktuální stav do `printers.status` (JSON) a obnovuje
`printers.last_seen_at`. Appka čte tyto klíče – ovladač vyplní ty, které
tiskárna zná, ostatní vynechá:

| Klíč | Význam |
|---|---|
| `gcode_state` | `IDLE`, `PREPARE`, `RUNNING`, `PAUSE`, `FINISH`, `FAILED` |
| `mc_percent` | průběh tisku 0–100 |
| `mc_remaining_time` | zbývající čas v minutách |
| `layer_num`, `total_layer_num` | aktuální a celkový počet vrstev |
| `subtask_name` | název tištěného souboru / úlohy |
| `task_id` | ID úlohy (páruje se s historií tisku) |
| `temperatures.nozzle_temper`, `.nozzle_target_temper` | tryska (°C) |
| `temperatures.bed_temper`, `.bed_target_temper` | podložka (°C) |
| `temperatures.chamber_temper` | komora (°C), volitelné |
| `fans.*` | rychlosti ventilátorů, volitelné |
| `spd_mag` | rychlost tisku v % |
| `lights` | `[{node, mode}]`, volitelné |
| `ams` | zásobník filamentů (tvar podle Bambu AMS), volitelné |
| `print_error`, `hms` | chyby tiskárny, volitelné |

Názvy klíčů pocházejí z Bambu, protože Bambu byl první ovladač. Ostatní
ovladače na ně převádějí své hodnoty (např. PrusaLink `PRINTING` → `RUNNING`).

## Plán

- [x] Rozhraní ovladače, registr, Bambu jako první ovladač
- [ ] Samostatný proces pro každou tiskárnu (dnes MQTT listener obsluhuje
      tiskárny postupně)
- [ ] Formulář tiskárny sestavený z `connectionFields()`, výběr typu
- [ ] PrusaLink (Prusa Core One, XL, MK4…)
- [ ] Moonraker / Klipper (Voron a další)
