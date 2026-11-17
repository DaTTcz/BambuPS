<?php

/*
| Ovladače tiskáren. Každý ovladač je třída implementující
| App\Printers\Contracts\PrinterDriver - návod v docs/PRINTER_DRIVERS.md.
*/
return [
    'drivers' => [
        App\Printers\Drivers\Bambu\BambuDriver::class,
    ],
];
