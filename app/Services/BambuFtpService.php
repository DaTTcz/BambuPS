<?php

namespace App\Services;

use App\Models\Printer;

class BambuFtpService
{
    private Printer $printer;

    public function __construct(Printer $printer)
    {
        $this->printer = $printer;
    }

    /**
     * Smaže soubor na tiskárně před nahráním nového. Bez tohoto kroku
     * přepisujeme stejný soubor přes FTP STOR, což nemusí vyčistit interní
     * stav/task tracking tiskárny vázaný na předchozí (např. neúspěšný) tisk
     * se stejným jménem souboru. Chybějící soubor (550) je v pořádku - není
     * co mazat.
     */
    public function delete(string $remoteFilename): bool
    {
        $url = sprintf(
            'ftps://%s:%s@%s:990/',
            'bblp',
            urlencode($this->printer->access_code),
            $this->printer->ip_address
        );

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_USERPWD        => 'bblp:' . $this->printer->access_code,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FTP_SSL        => CURLFTPSSL_ALL,
            CURLOPT_FTPSSLAUTH     => CURLFTPAUTH_TLS,
            CURLOPT_QUOTE          => ['DELE ' . $remoteFilename],
            CURLOPT_NOBODY         => true,
            CURLOPT_TIMEOUT        => 15,
        ]);

        curl_exec($ch);
        $error = curl_error($ch);
        $code  = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        // 550 = soubor neexistuje - to je v pořádku, nebylo co mazat
        if ($error && $code !== 550) {
            \Log::warning('BambuFTP delete warning (' . $remoteFilename . '): ' . $error . ' code=' . $code);
        } else {
            \Log::info('BambuFTP delete ok: ' . $remoteFilename . ' code=' . $code);
        }

        return true;
    }

    public function upload(string $localPath, string $remoteFilename): bool
    {
        $url = sprintf(
            'ftps://%s:%s@%s:990/%s',
            'bblp',
            urlencode($this->printer->access_code),
            $this->printer->ip_address,
            $remoteFilename
        );

        $fileHandle = fopen($localPath, 'r');
        if (!$fileHandle) {
            \Log::error('BambuFTP: Cannot open file ' . $localPath);
            return false;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_USERPWD        => 'bblp:' . $this->printer->access_code,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FTP_SSL        => CURLFTPSSL_ALL,
            CURLOPT_FTPSSLAUTH     => CURLFTPAUTH_TLS,
            CURLOPT_UPLOAD         => true,
            CURLOPT_INFILE         => $fileHandle,
            CURLOPT_INFILESIZE     => filesize($localPath),
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_VERBOSE        => false,
        ]);

        $result = curl_exec($ch);
        $error  = curl_error($ch);
        $code   = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        fclose($fileHandle);

        if ($error) {
            \Log::error('BambuFTP upload error: ' . $error);
            return false;
        }

        \Log::info('BambuFTP upload ok: ' . $remoteFilename . ' code=' . $code);
        return $result !== false;
    }

    public function listFiles(): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'ftps://bblp:' . urlencode($this->printer->access_code) . '@' . $this->printer->ip_address . ':990/',
            CURLOPT_USERPWD        => 'bblp:' . $this->printer->access_code,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FTP_SSL        => CURLFTPSSL_ALL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);

        $result = curl_exec($ch);
        curl_close($ch);

        if (!$result) return [];

        return array_filter(explode("\n", trim($result)));
    }
}
