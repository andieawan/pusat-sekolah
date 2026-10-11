<?php

namespace App\Support;

use InvalidArgumentException;

/** Pembaca CSV sederhana: deteksi pemisah (; atau ,), BOM, dan encoding Windows-1252. */
final class CsvParser
{
    public const MAKS_BARIS = 5000;

    /**
     * @return array{header: array<int,string>, baris: array<int,array<string,string>>}
     */
    public static function parse(string $isi): array
    {
        $isi = preg_replace('/^\xEF\xBB\xBF/', '', $isi) ?? $isi;
        if (! mb_check_encoding($isi, 'UTF-8')) {
            $isi = mb_convert_encoding($isi, 'UTF-8', 'Windows-1252');
        }

        $barisPertama = strtok($isi, "\r\n") ?: '';
        $pemisah = substr_count($barisPertama, ';') > substr_count($barisPertama, ',') ? ';' : ',';

        $h = fopen('php://temp', 'r+');
        fwrite($h, $isi);
        rewind($h);

        $header = null;
        $hasil = [];
        $nomor = 0;
        while (($sel = fgetcsv($h, 0, $pemisah, '"', '')) !== false) {
            $nomor++;
            if ($sel === [null] || self::kosong($sel)) {
                continue;
            }
            if ($header === null) {
                $header = array_map(
                    fn ($v) => preg_replace('/\s+/', '_', strtolower(trim((string) $v))),
                    $sel,
                );

                continue;
            }
            if (count($hasil) >= self::MAKS_BARIS) {
                fclose($h);
                throw new InvalidArgumentException('Maksimal '.self::MAKS_BARIS.' baris per file.');
            }
            $sel = array_pad(array_map(fn ($v) => trim((string) $v), $sel), count($header), '');
            $baris = array_combine($header, array_slice($sel, 0, count($header)));
            $baris['_baris'] = (string) $nomor;
            $hasil[] = $baris;
        }
        fclose($h);

        if ($header === null) {
            throw new InvalidArgumentException('File kosong atau tidak terbaca.');
        }

        return ['header' => $header, 'baris' => $hasil];
    }

    private static function kosong(array $sel): bool
    {
        foreach ($sel as $v) {
            if (trim((string) $v) !== '') {
                return false;
            }
        }

        return true;
    }
}
